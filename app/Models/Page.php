<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'navigation_name',
        'display_name',
        'content',
        'baner',
        'background',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Page $page) {
            if (! $page->navigation_name || $page->isDirty('display_name')) {
                $page->navigation_name = static::generateUniqueNavigationName(
                    $page->display_name,
                    $page->id
                );
            }
        });
    }

    public function getBanerUrlAttribute(): ?string
    {
        return $this->baner ? asset($this->baner) : null;
    }

    public function getBackgroundUrlAttribute(): ?string
    {
        return $this->background ? asset($this->background) : null;
    }

    public static function generateUniqueNavigationName(string $displayName, ?int $ignoreId = null): string
    {
        $base = static::normalizeNavigationName($displayName);

        if ($base === '') {
            $base = 'strona';
        }

        $navigationName = $base;
        $counter = 2;

        while (static::navigationNameExists($navigationName, $ignoreId)) {
            $navigationName = $base . '_' . $counter;
            $counter++;
        }

        return $navigationName;
    }

    public static function normalizeNavigationName(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9\s_\-]+/', '')
            ->replaceMatches('/[\s\-]+/', '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();
    }

    public static function navigationNameExists(string $navigationName, ?int $ignoreId = null): bool
    {
        $pageExists = static::query()
            ->where('navigation_name', $navigationName)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($pageExists) {
            return true;
        }

        return ShootingRange::query()
            ->where('endpoint', $navigationName)
            ->exists();
    }
}