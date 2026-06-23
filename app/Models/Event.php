<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit log row. Only created_at is managed; rows are never updated.
 * Event types are plain strings (the vocabulary grows each sprint) with the
 * known ones declared as constants so call sites stay typo-safe.
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    // Sprint 1 vocabulary; later sprints add payment.*, message.*, reply.* types.
    public const INVOICE_CREATED = 'invoice.created';

    public const DEBTOR_IMPORTED = 'debtor.imported';

    protected $fillable = [
        'debtor_id',
        'invoice_id',
        'message_id',
        'user_id',
        'type',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Debtor, Event> */
    public function debtor(): BelongsTo
    {
        return $this->belongsTo(Debtor::class);
    }

    /** @return BelongsTo<Invoice, Event> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Message, Event> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** @return BelongsTo<User, Event> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
