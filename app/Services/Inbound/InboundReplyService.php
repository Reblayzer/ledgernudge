<?php

namespace App\Services\Inbound;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Debtor;
use App\Models\Event;
use App\Models\Message;

/**
 * Records an inbound debtor reply, classifies it with Claude, and pauses the
 * debtor's sequence (flagging a human) when the classification warrants it.
 * Every step is written to the append-only event log.
 */
class InboundReplyService
{
    public function __construct(private ReplyClassifier $classifier) {}

    public function record(Debtor $debtor, MessageChannel $channel, string $body): Message
    {
        $message = $debtor->messages()->create([
            'direction' => MessageDirection::Inbound,
            'channel' => $channel,
            'status' => MessageStatus::Received,
            'body' => $body,
        ]);

        $this->log($debtor, $message, Event::REPLY_RECEIVED, ['channel' => $channel->value]);

        $result = $this->classifier->classify($body);
        $message->update(['classification' => $result->category->value]);

        $this->log($debtor, $message, Event::REPLY_CLASSIFIED, [
            'category' => $result->category->value,
            'rationale' => $result->rationale,
            'confidence' => $result->confidence,
        ]);

        if ($result->category->pausesSequence() && ! $debtor->isPaused()) {
            $debtor->update([
                'paused_at' => now(),
                'pause_reason' => $result->category->value,
            ]);

            $this->log($debtor, $message, Event::SEQUENCE_PAUSED, [
                'reason' => $result->category->value,
                'rationale' => $result->rationale,
            ]);
        }

        return $message;
    }

    private function log(Debtor $debtor, Message $message, string $type, array $data): void
    {
        Event::create([
            'debtor_id' => $debtor->id,
            'message_id' => $message->id,
            'type' => $type,
            'data' => $data,
        ]);
    }
}
