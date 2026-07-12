<?php

namespace App\Http\Controllers;

use App\Mail\RegulationRevokedMail;
use App\Mail\RegulationUpdatedMail;
use App\Models\Page;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class RegulationController extends Controller
{
    public function index(): View
    {
        $regulations = Regulation::query()
            ->with(['page', 'currentVersion'])
            ->latest()
            ->paginate(20);

        return view('superAdmin.regulations.index', compact('regulations'));
    }

    public function create(): View
    {
        return view('superAdmin.regulations.create', [
            'regulation' => new Regulation([
                'starts_at' => today(),
                'is_indefinite' => true,
                'target_roles' => ['all'],
            ]),
            'pages' => $this->pages(),
            'roles' => Regulation::TARGET_ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedRegulation($request);

        $regulation = DB::transaction(function () use ($validated): Regulation {
            $regulation = Regulation::create([
                ...$validated,
                'status' => Regulation::STATUS_ACTIVE,
                'ends_at' => $validated['is_indefinite'] ? null : $validated['ends_at'],
            ]);

            $this->publishVersion($regulation);

            return $regulation;
        });

        return redirect()
            ->route('admin.regulations.index')
            ->with('success', 'Regulamin został utworzony i opublikowany.');
    }

    public function edit(Regulation $regulation): View
    {
        $regulation->load([
            'page',
            'currentVersion',
            'versions' => fn ($query) => $query
                ->withCount('acceptances')
                ->latest('version_number'),
        ]);

        return view('superAdmin.regulations.edit', [
            'regulation' => $regulation,
            'pages' => $this->pages(),
            'roles' => Regulation::TARGET_ROLES,
        ]);
    }

    public function update(Request $request, Regulation $regulation): RedirectResponse
    {
        $validated = $this->validatedRegulation($request);

        $message = DB::transaction(function () use ($regulation, $validated): string {
            $regulation->update([
                ...$validated,
                'ends_at' => $validated['is_indefinite'] ? null : $validated['ends_at'],
            ]);

            $version = $this->publishVersion($regulation);

            if (! $version) {
                return 'Regulamin został zapisany. Treść nie zmieniła się względem aktualnej wersji, więc nie wymaga ponownej akceptacji.';
            }

            $this->notifyAcceptedUsersAboutUpdate($regulation);

            return 'Opublikowano nową wersję regulaminu. Użytkownicy objęci regulaminem muszą zaakceptować ją ponownie.';
        });

        return redirect()
            ->route('admin.regulations.index')
            ->with('success', $message);
    }

    public function revoke(Regulation $regulation): RedirectResponse
    {
        DB::transaction(function () use ($regulation): void {
            $regulation->update([
                'status' => Regulation::STATUS_REVOKED,
                'revoked_at' => now(),
            ]);

            $this->notifyAcceptedUsersAboutRevocation($regulation);
        });

        return back()->with('success', 'Regulamin został odwołany. Użytkownicy, którzy go zaakceptowali, otrzymali powiadomienie.');
    }

    private function validatedRegulation(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'page_id' => ['required', 'exists:pages,id'],
            'target_roles' => ['required', 'array', 'min:1'],
            'target_roles.*' => ['required', 'string', 'in:'.implode(',', array_keys(Regulation::TARGET_ROLES))],
            'starts_at' => ['required', 'date'],
            'is_indefinite' => ['nullable', 'boolean'],
            'ends_at' => ['nullable', 'required_unless:is_indefinite,1', 'date', 'after_or_equal:starts_at'],
        ], [
            'target_roles.required' => 'Wybierz co najmniej jedną grupę użytkowników.',
            'ends_at.required_unless' => 'Podaj datę końca albo zaznacz obowiązywanie bezterminowe.',
        ]);

        $roles = array_values(array_unique($validated['target_roles']));

        if (in_array(Regulation::ROLE_ALL, $roles, true)) {
            $roles = [Regulation::ROLE_ALL];
        }

        return [
            ...$validated,
            'target_roles' => $roles,
            'is_indefinite' => $request->boolean('is_indefinite'),
        ];
    }

    private function publishVersion(Regulation $regulation): ?RegulationVersion
    {
        $regulation->load('page', 'currentVersion');
        $page = $regulation->page;

        if (! $page) {
            return null;
        }

        $hash = RegulationVersion::contentHash($page->display_name, $page->content);

        if ($regulation->currentVersion?->content_hash === $hash) {
            return null;
        }

        return $regulation->versions()->create([
            'page_id' => $page->id,
            'version_number' => ((int) $regulation->versions()->max('version_number')) + 1,
            'display_name' => $page->display_name,
            'content' => $page->content,
            'content_hash' => $hash,
            'published_at' => now(),
            'created_by_user_id' => auth()->id(),
        ]);
    }

    private function pages()
    {
        return Page::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'navigation_name']);
    }

    private function notifyAcceptedUsersAboutUpdate(Regulation $regulation): void
    {
        $users = $regulation->acceptances()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id');

        foreach ($users as $user) {
            Mail::to($user->email)->send(new RegulationUpdatedMail($regulation));
        }
    }

    private function notifyAcceptedUsersAboutRevocation(Regulation $regulation): void
    {
        $users = $regulation->acceptances()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id');

        foreach ($users as $user) {
            Mail::to($user->email)->send(new RegulationRevokedMail($regulation));
        }
    }
}
