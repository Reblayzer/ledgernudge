<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $fillable = [
        'debtor_id',
        'invoice_id',
        'direction',
        'channel',
        'status',
        'body',
        'model',
        'input_tokens',
        'output_tokens',
        'sequence_step',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'channel' => MessageChannel::class,
            'status' => MessageStatus::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'sequence_step' => 'integer',
        ];
    }

    /** @return BelongsTo<Debtor, Message> */
    public function debtor(): BelongsTo
    {
        return $this->belongsTo(Debtor::class);
    }

    /** @return BelongsTo<Invoice, Message> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return HasMany<Event> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
