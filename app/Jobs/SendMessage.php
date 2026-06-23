<?php

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Services\Sending\MessageSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Delivers an approved message. Re-reads the message at run time and only sends
 * if it is still Approved, so a message can't be double-sent or sent after being
 * superseded. Rate-limited to protect the email/SMS providers.
 */
class SendMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public Message $message) {}

    public function handle(MessageSender $sender): void
    {
        $message = $this->message->fresh();

        if (! $message || $message->status !== MessageStatus::Approved) {
            return;
        }

        $sender->send($message);
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RateLimited('dunning-sends')];
    }
}
