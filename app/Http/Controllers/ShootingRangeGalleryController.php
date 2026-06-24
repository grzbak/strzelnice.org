<?php

namespace App\Http\Controllers;

use App\Models\ShootingRange;
use App\Models\ShootingRangeGalleryCategory;
use App\Models\ShootingRangeGalleryPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShootingRangeGalleryController extends Controller
{
    public function index(ShootingRange $shootingRange)
    {
        $this->authorizeManagement($shootingRange);

        $gallery = $shootingRange->gallery()
            ->with('photos')
            ->first();

        return view('shooting_ranges.gallery.index', compact(
            'shootingRange',
            'gallery'
        ));
    }

    public function publicGallery(string $endpoint)
    {
        $shootingRange = ShootingRange::query()
            ->where('endpoint', $endpoint)
            ->where('is_visible', true)
            ->where('is_archived', false)
            ->firstOrFail();

        $gallery = $shootingRange->gallery()
            ->where('is_visible', true)
            ->with('photos')
            ->first();

        return view('shooting_ranges.gallery.public', compact(
            'shootingRange',
            'gallery'
        ));
    }

    public function store(Request $request, ShootingRange $shootingRange)
    {
        $this->authorizeManagement($shootingRange);

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png',
                'max:1024',
            ],
        ]);

        $isSuperAdmin = auth()->user()->role === 'superadmin';

        $gallery = $shootingRange->gallery;

        if ($gallery && ! $gallery->allow_upload && ! $isSuperAdmin) {
            return back()->with('error', 'Dodawanie zdjęć zostało wyłączone.');
        }

        $incomingSize = collect($validated['photos'])
            ->sum(fn ($photo) => $photo->getSize());

        $storedFiles = [];

        try {
            DB::transaction(function () use (
                $shootingRange,
                $validated,
                $incomingSize,
                &$storedFiles
            ) {
                $gallery = ShootingRangeGalleryCategory::query()
                    ->where('shooting_range_id', $shootingRange->id)
                    ->lockForUpdate()
                    ->first();

                if (! $gallery) {
                    $folderPath = 'gallery/' . $shootingRange->endpoint;

                    $gallery = ShootingRangeGalleryCategory::create([
                        'shooting_range_id' => $shootingRange->id,
                        'name' => $shootingRange->endpoint,
                        'folder_path' => $folderPath,
                        'capacity_bytes' => 50 * 1024 * 1024,
                        'is_visible' => true,
                        'is_deletable' => false,
                    ]);

                }

                $usedBytes = (int) $gallery->photos()->sum('size_bytes');

                if ($usedBytes + $incomingSize > $gallery->capacity_bytes) {
                    //abort(422, 'Przekroczono pojemność galerii.');

                    throw ValidationException::withMessages([
                        'photos' => 'Próbujesz przekroczyć dostępną powierzchnię galerii.',
                    ]);
                }

                $absoluteFolder = public_path($gallery->folder_path);

                if (! File::isDirectory($absoluteFolder)) {
                    File::makeDirectory($absoluteFolder, 0755, true);
                }

                foreach ($validated['photos'] as $photo) {
                    $extension = strtolower($photo->getClientOriginalExtension());
                    $storedName = Str::uuid() . '.' . $extension;

                    $photo->move($absoluteFolder, $storedName);

                    $storedFiles[] = $absoluteFolder . DIRECTORY_SEPARATOR . $storedName;

                    $gallery->photos()->create([
                        'stored_name' => $storedName,
                        'original_name' => $photo->getClientOriginalName(),
                        'full_url' => asset($gallery->folder_path . '/' . $storedName),
                        'size_bytes' => File::size(
                            $absoluteFolder . DIRECTORY_SEPARATOR . $storedName
                        ),
                        'format' => $extension,
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            foreach ($storedFiles as $storedFile) {
                File::delete($storedFile);
            }

            throw $exception;
        }

        return back()->with('success', 'Zdjęcia zostały dodane.');
    }

    public function updatePhoto(
        Request $request,
        ShootingRange $shootingRange,
        ShootingRangeGalleryPhoto $photo
    ) {
        $this->authorizePhoto($shootingRange, $photo);

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $photo->update($validated);

        return back()->with('success', 'Opis zdjęcia został zapisany.');
    }

    public function destroyPhoto(
        ShootingRange $shootingRange,
        ShootingRangeGalleryPhoto $photo
    ) {
        $this->authorizePhoto($shootingRange, $photo);

        $gallery = $photo->category;
        $isSuperAdmin = auth()->user()->role === 'superadmin';

        if (! $gallery->allow_delete && ! $isSuperAdmin) {
            return back()->with('error', 'Usuwanie zdjęć zostało wyłączone.');
        }

        File::delete(public_path(
            $gallery->folder_path . '/' . $photo->stored_name
        ));

        $photo->delete();

        return back()->with('success', 'Zdjęcie zostało usunięte.');
    }

    public function updateSettings(
        Request $request,
        ShootingRange $shootingRange
    ) {
        abort_unless(auth()->user()->role === 'superadmin', 403);

        $validated = $request->validate([
            'capacity_mb' => ['required', 'integer', 'min:1', 'max:10240'],
            'is_visible' => ['nullable', 'boolean'],
            'allow_upload' => ['nullable', 'boolean'],
            'allow_delete' => ['nullable', 'boolean'],
        ]);

        $gallery = $shootingRange->gallery()->firstOrCreate([
            'shooting_range_id' => $shootingRange->id,
        ], [
            'name' => $shootingRange->endpoint,
            'folder_path' => 'gallery/' . $shootingRange->endpoint,
        ]);

        $newCapacity = $validated['capacity_mb'] * 1024 * 1024;

        if ($newCapacity < $gallery->used_bytes) {
            return back()->withErrors([
                'capacity_mb' => 'Pojemność nie może być mniejsza od zajętego miejsca.',
            ]);
        }

        $gallery->update([
            'capacity_bytes' => $newCapacity,
            'is_visible' => $request->boolean('is_visible'),
            'allow_upload' => $request->boolean('allow_upload'),
            'allow_delete' => $request->boolean('allow_delete'),
        ]);

        return back()->with('success', 'Ustawienia galerii zostały zapisane.');
    }

    private function authorizeManagement(ShootingRange $shootingRange): void
    {
        $user = auth()->user();

        abort_unless(
            $user->role === 'superadmin'
            || $shootingRange->employees()->whereKey($user->id)->exists(),
            403
        );
    }

    private function authorizePhoto(
        ShootingRange $shootingRange,
        ShootingRangeGalleryPhoto $photo
    ): void {
        abort_unless(
            $photo->category->shooting_range_id === $shootingRange->id,
            404
        );

        $this->authorizeManagement($shootingRange);
    }
}
