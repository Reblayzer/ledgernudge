<?php

namespace App\Services\Dunning;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Message;
use App\Services\Claude\Contracts\ClaudeMessenger;

/**
 * Asks Claude to draft the next dunning message for an invoice and saves it as
 * pending_approval — never sent. Token usage is recorded on the message and in
 * a message.drafted event so the cost of each draft is inspectable.
 */
class DunningDraftService
{
    public function __construct(
        private ClaudeMessenger $messenger,
        private DunningPromptBuilder $prompts,
    ) {}

    public function draftFor(Invoice $invoice, MessageChannel $channel = MessageChannel::Email): Message
    {
        [$system, $user] = $this->prompts->build($invoice);

        $completion = $this->messenger->complete($system, $user);

        $message = $invoice->messages()->create([
            'debtor_id' => $invoice->debtor_id,
            'direction' => MessageDirection::Outbound,
            'channel' => $channel,
            'status' => MessageStatus::PendingApproval,
            'body' => $completion->text,
            'model' => $completion->model,
            'input_tokens' => $completion->inputTokens,
            'output_tokens' => $completion->outputTokens,
        ]);

        Event::create([
            'debtor_id' => $invoice->debtor_id,
            'invoice_id' => $invoice->id,
            'message_id' => $message->id,
            'type' => Event::MESSAGE_DRAFTED,
            'data' => [
                'model' => $completion->model,
                'input_tokens' => $completion->inputTokens,
                'output_tokens' => $completion->outputTokens,
            ],
        ]);

        return $message;
    }
}
