<?php

namespace Tests\Feature\Inbound;

use App\Enums\MessageChannel;
use App\Enums\ReplyClassification;
use App\Models\Debtor;
use App\Models\Message;
use App\Services\Claude\ClaudeCompletion;
use App\Services\Claude\Contracts\ClaudeMessenger;
use App\Services\Inbound\Contracts\InboundSmsVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class InboundWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic classifier for the webhook path.
        $this->app->instance(ClaudeMessenger::class, new class implements ClaudeMessenger
        {
            public function complete(string $system, string $user, ?string $model = null): ClaudeCompletion
            {
                return new ClaudeCompletion('{"category":"dispute","rationale":"Contests the charge.","confidence":0.9}', 'claude-opus-4-8', 40, 15);
            }
        });
    }

    private function allowSmsSignature(bool $valid = true): void
    {
        $this->app->instance(InboundSmsVerifier::class, new class($valid) implements InboundSmsVerifier
        {
            public function __construct(private bool $valid) {}

            public function verify(Request $request): bool
            {
                return $this->valid;
            }
        });
    }

    public function test_valid_twilio_sms_creates_and_classifies_an_inbound_reply(): void
    {
        $this->allowSmsSignature();
        $debtor = Debtor::factory()->create(['phone' => '+15551234567']);

        $this->post('/twilio/inbound', [
            'From' => '+15551234567',
            'Body' => 'This bill is wrong.',
        ])->assertOk();

        $message = Message::where('debtor_id', $debtor->id)->first();
        $this->assertNotNull($message);
        $this->assertSame(MessageChannel::Sms, $message->channel);
        $this->assertSame(ReplyClassification::Dispute->value, $message->classification);
        $this->assertNotNull($debtor->refresh()->paused_at);
    }

    public function test_invalid_twilio_signature_is_rejected(): void
    {
        $this->allowSmsSignature(valid: false);
        Debtor::factory()->create(['phone' => '+15551234567']);

        $this->post('/twilio/inbound', ['From' => '+15551234567', 'Body' => 'x'])
            ->assertForbidden();

        $this->assertSame(0, Message::count());
    }

    public function test_unknown_sender_is_acknowledged_without_creating_a_reply(): void
    {
        $this->allowSmsSignature();

        $this->post('/twilio/inbound', ['From' => '+19990000000', 'Body' => 'hi'])
            ->assertOk();

        $this->assertSame(0, Message::count());
    }

    public function test_email_inbound_creates_and_classifies_a_reply(): void
    {
        $debtor = Debtor::factory()->create(['email' => 'ar@acme.test']);

        $this->postJson('/email/inbound', [
            'from' => 'ar@acme.test',
            'body' => 'We dispute this invoice.',
        ])->assertNoContent();

        $message = Message::where('debtor_id', $debtor->id)->first();
        $this->assertNotNull($message);
        $this->assertSame(MessageChannel::Email, $message->channel);
        $this->assertSame(ReplyClassification::Dispute->value, $message->classification);
    }

    public function test_email_inbound_for_unknown_address_is_ignored(): void
    {
        $this->postJson('/email/inbound', ['from' => 'nobody@nowhere.test', 'body' => 'hi'])
            ->assertNoContent();

        $this->assertSame(0, Message::count());
    }
}
