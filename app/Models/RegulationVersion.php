<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegulationVersion extends Model
{
    protected $fillable = [
        'regulation_id',
        'page_id',
        'version_number',
        'display_name',
        'content',
        'content_hash',
        'published_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function regulation(): BelongsTo
    {
        return $this->belongsTo(Regulation::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(RegulationAcceptance::class);
    }

    public static function contentHash(?string $displayName, ?string $content): string
    {
        $normalized = implode("\n", [
            trim((string) $displayName),
            preg_replace("/\r\n?/", "\n", trim((string) $content)),
        ]);

        return hash('sha256', $normalized);
    }
}
