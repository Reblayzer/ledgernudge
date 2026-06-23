<?php

namespace Tests\Feature\Dunning;

use App\Enums\InvoiceStatus;
use App\Enums\MessageStatus;
use App\Models\Debtor;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\User;
use App\Services\Claude\ClaudeCompletion;
use App\Services\Claude\Contracts\ClaudeMessenger;
use App\Services\Dunning\DunningDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DunningDraftTest extends TestCase
{
    use RefreshDatabase;

    /** Records the prompts it is given and returns a canned completion. */
    private object $messenger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messenger = new class implements ClaudeMessenger
        {
            public string $system = '';

            public string $user = '';

            public int $calls = 0;

            public function complete(string $system, string $user, ?string $model = null): ClaudeCompletion
            {
                $this->system = $system;
                $this->user = $user;
                $this->calls++;

                return new ClaudeCompletion(
                    text: 'Hi — invoice INV-555 for $1,250.00 is now overdue. Could you arrange payment, or let us know if something is wrong?',
                    model: 'claude-opus-4-8',
                    inputTokens: 1200,
                    outputTokens: 60,
                );
            }
        };

        $this->app->instance(ClaudeMessenger::class, $this->messenger);
    }

    private function overdueInvoice(): Invoice
    {
        $debtor = Debtor::factory()->create([
            'name' => 'Acme Roofing',
            'tone_policy' => 'Warm but firm; first-name basis; never threaten legal action.',
        ]);

        return Invoice::factory()->for($debtor)->create([
            'number' => 'INV-555',
            'amount_cents' => 125_000,
            'currency' => 'usd',
            'status' => InvoiceStatus::Open,
        ]);
    }

    public function test_drafting_creates_a_pending_approval_message_with_token_usage(): void
    {
        $invoice = $this->overdueInvoice();

        $message = app(DunningDraftService::class)->draftFor($invoice);

        $this->assertSame(MessageStatus::PendingApproval, $message->status);
        $this->assertSame($invoice->id, $message->invoice_id);
        $this->assertSame($invoice->debtor_id, $message->debtor_id);
        $this->assertStringContainsString('INV-555', $message->body);
        $this->assertSame('claude-opus-4-8', $message->model);
        $this->assertSame(1200, $message->input_tokens);
        $this->assertSame(60, $message->output_tokens);

        $this->assertDatabaseHas('events', [
            'invoice_id' => $invoice->id,
            'message_id' => $message->id,
            'type' => Event::MESSAGE_DRAFTED,
        ]);
    }

    public function test_prompt_includes_tone_policy_and_invoice_facts(): void
    {
        $invoice = $this->overdueInvoice();

        app(DunningDraftService::class)->draftFor($invoice);

        $this->assertSame(1, $this->messenger->calls);
        $this->assertStringContainsString('never threaten legal action', $this->messenger->user);
        $this->assertStringContainsString('INV-555', $this->messenger->user);
        $this->assertStringContainsString('1,250.00', $this->messenger->user);
        // The system prompt must forbid auto-sending / preamble (output is the body only).
        $this->assertStringContainsString('body', strtolower($this->messenger->system));
    }

    public function test_operator_can_trigger_a_draft_via_route(): void
    {
        $invoice = $this->overdueInvoice();

        $this->actingAs(User::factory()->create())
            ->post("/invoices/{$invoice->id}/draft")
            ->assertRedirect();

        $this->assertSame(1, Message::where('invoice_id', $invoice->id)->count());
    }

    public function test_drafting_requires_authentication(): void
    {
        $invoice = $this->overdueInvoice();

        $this->post("/invoices/{$invoice->id}/draft")->assertRedirect('/login');
        $this->assertSame(0, Message::count());
    }
}
