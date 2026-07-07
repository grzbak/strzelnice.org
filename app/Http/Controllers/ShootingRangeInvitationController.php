<?php

namespace App\Http\Controllers;

use App\Mail\ShootingRangeInvitationMail;
use App\Mail\ShootingRangeInvitationRevokedMail;
use App\Models\ShootingRangeInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ShootingRangeInvitationController extends Controller
{
    private const RENEWED_INVITATION_DAYS = 7;

    private function userShootingRange()
    {
        return Auth::user()->shootingRanges()
            ->wherePivot('is_approved', true)
            ->firstOrFail();
    }

    public function index(Request $request)
    {
        $shootingRange = $this->userShootingRange();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'in:name,email,status,expires_at,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
        ]);
        $search = trim($validated['search'] ?? '');
        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $perPage = (int) ($validated['per_page'] ?? 20);
        $searchTerms = preg_split(
            '/\s+/u',
            $search,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        $invitations = $shootingRange->invitations()
            ->when($searchTerms, function ($query) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $query->where(function ($query) use ($term) {
                        $like = '%'.$term.'%';

                        $query
                            ->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
                }
            })
            ->when($sort === 'name', function ($query) use ($direction) {
                $query
                    ->orderBy('last_name', $direction)
                    ->orderBy('first_name', $direction)
                    ->orderBy('email', $direction);
            })
            ->when($sort !== 'name', function ($query) use ($sort, $direction) {
                $query->orderBy($sort, $direction);
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.shooting_ranges.invitations.index', compact(
            'shootingRange',
            'invitations',
            'search',
            'sort',
            'direction',
            'perPage'
        ));
    }

    public function store(Request $request)
    {
        $shootingRange = $this->userShootingRange();

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);
        $email = mb_strtolower(trim($validated['email']));

        $existingInvitation = $shootingRange->invitations()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existingInvitation) {
            return redirect()
                ->route('shooting.invitations.index', ['search' => $email])
                ->with(
                    'warning',
                    "Użytkownik z adresem {$email} jest już na liście. Nie wprowadzono zmian."
                );
        }

        $shootingRange->invitations()->create([
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $email,
            'expires_at' => now()->addDays(self::RENEWED_INVITATION_DAYS),
            'expires_soon_notified_at' => null,
            'status' => ShootingRangeInvitation::STATUS_NONE,
            'invited_by' => auth()->id(),
        ]);

        return back()->with('success', 'Dodano zaproszenie.');
    }

    public function import(Request $request)
    {
        $shootingRange = $this->userShootingRange();

        $request->validate([
            'file' => ['required', 'file', 'mimes:xls,xlsx'],
        ]);

        $worksheet = IOFactory::load($request->file('file')->getRealPath())
            ->getActiveSheet();
        $imported = 0;
        $rowNumber = 0;

        foreach ($worksheet->toArray(null, true, true, false) as $row) {
            $rowNumber++;
            [$firstName, $lastName, $email] = array_map(
                fn ($value) => is_string($value) ? trim($value) : $value,
                array_pad($row, 3, null)
            );

            if ($rowNumber === 1 && $this->isInvitationImportHeader($row)) {
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $shootingRange->invitations()->updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'expires_at' => now()->addDays(self::RENEWED_INVITATION_DAYS),
                    'expires_soon_notified_at' => null,
                    'status' => ShootingRangeInvitation::STATUS_NONE,
                    'invited_by' => auth()->id(),
                ]
            );

            $imported++;
        }

        return back()->with('success', "Zaimportowano zaproszenia: {$imported}.");
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Zaproszenia');
        $sheet->fromArray([
            ['Imię', 'Nazwisko', 'Email'],
            ['Jan', 'Kowalski', 'jan.kowalski@example.com'],
        ]);
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(36);
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'szablon-zaproszen.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function generateLink(Request $request, ShootingRangeInvitation $invitation)
    {
        $this->authorizeInvitation($invitation);

        if ($invitation->isAccepted()) {
            return back()->with('error', 'Nie można wygenerować nowego linku dla zaakceptowanego zaproszenia.');
        }

        $this->refreshInvitationLink($invitation);

        return back()->with(
            'success',
            'Wygenerowano nowy link zaproszenia ważny przez 7 dni.'
        );
    }

    public function send(ShootingRangeInvitation $invitation)
    {
        $this->authorizeInvitation($invitation);

        if ($invitation->isAccepted()) {
            return back()->with('error', 'Zaproszenie zostało już zaakceptowane. Nie wysłano nowej wiadomości.');
        }

        $this->refreshInvitationLink($invitation);
        Mail::to($invitation->email)->send(new ShootingRangeInvitationMail($invitation->fresh('shootingRange')));

        return back()->with('success', 'Wysłano zaproszenie do klienta.');
    }

    public function revoke(ShootingRangeInvitation $invitation)
    {
        $this->authorizeInvitation($invitation);

        if ($invitation->isRevoked()) {
            return back()->with('error', 'Zaproszenie jest już odwołane.');
        }

        $invitation->update([
            'status' => ShootingRangeInvitation::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);

        Mail::to($invitation->email)->send(new ShootingRangeInvitationRevokedMail($invitation->fresh('shootingRange')));

        return back()->with('success', 'Odwołano zaproszenie i wysłano informację do klienta.');
    }

    public function destroy(ShootingRangeInvitation $invitation)
    {
        $this->authorizeInvitation($invitation);

        $email = $invitation->email;
        $invitation->delete();

        return redirect()
            ->route('shooting.invitations.index')
            ->with(
                'success',
                "Usunięto użytkownika {$email} z listy strzelnicy."
            );
    }

    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:generate,send,revoke,delete'],
        ]);

        $invitations = ShootingRangeInvitation::whereIn('id', $validated['ids'])->get();
        $done = 0;
        $skipped = 0;

        foreach ($invitations as $invitation) {
            $this->authorizeInvitation($invitation);

            if ($validated['action'] === 'generate') {
                if ($invitation->isAccepted()) {
                    $skipped++;

                    continue;
                }

                $this->refreshInvitationLink($invitation);
                $done++;
            }

            if ($validated['action'] === 'send') {
                if ($invitation->isAccepted()) {
                    $skipped++;

                    continue;
                }

                $this->refreshInvitationLink($invitation);
                Mail::to($invitation->email)->send(new ShootingRangeInvitationMail($invitation->fresh('shootingRange')));
                $done++;
            }

            if ($validated['action'] === 'revoke') {
                if ($invitation->isRevoked()) {
                    $skipped++;

                    continue;
                }

                $invitation->update([
                    'status' => ShootingRangeInvitation::STATUS_REVOKED,
                    'revoked_at' => now(),
                ]);

                Mail::to($invitation->email)->send(new ShootingRangeInvitationRevokedMail($invitation->fresh('shootingRange')));
                $done++;
            }

            if ($validated['action'] === 'delete') {
                $invitation->delete();
                $done++;
            }
        }

        $message = "Wykonano operację zbiorczą. Przetworzono: {$done}.";

        if ($skipped > 0) {
            $message .= " Pominięto: {$skipped}.";
        }

        return back()->with('success', $message);
    }

    public function accept(string $token)
    {
        $invitation = ShootingRangeInvitation::where('token', $token)->firstOrFail();

        if ($invitation->isRevoked()) {
            abort(403, 'Zaproszenie zostało odwołane.');
        }

        if ($invitation->isExpired()) {
            $invitation->update([
                'status' => ShootingRangeInvitation::STATUS_EXPIRED,
            ]);

            abort(403, 'Zaproszenie wygasło.');
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('index')
                ->with('success', 'Zaproszenie było już zaakceptowane.');
        }

        if (! auth()->check()) {
            session(['pending_invitation_token' => $token]);

            return redirect()->route('login')
                ->with('success', 'Zaloguj się, aby zaakceptować zaproszenie.');
        }

        if (
            mb_strtolower((string) auth()->user()->email)
            !== mb_strtolower((string) $invitation->email)
        ) {
            abort(
                403,
                'Zaproszenie zostało wystawione dla innego adresu e-mail.'
            );
        }

        $invitation->update([
            'status' => ShootingRangeInvitation::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('index')
            ->with('success', 'Zaproszenie zaakceptowane.');
    }

    private function refreshInvitationLink(ShootingRangeInvitation $invitation): void
    {
        $invitation->update([
            'token' => Str::random(64),
            'status' => ShootingRangeInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(self::RENEWED_INVITATION_DAYS),
            'expires_soon_notified_at' => null,
            'revoked_at' => null,
        ]);

        $invitation->refresh();
    }

    private function isInvitationImportHeader(array $row): bool
    {
        $columns = array_map(
            fn ($value) => Str::lower(trim((string) $value)),
            array_slice($row, 0, 3)
        );

        return in_array($columns[0] ?? '', ['imię', 'imie', 'first_name', 'first name'], true)
            && in_array($columns[1] ?? '', ['nazwisko', 'last_name', 'last name'], true)
            && in_array($columns[2] ?? '', ['email', 'e-mail'], true);
    }

    private function authorizeInvitation(ShootingRangeInvitation $invitation): void
    {
        $shootingRange = $this->userShootingRange();

        abort_if($invitation->shooting_range_id !== $shootingRange->id, 403);
    }
}
