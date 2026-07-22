<?php

namespace App\Http\Controllers;

use App\Mail\UserAccountStatusMail;
use App\Models\User;
use App\Models\UserSecurityEvent;
use App\Services\UserSecurityService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class AdminDashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // return view('dashboard');
        $tours = [
            [
                'image' => 'https://source.unsplash.com/400x300/?maldives',
                'title' => 'Rajskie Malediwy',
                'description' => '7 dni wypoczynku na tropikalnej wyspie',
                'price' => 4999,
            ],
            [
                'image' => 'https://source.unsplash.com/400x300/?paris',
                'title' => 'Weekend w Paryżu',
                'description' => 'Zabytki, kuchnia i romantyczne spacery',
                'price' => 2199,
            ],
            // … więcej ofert
        ];

        return view('home', compact('tours'), [
            'unreadMessagesCount' => auth()->check()
                ? auth()->user()->messages()->unread()->count()
                : 0,

            'messages' => auth()->check()
                ? auth()->user()->messages()->latest()->limit(5)->get()
                : [],
        ]);
    }

    public function userList(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['name', 'email', 'role', 'approval_status', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $perPage = (int) ($validated['per_page'] ?? 20);

        $users = User::query()
            ->withCount(['ipAddresses', 'securityEvents'])
            ->withExists([
                'securityEvents as has_active_ip_ban' => fn ($query) => $query
                    ->active()
                    ->where('event_type', UserSecurityEvent::TYPE_BAN),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('ipAddresses', function ($query) use ($search): void {
                            $query->where('ip_address', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('superAdmin.users.index', compact(
            'users',
            'search',
            'sort',
            'direction',
            'perPage'
        ));
    }

    public function resetUserPassword(
        Request $request,
        User $user,
        UserSecurityService $security
    ) {
        $status = Password::broker()->sendResetLink([
            'email' => $user->email,
        ]);

        $security->audit('admin.user.password_reset_requested', $request->user(), $request, $user);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('error', 'Nie udało się wysłać linku resetowania hasła.');
        }

        return back()->with('success', 'Link resetowania hasła został wysłany do użytkownika.');
    }

    public function activateUser(
        Request $request,
        User $user,
        UserSecurityService $security
    ) {
        $user->update([
            'is_approved' => true,
            'approval_status' => User::APPROVAL_APPROVED,
            'approval_reason' => null,
            'approved_at' => now(),
            'approved_by_user_id' => $request->user()->id,
        ]);

        UserSecurityEvent::query()
            ->where('user_id', $user->id)
            ->where('event_type', UserSecurityEvent::TYPE_SUSPICIOUS_REGISTRATION)
            ->whereNull('resolved_at')
            ->update([
                'resolved_at' => now(),
                'resolved_by_user_id' => $request->user()->id,
            ]);

        UserSecurityEvent::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'event_type' => UserSecurityEvent::TYPE_ACCOUNT_APPROVED,
            'severity' => UserSecurityEvent::SEVERITY_INFO,
            'reason' => 'Konto zatwierdzone przez superadmina.',
            'resolved_at' => now(),
            'resolved_by_user_id' => $request->user()->id,
        ]);

        $security->audit('admin.user.activated', $request->user(), $request, $user);

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        Mail::to($user->email)->send(
            new UserAccountStatusMail($user, UserAccountStatusMail::STATUS_ACTIVATED)
        );

        return back()->with('success', 'Konto użytkownika zostało aktywowane.');
    }

    public function blockUser(
        Request $request,
        User $user,
        UserSecurityService $security
    ) {
        abort_if($user->is($request->user()), 422, 'Nie możesz zablokować własnego konta.');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $reason = $validated['reason'] ?: 'Konto zostało zablokowane przez superadmina.';

        $user->update([
            'is_approved' => false,
            'approval_status' => User::APPROVAL_REJECTED,
            'approval_reason' => $reason,
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);

        UserSecurityEvent::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'event_type' => 'account_blocked',
            'severity' => UserSecurityEvent::SEVERITY_WARNING,
            'reason' => $reason,
        ]);

        $security->audit('admin.user.blocked', $request->user(), $request, $user, [
            'reason' => $reason,
        ]);

        Mail::to($user->email)->send(
            new UserAccountStatusMail($user, UserAccountStatusMail::STATUS_BLOCKED, $reason)
        );

        return back()->with('success', 'Konto użytkownika zostało zablokowane.');
    }

    public function deleteUser(
        Request $request,
        User $user,
        UserSecurityService $security
    ) {
        abort_if($user->is($request->user()), 422, 'Nie możesz usunąć własnego konta.');

        if ($user->isSuperAdmin() && User::where('role', 'superadmin')->count() <= 1) {
            return back()->with('error', 'Nie można usunąć ostatniego konta superadmina.');
        }

        $email = $user->email;
        $name = $user->name;

        try {
            DB::transaction(function () use ($request, $security, $user): void {
                $security->audit('admin.user.deleted', $request->user(), $request, $user);
                $user->delete();
            });
        } catch (QueryException) {
            return back()->with(
                'error',
                'Nie można usunąć użytkownika, ponieważ istnieją powiązane dane. Zablokuj konto zamiast usuwać.'
            );
        }

        Mail::to($email)->send(
            new UserAccountStatusMail((object) [
                'name' => $name,
                'email' => $email,
            ], UserAccountStatusMail::STATUS_DELETED)
        );

        return redirect()
            ->route('admin.users')
            ->with('success', 'Użytkownik został usunięty.');
    }

    public function userSecurity(User $user)
    {
        $user->loadCount(['ipAddresses', 'auditLogs', 'securityEvents']);

        $ipAddresses = $user->ipAddresses()
            ->latest('last_seen_at')
            ->get();

        $securityEvents = $user->securityEvents()
            ->latest()
            ->paginate(20, ['*'], 'events_page')
            ->withQueryString();

        $auditLogs = $user->auditLogs()
            ->latest()
            ->paginate(20, ['*'], 'audit_page')
            ->withQueryString();

        return view('superAdmin.users.security', compact(
            'auditLogs',
            'ipAddresses',
            'securityEvents',
            'user'
        ));
    }

    public function storeUserSecurityEvent(
        Request $request,
        User $user,
        UserSecurityService $security
    ) {
        $validated = $request->validate([
            'ip_address' => ['required', 'ip'],
            'event_type' => ['required', Rule::in([
                UserSecurityEvent::TYPE_BAN,
                UserSecurityEvent::TYPE_SUSPICIOUS_REGISTRATION,
            ])],
            'severity' => ['required', Rule::in([
                UserSecurityEvent::SEVERITY_INFO,
                UserSecurityEvent::SEVERITY_WARNING,
                UserSecurityEvent::SEVERITY_CRITICAL,
            ])],
            'reason' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $event = UserSecurityEvent::create([
            'user_id' => $user->id,
            'ip_address' => $validated['ip_address'],
            'event_type' => $validated['event_type'],
            'severity' => $validated['severity'],
            'reason' => $validated['reason'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        $security->audit('admin.user.security_event_created', $request->user(), $request, $user, [
            'security_event_id' => $event->id,
            'event_type' => $event->event_type,
            'ip_address' => $event->ip_address,
        ]);

        return back()->with('success', 'Zdarzenie bezpieczeństwa zostało dodane.');
    }

    public function resolveUserSecurityEvent(
        Request $request,
        User $user,
        UserSecurityEvent $event,
        UserSecurityService $security
    ) {
        abort_unless((int) $event->user_id === (int) $user->id, 404);

        $event->update([
            'resolved_at' => now(),
            'resolved_by_user_id' => $request->user()->id,
        ]);

        $security->audit('admin.user.security_event_resolved', $request->user(), $request, $user, [
            'security_event_id' => $event->id,
            'event_type' => $event->event_type,
            'ip_address' => $event->ip_address,
        ]);

        return back()->with('success', 'Zdarzenie bezpieczeństwa zostało oznaczone jako zamknięte.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
