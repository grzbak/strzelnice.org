<?php

namespace App\Http\Controllers;

use App\Mail\RegulationRevokedMail;
use App\Mail\RegulationUpdatedMail;
use App\Models\Page;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    private const MEDIA_DIRECTORY = 'img/pages';

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:id,navigation_name,display_name,is_active,updated_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $sort = $validated['sort'] ?? 'display_name';
        $direction = $validated['direction'] ?? 'asc';
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = Page::query();

        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $query->where(function ($query) use ($term) {
                $query
                    ->where('navigation_name', 'like', "%{$term}%")
                    ->orWhere('display_name', 'like', "%{$term}%")
                    ->orWhere('content', 'like', "%{$term}%");
            });
        }

        match ($sort) {
            'id' => $query->orderBy('id', $direction),
            'navigation_name' => $query->orderBy('navigation_name', $direction)->orderBy('display_name'),
            'is_active' => $query->orderBy('is_active', $direction)->orderBy('display_name'),
            'updated_at' => $query->orderBy('updated_at', $direction)->orderBy('display_name'),
            default => $query->orderBy('display_name', $direction),
        };

        $pages = $query->paginate($perPage)->withQueryString();

        return view('superAdmin.pages.index', compact(
            'pages',
            'search',
            'sort',
            'direction',
            'perPage'
        ));
    }

    public function create(): View
    {
        return view('superAdmin.pages.create', [
            'page' => new Page(['is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPage($request);

        $page = new Page([
            'display_name' => $validated['display_name'],
            'content' => $validated['content'] ?? null,
            ...$this->seoPayload($validated),
            'is_active' => $request->boolean('is_active'),
        ]);

        $page->baner = $this->storeImage($request, 'baner');
        $page->background = $this->storeImage($request, 'background');
        $page->save();

        return redirect()
            ->route('admin.pages.index')
            ->with('success', 'Strona została dodana.');
    }

    public function edit(Page $page): View
    {
        $page->load(['activeRegulations.currentVersion']);

        return view('superAdmin.pages.edit', compact('page'));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $validated = $this->validatedPage($request);

        $page->fill([
            'display_name' => $validated['display_name'],
            'content' => $validated['content'] ?? null,
            ...$this->seoPayload($validated),
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->hasFile('baner')) {
            $this->deletePublicFile($page->baner);
            $page->baner = $this->storeImage($request, 'baner');
        }

        if ($request->hasFile('background')) {
            $this->deletePublicFile($page->background);
            $page->background = $this->storeImage($request, 'background');
        }

        $page->save();

        $versionedRegulations = $this->publishChangedRegulationVersions($page);
        $message = $versionedRegulations > 0
            ? 'Strona została zaktualizowana. Utworzono nową wersję regulaminu i użytkownicy muszą zaakceptować ją ponownie.'
            : 'Strona została zaktualizowana.';

        return redirect()
            ->route('admin.pages.index')
            ->with('success', $message);
    }

    public function toggleStatus(Page $page): RedirectResponse
    {
        $page->update([
            'is_active' => ! $page->is_active,
        ]);

        return back()->with(
            'success',
            $page->is_active ? 'Strona została aktywowana.' : 'Strona została ukryta.'
        );
    }

    public function destroy(Page $page): RedirectResponse
    {
        $this->revokeRegulationsForDeletedPage($page);
        $this->deletePublicFile($page->baner);
        $this->deletePublicFile($page->background);
        $page->delete();

        return redirect()
            ->route('admin.pages.index')
            ->with('success', 'Strona została usunięta.');
    }

    public function showPublicOrShooting(string $endpoint)
    {
        $page = Page::query()
            ->where('navigation_name', $endpoint)
            ->where('is_active', true)
            ->first();

        if ($page) {
            return view('pages.show', compact('page'));
        }

        return app(ShootingsController::class)->publicShow($endpoint);
    }

    private function validatedPage(Request $request): array
    {
        return $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'baner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'robots' => [
                'nullable',
                'string',
                'max:50',
                Rule::in([
                    'index,follow',
                    'noindex,follow',
                    'index,nofollow',
                    'noindex,nofollow',
                ]),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function seoPayload(array $validated): array
    {
        return collect($validated)
            ->only([
                'meta_title',
                'meta_description',
                'meta_keywords',
                'og_title',
                'og_description',
                'og_image',
                'canonical_url',
                'robots',
            ])
            ->map(fn ($value) => filled($value) ? $value : null)
            ->all();
    }

    private function storeImage(Request $request, string $field): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $directory = public_path(self::MEDIA_DIRECTORY);
        File::ensureDirectoryExists($directory);

        $file = $request->file($field);
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return self::MEDIA_DIRECTORY.'/'.$filename;
    }

    private function deletePublicFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        $relativePath = str_replace('\\', '/', $path);

        if (! str_starts_with($relativePath, self::MEDIA_DIRECTORY.'/')) {
            return;
        }

        $fullPath = public_path($relativePath);

        if (File::isFile($fullPath)) {
            File::delete($fullPath);
        }
    }

    private function publishChangedRegulationVersions(Page $page): int
    {
        $page->load(['activeRegulations.currentVersion']);
        $published = 0;

        foreach ($page->activeRegulations as $regulation) {
            $hash = RegulationVersion::contentHash($page->display_name, $page->content);

            if ($regulation->currentVersion?->content_hash === $hash) {
                continue;
            }

            $regulation->versions()->create([
                'page_id' => $page->id,
                'version_number' => ((int) $regulation->versions()->max('version_number')) + 1,
                'display_name' => $page->display_name,
                'content' => $page->content,
                'content_hash' => $hash,
                'published_at' => now(),
                'created_by_user_id' => auth()->id(),
            ]);

            $this->notifyAcceptedUsersAboutUpdate($regulation);
            $published++;
        }

        return $published;
    }

    private function revokeRegulationsForDeletedPage(Page $page): void
    {
        $page->load('activeRegulations');

        foreach ($page->activeRegulations as $regulation) {
            $regulation->update([
                'status' => Regulation::STATUS_REVOKED,
                'revoked_at' => now(),
            ]);

            $this->notifyAcceptedUsersAboutRevocation($regulation);
        }
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
