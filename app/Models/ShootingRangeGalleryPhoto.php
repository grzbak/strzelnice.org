<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShootingRangeGalleryPhoto extends Model
{
    protected $fillable = [
        'category_id',
        'stored_name',
        'original_name',
        'full_url',
        'description',
        'size_bytes',
        'format',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            ShootingRangeGalleryCategory::class,
            'category_id'
        );
    }
}
