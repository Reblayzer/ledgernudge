<?php

namespace Tests\Feature\Stripe;

use App\Enums\InvoiceStatus;
use App\Models\Event;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => $this->secret]);
    }

    /**
     * Build a Stripe-signed POST to the webhook, mirroring Stripe's
     * t=timestamp,v1=HMAC-SHA256(timestamp.payload) scheme so the real
     * signature verification runs.
     */
    private function postSigned(array $event, ?string $signature = null)
    {
        $payload = json_encode($event);
        $timestamp = time();
        $computed = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->secret);
        $header = $signature ?? "t={$timestamp},v1={$computed}";

        return $this->call(
            'POST', '/stripe/webhook', [], [], [],
            ['HTTP_STRIPE_SIGNATURE' => $header, 'CONTENT_TYPE' => 'application/json'],
            $payload,
        );
    }

    private function checkoutCompleted(Invoice $invoice, string $eventId = 'evt_paid_1'): array
    {
        return [
            'id' => $eventId,
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_1',
                'object' => 'checkout.session',
                'payment_status' => 'paid',
                'amount_total' => $invoice->amount_cents,
                'currency' => $invoice->currency,
                'payment_intent' => 'pi_test_1',
                'client_reference_id' => (string) $invoice->id,
                'metadata' => ['invoice_id' => (string) $invoice->id],
            ]],
        ];
    }

    public function test_valid_checkout_completed_marks_invoice_paid_and_logs_event(): void
    {
        $invoice = Invoice::factory()->create([
            'amount_cents' => 125_00,
            'status' => InvoiceStatus::Open,
        ]);

        $response = $this->postSigned($this->checkoutCompleted($invoice));

        $response->assertOk();
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(125_00, $invoice->amount_paid_cents);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame('pi_test_1', $invoice->stripe_payment_intent_id);

        $this->assertDatabaseHas('events', [
            'invoice_id' => $invoice->id,
            'type' => Event::PAYMENT_SUCCEEDED,
        ]);
        $this->assertDatabaseHas('stripe_events', ['id' => 'evt_paid_1']);
    }

    public function test_invalid_signature_is_rejected_and_changes_nothing(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Open]);

        $response = $this->postSigned(
            $this->checkoutCompleted($invoice),
            signature: 't=123,v1=deadbeef',
        );

        $response->assertStatus(400);
        $this->assertSame(InvoiceStatus::Open, $invoice->refresh()->status);
        $this->assertDatabaseCount('stripe_events', 0);
    }

    public function test_duplicate_webhook_is_processed_only_once(): void
    {
        $invoice = Invoice::factory()->create([
            'amount_cents' => 125_00,
            'status' => InvoiceStatus::Open,
        ]);
        $event = $this->checkoutCompleted($invoice);

        $this->postSigned($event)->assertOk();
        $this->postSigned($event)->assertOk(); // same event id again

        $this->assertDatabaseCount('stripe_events', 1);
        $this->assertSame(1, Event::where('type', Event::PAYMENT_SUCCEEDED)->count());
    }

    public function test_partial_payment_marks_invoice_partial(): void
    {
        $invoice = Invoice::factory()->create([
            'amount_cents' => 100_00,
            'status' => InvoiceStatus::Open,
        ]);

        $event = [
            'id' => 'evt_partial_1',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_partial_1',
                'object' => 'payment_intent',
                'amount' => 100_00,
                'amount_received' => 40_00,
                'metadata' => ['invoice_id' => (string) $invoice->id],
            ]],
        ];

        $this->postSigned($event)->assertOk();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Partial, $invoice->status);
        $this->assertSame(40_00, $invoice->amount_paid_cents);
        $this->assertNull($invoice->paid_at);
        $this->assertDatabaseHas('events', [
            'invoice_id' => $invoice->id,
            'type' => Event::PAYMENT_PARTIAL,
        ]);
    }

    public function test_failed_payment_marks_invoice_failed(): void
    {
        $invoice = Invoice::factory()->create([
            'amount_cents' => 100_00,
            'status' => InvoiceStatus::Open,
        ]);

        $event = [
            'id' => 'evt_failed_1',
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => [
                'id' => 'pi_failed_1',
                'object' => 'payment_intent',
                'amount' => 100_00,
                'amount_received' => 0,
                'metadata' => ['invoice_id' => (string) $invoice->id],
            ]],
        ];

        $this->postSigned($event)->assertOk();

        $this->assertSame(InvoiceStatus::Failed, $invoice->refresh()->status);
        $this->assertDatabaseHas('events', [
            'invoice_id' => $invoice->id,
            'type' => Event::PAYMENT_FAILED,
        ]);
    }

    public function test_unknown_invoice_is_acknowledged_without_error(): void
    {
        // Stripe still expects a 2xx so it stops retrying; we just no-op.
        $event = [
            'id' => 'evt_orphan_1',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_orphan',
                'object' => 'payment_intent',
                'amount' => 5000,
                'amount_received' => 5000,
                'metadata' => ['invoice_id' => '999999'],
            ]],
        ];

        $this->postSigned($event)->assertOk();
        $this->assertSame(0, Event::whereIn('type', [
            Event::PAYMENT_SUCCEEDED, Event::PAYMENT_PARTIAL,
        ])->count());
    }
}
