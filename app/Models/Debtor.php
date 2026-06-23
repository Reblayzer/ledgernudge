<?php

namespace App\Models;

use Database\Factories\DebtorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debtor extends Model
{
    /** @use HasFactory<DebtorFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'company',
        'email',
        'phone',
        'external_ref',
        'tone_policy',
    ];

    /** @return HasMany<Invoice> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
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
