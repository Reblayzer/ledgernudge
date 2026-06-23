<?php

namespace Tests\Feature\Models;

use App\Enums\InvoiceStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Debtor;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_debtor_has_many_invoices_messages_and_events(): void
    {
        $debtor = Debtor::factory()
            ->has(Invoice::factory()->count(2))
            ->has(Message::factory()->count(3))
            ->has(Event::factory()->count(4))
            ->create();

        $this->assertInstanceOf(Collection::class, $debtor->invoices);
        $this->assertCount(2, $debtor->invoices);
        $this->assertCount(3, $debtor->messages);
        $this->assertCount(4, $debtor->events);
    }

    public function test_invoice_belongs_to_debtor_and_casts_status_enum(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Open,
            'amount_cents' => 125_00,
            'currency' => 'usd',
        ]);

        $this->assertInstanceOf(Debtor::class, $invoice->debtor);
        $this->assertInstanceOf(InvoiceStatus::class, $invoice->status);
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame(125_00, $invoice->amount_cents);
    }

    public function test_invoice_tracks_outstanding_balance_in_cents(): void
    {
        $invoice = Invoice::factory()->create([
            'amount_cents' => 100_00,
            'amount_paid_cents' => 30_00,
        ]);

        $this->assertSame(70_00, $invoice->outstanding_cents);
    }

    public function test_message_belongs_to_debtor_and_invoice_and_casts_enums(): void
    {
        $invoice = Invoice::factory()->create();
        $message = Message::factory()->create([
            'debtor_id' => $invoice->debtor_id,
            'invoice_id' => $invoice->id,
            'direction' => MessageDirection::Outbound,
            'channel' => MessageChannel::Email,
            'status' => MessageStatus::PendingApproval,
        ]);

        $this->assertInstanceOf(Debtor::class, $message->debtor);
        $this->assertInstanceOf(Invoice::class, $message->invoice);
        $this->assertSame(MessageDirection::Outbound, $message->direction);
        $this->assertSame(MessageChannel::Email, $message->channel);
        $this->assertSame(MessageStatus::PendingApproval, $message->status);
    }

    public function test_event_relates_to_debtor_invoice_message_and_user(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create();
        $message = Message::factory()->create([
            'debtor_id' => $invoice->debtor_id,
            'invoice_id' => $invoice->id,
        ]);

        $event = Event::factory()->create([
            'debtor_id' => $invoice->debtor_id,
            'invoice_id' => $invoice->id,
            'message_id' => $message->id,
            'user_id' => $user->id,
            'type' => Event::INVOICE_CREATED,
            'data' => ['amount_cents' => 100_00],
        ]);

        $this->assertInstanceOf(Debtor::class, $event->debtor);
        $this->assertInstanceOf(Invoice::class, $event->invoice);
        $this->assertInstanceOf(Message::class, $event->message);
        $this->assertInstanceOf(User::class, $event->user);
        $this->assertSame(['amount_cents' => 100_00], $event->data);
    }

    public function test_events_are_append_only_with_no_updated_at(): void
    {
        // The append-only event log only manages a created_at timestamp.
        $this->assertNull(Event::UPDATED_AT);

        $event = Event::factory()->create();

        $this->assertNotNull($event->created_at);
        $this->assertArrayNotHasKey('updated_at', $event->getAttributes());
    }

    public function test_demo_seeder_creates_a_coherent_dataset(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThan(0, Debtor::count());
        $this->assertGreaterThan(0, Invoice::count());
        $this->assertGreaterThan(0, Event::count());

        // Every invoice is attached to a real debtor (no orphans).
        $this->assertSame(0, Invoice::whereDoesntHave('debtor')->count());
    }
}
