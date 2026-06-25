<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PageController extends Controller
{
    private const MEDIA_DIRECTORY = 'img/pages';

    public function index(): View
    {
        $pages = Page::query()
            ->orderBy('display_name')
            ->get();

        return view('superAdmin.pages.index', compact('pages'));
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
        return view('superAdmin.pages.edit', compact('page'));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $validated = $this->validatedPage($request);

        $page->fill([
            'display_name' => $validated['display_name'],
            'content' => $validated['content'] ?? null,
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

        return redirect()
            ->route('admin.pages.index')
            ->with('success', 'Strona została zaktualizowana.');
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
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function storeImage(Request $request, string $field): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $directory = public_path(self::MEDIA_DIRECTORY);
        File::ensureDirectoryExists($directory);

        $file = $request->file($field);
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return self::MEDIA_DIRECTORY . '/' . $filename;
    }

    private function deletePublicFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        $relativePath = str_replace('\\', '/', $path);

        if (! str_starts_with($relativePath, self::MEDIA_DIRECTORY . '/')) {
            return;
        }

        $fullPath = public_path($relativePath);

        if (File::isFile($fullPath)) {
            File::delete($fullPath);
        }
    }
}