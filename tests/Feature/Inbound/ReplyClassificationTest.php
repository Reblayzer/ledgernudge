<?php

namespace Tests\Feature\Inbound;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\ReplyClassification;
use App\Models\Debtor;
use App\Models\Event;
use App\Services\Claude\ClaudeCompletion;
use App\Services\Claude\Contracts\ClaudeMessenger;
use App\Services\Inbound\InboundReplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplyClassificationTest extends TestCase
{
    use RefreshDatabase;

    /** Binds a fake messenger that returns the given raw model text. */
    private function fakeClaudeReturns(string $raw): void
    {
        $this->app->instance(ClaudeMessenger::class, new class($raw) implements ClaudeMessenger
        {
            public function __construct(private string $raw) {}

            public function complete(string $system, string $user, ?string $model = null): ClaudeCompletion
            {
                return new ClaudeCompletion($this->raw, 'claude-opus-4-8', 50, 20);
            }
        });
    }

    public function test_dispute_reply_is_recorded_classified_and_pauses_the_sequence(): void
    {
        $this->fakeClaudeReturns('{"category":"dispute","rationale":"They say the work was never completed.","confidence":0.9}');
        $debtor = Debtor::factory()->create();

        $message = app(InboundReplyService::class)
            ->record($debtor, MessageChannel::Sms, 'This invoice is wrong, the job was never finished.');

        $this->assertSame(MessageDirection::Inbound, $message->direction);
        $this->assertSame(MessageStatus::Received, $message->status);
        $this->assertSame(ReplyClassification::Dispute->value, $message->classification);

        $this->assertDatabaseHas('events', ['message_id' => $message->id, 'type' => Event::REPLY_RECEIVED]);
        $this->assertDatabaseHas('events', ['message_id' => $message->id, 'type' => Event::REPLY_CLASSIFIED]);
        $this->assertDatabaseHas('events', ['debtor_id' => $debtor->id, 'type' => Event::SEQUENCE_PAUSED]);

        $debtor->refresh();
        $this->assertNotNull($debtor->paused_at);
        $this->assertSame(ReplyClassification::Dispute->value, $debtor->pause_reason);
    }

    public function test_stop_reply_pauses_the_sequence(): void
    {
        $this->fakeClaudeReturns('{"category":"stop","rationale":"Asked to stop contacting them.","confidence":0.95}');
        $debtor = Debtor::factory()->create();

        app(InboundReplyService::class)->record($debtor, MessageChannel::Sms, 'STOP');

        $this->assertNotNull($debtor->refresh()->paused_at);
    }

    public function test_promise_to_pay_does_not_pause_the_sequence(): void
    {
        $this->fakeClaudeReturns('{"category":"promise_to_pay","rationale":"Will pay on Friday.","confidence":0.8}');
        $debtor = Debtor::factory()->create();

        $message = app(InboundReplyService::class)->record($debtor, MessageChannel::Email, 'Sorry! I will pay this Friday.');

        $this->assertSame(ReplyClassification::PromiseToPay->value, $message->classification);
        $this->assertNull($debtor->refresh()->paused_at);
        $this->assertSame(0, Event::where('type', Event::SEQUENCE_PAUSED)->count());
    }

    public function test_unparseable_classification_is_treated_as_unknown_and_pauses(): void
    {
        // Bias toward pausing when unsure: a garbled model reply must not slip through.
        $this->fakeClaudeReturns('I think this is probably a dispute but I am not sure.');
        $debtor = Debtor::factory()->create();

        $message = app(InboundReplyService::class)->record($debtor, MessageChannel::Sms, '???');

        $this->assertSame(ReplyClassification::Unknown->value, $message->classification);
        $this->assertNotNull($debtor->refresh()->paused_at);
    }
}
