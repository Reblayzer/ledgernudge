<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'debtor_id',
        'number',
        'amount_cents',
        'amount_paid_cents',
        'currency',
        'due_date',
        'status',
        'paid_at',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'payment_url',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'amount_paid_cents' => 'integer',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'status' => InvoiceStatus::class,
        ];
    }

    /** Remaining balance in cents, never negative. */
    protected function outstandingCents(): Attribute
    {
        return Attribute::get(
            fn () => max(0, $this->amount_cents - $this->amount_paid_cents),
        );
    }

    /** @return BelongsTo<Debtor, Invoice> */
    public function debtor(): BelongsTo
    {
        return $this->belongsTo(Debtor::class);
    }

    /** @return HasMany<Message> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasMany<Event> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
