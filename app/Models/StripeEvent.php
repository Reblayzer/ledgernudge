<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per Stripe webhook event we have seen. Keyed by the Stripe event id
 * (a string), append-only, used purely for idempotency.
 */
class StripeEvent extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'type',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
