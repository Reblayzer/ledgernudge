<?php

namespace Tests\Feature\Dunning;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Jobs\SendMessage;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MessageApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Sending is queued; fake it so approval doesn't actually deliver here.
        Queue::fake();
    }

    private function pendingDraft(): Message
    {
        $invoice = Invoice::factory()->create();

        return Message::factory()->create([
            'debtor_id' => $invoice->debtor_id,
            'invoice_id' => $invoice->id,
            'direction' => MessageDirection::Outbound,
            'status' => MessageStatus::PendingApproval,
            'body' => 'Draft body the operator will review.',
        ]);
    }

    public function test_operator_can_approve_a_pending_message(): void
    {
        $message = $this->pendingDraft();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/messages/{$message->id}/approve")
            ->assertRedirect();

        $this->assertSame(MessageStatus::Approved, $message->refresh()->status);
        $this->assertDatabaseHas('events', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'type' => Event::MESSAGE_APPROVED,
        ]);
    }

    public function test_operator_can_edit_a_draft_before_approving(): void
    {
        $message = $this->pendingDraft();

        $this->actingAs(User::factory()->create())
            ->patch("/messages/{$message->id}", ['body' => 'Edited, gentler wording.'])
            ->assertRedirect();

        $message->refresh();
        $this->assertSame('Edited, gentler wording.', $message->body);
        // Editing does not approve — a human still has to approve afterwards.
        $this->assertSame(MessageStatus::PendingApproval, $message->status);
        $this->assertDatabaseHas('events', [
            'message_id' => $message->id,
            'type' => Event::MESSAGE_EDITED,
        ]);
    }

    public function test_approving_enqueues_exactly_one_send(): void
    {
        $message = $this->pendingDraft();

        $this->actingAs(User::factory()->create())
            ->post("/messages/{$message->id}/approve");

        // A human approval is what authorizes sending — never an auto-send.
        Queue::assertPushed(
            SendMessage::class,
            fn (SendMessage $job) => $job->message->is($message),
        );
        Queue::assertPushed(SendMessage::class, 1);
    }

    public function test_editing_a_draft_does_not_enqueue_a_send(): void
    {
        $message = $this->pendingDraft();

        $this->actingAs(User::factory()->create())
            ->patch("/messages/{$message->id}", ['body' => 'Edited copy.']);

        Queue::assertNotPushed(SendMessage::class);
    }

    public function test_approval_and_editing_require_authentication(): void
    {
        $message = $this->pendingDraft();

        $this->post("/messages/{$message->id}/approve")->assertRedirect('/login');
        $this->patch("/messages/{$message->id}", ['body' => 'x'])->assertRedirect('/login');
        $this->assertSame(MessageStatus::PendingApproval, $message->refresh()->status);
    }
}
