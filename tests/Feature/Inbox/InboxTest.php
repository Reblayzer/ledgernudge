<?php

namespace Tests\Feature\Inbox;

use App\Enums\InvoiceStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Debtor;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_requires_authentication(): void
    {
        $this->get('/inbox')->assertRedirect('/login');
    }

    public function test_index_lists_debtors_with_status(): void
    {
        $paused = Debtor::factory()->create(['paused_at' => now(), 'pause_reason' => 'dispute']);
        Invoice::factory()->for($paused)->create(['amount_cents' => 100_00, 'status' => InvoiceStatus::Open]);
        Debtor::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get('/inbox')
            ->assertInertia(fn (Assert $page) => $page
                ->component('inbox/index')
                ->has('debtors', 2)
                ->where('debtors.0.paused', true)            // paused sorts first
                ->where('debtors.0.outstanding_cents', 100_00)
            );
    }

    public function test_show_returns_thread_events_and_invoices(): void
    {
        $debtor = Debtor::factory()->create();
        $invoice = Invoice::factory()->for($debtor)->create(['payment_url' => 'https://pay.test/abc']);
        Message::factory()->create([
            'debtor_id' => $debtor->id,
            'invoice_id' => $invoice->id,
            'direction' => MessageDirection::Outbound,
            'status' => MessageStatus::PendingApproval,
        ]);
        Event::factory()->create(['debtor_id' => $debtor->id, 'type' => Event::INVOICE_CREATED]);

        $this->actingAs(User::factory()->create())
            ->get("/inbox/{$debtor->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('inbox/show')
                ->where('debtor.id', $debtor->id)
                ->has('invoices', 1)
                ->where('invoices.0.payment_url', 'https://pay.test/abc')
                ->has('invoices.0.next_step')
                ->has('thread', 1)
                ->where('thread.0.can_approve', true)
                ->has('events', 1)
            );
    }
}
