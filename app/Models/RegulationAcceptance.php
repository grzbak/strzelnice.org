<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegulationAcceptance extends Model
{
    protected $fillable = [
        'user_id',
        'regulation_id',
        'regulation_version_id',
        'accepted_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function regulation(): BelongsTo
    {
        return $this->belongsTo(Regulation::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(RegulationVersion::class, 'regulation_version_id');
    }
}
