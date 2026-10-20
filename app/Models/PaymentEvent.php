<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    protected $fillable = [
        'payment_id',
        'provider',
        'event_type',
        'payload',
        'signature_valid',
        'received_at',
        'processed_at',
        'processing_status',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'signature_valid' => 'boolean',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
