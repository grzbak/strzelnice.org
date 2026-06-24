<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShootingRangeGalleryCategory extends Model
{
    protected $fillable = [
        'shooting_range_id',
        'name',
        'folder_path',
        'capacity_bytes',
        'is_visible',
        'is_deletable',
        'allow_upload',
        'allow_delete',
    ];

    protected $casts = [
        'capacity_bytes' => 'integer',
        'is_visible' => 'boolean',
        'is_deletable' => 'boolean',
        'allow_upload' => 'boolean',
        'allow_delete' => 'boolean',
    ];

    public function shootingRange(): BelongsTo
    {
        return $this->belongsTo(ShootingRange::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(
            ShootingRangeGalleryPhoto::class,
            'category_id'
        );
    }

    public function getUsedBytesAttribute(): int
    {
        return (int) $this->photos()->sum('size_bytes');
    }

    public function getRemainingBytesAttribute(): int
    {
        return max(0, $this->capacity_bytes - $this->used_bytes);
    }
}
