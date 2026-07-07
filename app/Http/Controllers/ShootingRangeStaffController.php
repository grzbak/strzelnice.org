<?php

namespace App\Http\Controllers;

use App\Mail\ShootingRangeStaffRoleMail;
use App\Models\ShootingRange;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShootingRangeStaffController extends Controller
{
    public function index(Request $request, ShootingRange $shootingRange): View
    {
        $this->authorizeStaffManagement($request->user(), $shootingRange);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'in:name,email,staff_role,account_status'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
        ]);
        $search = trim($validated['search'] ?? '');
        $sort = $validated['sort'] ?? 'staff_role';
        $direction = $validated['direction'] ?? 'asc';
        $perPage = (int) ($validated['per_page'] ?? 20);
        $searchTerms = preg_split(
            '/\s+/u',
            $search,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        $staff = $shootingRange->employees()
            ->when($searchTerms, function ($query) use ($searchTerms): void {
                foreach ($searchTerms as $term) {
                    $query->where(function ($query) use ($term): void {
                        $like = '%'.$term.'%';

                        $query
                            ->where('users.name', 'like', $like)
                            ->orWhere('users.email', 'like', $like);
                    });
                }
            })
            ->when($sort === 'staff_role', function ($query) use ($direction): void {
                $query
                    ->orderByRaw(
                        "CASE shooting_range_employee.role WHEN 'admin' THEN 0 ELSE 1 END "
                        .($direction === 'asc' ? 'ASC' : 'DESC')
                    )
                    ->orderBy('users.name');
            })
            ->when($sort === 'name', function ($query) use ($direction): void {
                $query->orderBy('users.name', $direction);
            })
            ->when($sort === 'email', function ($query) use ($direction): void {
                $query->orderBy('users.email', $direction);
            })
            ->when($sort === 'account_status', function ($query) use ($direction): void {
                $query
                    ->orderBy('users.is_approved', $direction)
                    ->orderBy('users.name');
            })
            ->orderBy('users.id')
            ->paginate($perPage)
            ->withQueryString();
        $activeAdministratorsCount = $this->activeAdministratorsCount(
            $shootingRange
        );

        return view('admin.shooting_ranges.staff', [
            'shootingRange' => $shootingRange,
            'staff' => $staff,
            'canManageAdministrators' => $request->user()->isSuperAdmin(),
            'activeAdministratorsCount' => $activeAdministratorsCount,
            'hasActiveAdministrator' => $activeAdministratorsCount > 0,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'perPage' => $perPage,
        ]);
    }

    public function search(
        Request $request,
        ShootingRange $shootingRange
    ): JsonResponse {
        $this->authorizeStaffManagement($request->user(), $shootingRange);

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ]);
        $term = mb_strtolower(trim($validated['q']));
        $like = '%'.$term.'%';

        $users = User::query()
            ->where('role', '!=', 'superadmin')
            ->where('is_approved', true)
            ->whereNotNull('email_verified_at')
            ->whereDoesntHave('shootingRanges')
            ->where(function ($query) use ($like): void {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json(['users' => $users]);
    }

    public function store(
        Request $request,
        ShootingRange $shootingRange
    ): RedirectResponse {
        $actor = $request->user();
        $this->authorizeStaffManagement($actor, $shootingRange);

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->where('is_approved', true)
                        ->whereNotNull('email_verified_at')
                        ->where('role', '!=', 'superadmin')
                ),
            ],
            'role' => ['required', Rule::in(['admin', 'pracownik'])],
        ]);

        if ($validated['role'] === 'admin' && ! $actor->isSuperAdmin()) {
            abort(403, 'Tylko superadmin może przypisywać administratorów strzelnicy.');
        }

        $result = DB::transaction(function () use (
            $shootingRange,
            $validated
        ): array {
            $lockedShootingRange = ShootingRange::query()
                ->lockForUpdate()
                ->findOrFail($shootingRange->id);
            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($validated['user_id']);
            $existingRange = $user->shootingRanges()
                ->first(['shooting_ranges.id', 'shooting_ranges.name']);

            if ($existingRange) {
                return [
                    'user' => $user,
                    'existing_range' => $existingRange,
                    'missing_administrator' => false,
                ];
            }

            if (
                $validated['role'] === 'pracownik'
                && $this->activeAdministratorsCount($lockedShootingRange) === 0
            ) {
                return [
                    'user' => $user,
                    'existing_range' => null,
                    'missing_administrator' => true,
                ];
            }

            $lockedShootingRange->employees()->attach($user->id, [
                'role' => $validated['role'],
                'is_approved' => true,
            ]);
            $this->synchronizeSystemRole($user);

            return [
                'user' => $user,
                'existing_range' => null,
                'missing_administrator' => false,
            ];
        });

        $user = $result['user'];
        $existingRange = $result['existing_range'];

        if ($existingRange) {
            return back()->with(
                'error',
                "Użytkownik {$user->name} jest już przypisany do strzelnicy "
                ."{$existingRange->name}. Jeden użytkownik może pracować tylko "
                .'w jednej strzelnicy.'
            );
        }

        if ($result['missing_administrator']) {
            return back()->with(
                'error',
                'Nie można dodać pracownika, ponieważ strzelnica nie ma '
                .'aktywnego administratora. Najpierw przypisz administratora.'
            );
        }

        $roleLabel = $validated['role'] === 'admin'
            ? 'administratora'
            : 'pracownika';

        Mail::to($user->email)->send(new ShootingRangeStaffRoleMail(
            $user,
            $shootingRange,
            $validated['role'],
            ShootingRangeStaffRoleMail::CHANGE_ASSIGNED
        ));

        return back()->with(
            'success',
            "Użytkownik {$user->name} został dołączony jako {$roleLabel} strzelnicy."
        );
    }

    public function destroy(
        Request $request,
        ShootingRange $shootingRange,
        User $user
    ): RedirectResponse {
        $actor = $request->user();
        $this->authorizeStaffManagement($actor, $shootingRange);

        $staffMember = $shootingRange->employees()
            ->whereKey($user->id)
            ->firstOrFail();

        if (
            $staffMember->pivot->role === 'admin'
            && ! $actor->isSuperAdmin()
        ) {
            abort(403, 'Administrator strzelnicy nie może usuwać innych administratorów.');
        }

        DB::transaction(function () use ($shootingRange, $user): void {
            $shootingRange->employees()->detach($user->id);
            $this->synchronizeSystemRole($user);
        });

        return back()->with(
            'success',
            "Użytkownik {$user->name} został odłączony od strzelnicy."
        );
    }

    public function updateRole(
        Request $request,
        ShootingRange $shootingRange,
        User $user
    ): RedirectResponse {
        $actor = $request->user();
        $this->authorizeStaffManagement($actor, $shootingRange);
        abort_unless(
            $actor->isSuperAdmin(),
            403,
            'Tylko superadmin może zmieniać role personelu strzelnicy.'
        );

        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'pracownik'])],
        ]);
        $result = DB::transaction(function () use (
            $shootingRange,
            $user,
            $validated
        ): array {
            $lockedShootingRange = ShootingRange::query()
                ->lockForUpdate()
                ->findOrFail($shootingRange->id);
            $staffMember = $lockedShootingRange->employees()
                ->whereKey($user->id)
                ->firstOrFail();

            if ($staffMember->pivot->role === $validated['role']) {
                return ['unchanged' => true, 'last_administrator' => false];
            }

            if (
                $staffMember->pivot->role === 'admin'
                && $validated['role'] === 'pracownik'
                && $this->activeAdministratorsCount(
                    $lockedShootingRange,
                    $user->id
                ) === 0
            ) {
                return ['unchanged' => false, 'last_administrator' => true];
            }

            $lockedShootingRange->employees()->updateExistingPivot($user->id, [
                'role' => $validated['role'],
                'is_approved' => true,
            ]);
            $this->synchronizeSystemRole($user);

            return ['unchanged' => false, 'last_administrator' => false];
        });

        if ($result['unchanged']) {
            return back()->with('success', 'Rola użytkownika nie wymagała zmiany.');
        }

        if ($result['last_administrator']) {
            return back()->with(
                'error',
                'Nie można zmienić ostatniego administratora na pracownika. '
                .'Najpierw przypisz drugiego aktywnego administratora.'
            );
        }

        $roleLabel = $validated['role'] === 'admin'
            ? 'administratora'
            : 'pracownika';

        Mail::to($user->email)->send(new ShootingRangeStaffRoleMail(
            $user->fresh(),
            $shootingRange,
            $validated['role'],
            ShootingRangeStaffRoleMail::CHANGE_ROLE_UPDATED
        ));

        return back()->with(
            'success',
            "Użytkownik {$user->name} otrzymał rolę {$roleLabel} strzelnicy."
        );
    }

    private function authorizeStaffManagement(
        User $user,
        ShootingRange $shootingRange
    ): void {
        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless(
            $user->is_approved
                && $user->isAdmin()
                && $user->shootingRanges()
                    ->whereKey($shootingRange->id)
                    ->wherePivot('role', 'admin')
                    ->wherePivot('is_approved', true)
                    ->exists(),
            403,
            'Nie masz uprawnień do zarządzania personelem tej strzelnicy.'
        );
    }

    private function synchronizeSystemRole(User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $roles = $user->shootingRanges()
            ->wherePivot('is_approved', true)
            ->pluck('shooting_range_employee.role');

        $role = $roles->contains('admin')
            ? 'admin'
            : ($roles->contains('pracownik') ? 'pracownik' : 'klient');

        if ($user->role !== $role) {
            $user->update(['role' => $role]);
        }
    }

    private function activeAdministratorsCount(
        ShootingRange $shootingRange,
        ?int $excludeUserId = null
    ): int {
        return $shootingRange->employees()
            ->where('shooting_range_employee.role', 'admin')
            ->where('shooting_range_employee.is_approved', true)
            ->where('users.is_approved', true)
            ->when(
                $excludeUserId,
                fn ($query) => $query->where('users.id', '!=', $excludeUserId)
            )
            ->count();
    }
}
