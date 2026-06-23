<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\ReplyClassification;
use App\Models\Debtor;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds a coherent, offline demo of the whole loop. The three "story" debtors
 * are hand-built (no live Claude / Stripe / Twilio calls) so the inbox tells a
 * complete story out of the box: a draft awaiting approval, a paid happy path,
 * and an inbound dispute that paused the sequence. A handful of random debtors
 * add list volume.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'operator@ledgernudge.test'],
            [
                'name' => 'Demo Operator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $this->seedRandomDebtors();
        $this->seedAwaitingApproval();
        $this->seedPaidHappyPath();
        $this->seedDispute();
    }

    private function seedRandomDebtors(): void
    {
        Debtor::factory()
            ->count(6)
            ->create()
            ->each(function (Debtor $debtor): void {
                Invoice::factory()
                    ->count(fake()->numberBetween(1, 3))
                    ->state(fn () => fake()->boolean(70) ? ['status' => InvoiceStatus::Open] : [])
                    ->for($debtor)
                    ->create()
                    ->each(fn (Invoice $invoice) => $this->event($debtor, Event::INVOICE_CREATED, now()->subDays(20), [
                        'number' => $invoice->number,
                        'amount_cents' => $invoice->amount_cents,
                    ], invoice: $invoice));
            });
    }

    private function seedAwaitingApproval(): void
    {
        $debtor = Debtor::factory()->create([
            'name' => 'Dana Whitfield',
            'company' => 'Northwind Traders',
            'tone_policy' => 'Warm but firm; first-name basis; never threaten legal action.',
        ]);

        $invoice = Invoice::factory()->for($debtor)->create([
            'number' => 'INV-20481',
            'amount_cents' => 245_000,
            'due_date' => now()->subDays(8),
            'status' => InvoiceStatus::Open,
            'payment_url' => 'https://checkout.stripe.test/c/demo_northwind',
            'stripe_checkout_session_id' => 'cs_demo_northwind',
        ]);

        $this->event($debtor, Event::INVOICE_CREATED, now()->subDays(20), ['number' => $invoice->number], $invoice);
        $this->event($debtor, Event::PAYMENT_LINK_CREATED, now()->subDays(2), ['checkout_session_id' => 'cs_demo_northwind'], $invoice);

        $draft = $this->message($debtor, $invoice, MessageDirection::Outbound, MessageChannel::Email, MessageStatus::PendingApproval, now()->subHour(), [
            'body' => 'Hi Dana — just a friendly nudge that invoice INV-20481 for $2,450.00 is now 8 days past due. You can settle it here whenever convenient, and do let me know if anything looks off.',
            'sequence_step' => 7,
            'model' => 'claude-opus-4-8',
            'input_tokens' => 1180,
            'output_tokens' => 58,
        ]);

        $this->event($debtor, Event::MESSAGE_DRAFTED, now()->subHour(), [
            'model' => 'claude-opus-4-8', 'input_tokens' => 1180, 'output_tokens' => 58,
        ], $invoice, $draft);
    }

    private function seedPaidHappyPath(): void
    {
        $debtor = Debtor::factory()->create([
            'name' => 'Marcus Lee',
            'company' => 'Acme Industries',
            'tone_policy' => 'Brief and formal; reference the contract terms; no chit-chat.',
        ]);

        $invoice = Invoice::factory()->for($debtor)->create([
            'number' => 'INV-20455',
            'amount_cents' => 89_900,
            'amount_paid_cents' => 89_900,
            'due_date' => now()->subDays(25),
            'status' => InvoiceStatus::Paid,
            'paid_at' => now()->subDay(),
            'payment_url' => 'https://checkout.stripe.test/c/demo_acme',
            'stripe_checkout_session_id' => 'cs_demo_acme',
            'stripe_payment_intent_id' => 'pi_demo_acme',
        ]);

        $this->event($debtor, Event::INVOICE_CREATED, now()->subDays(30), ['number' => $invoice->number], $invoice);
        $this->event($debtor, Event::PAYMENT_LINK_CREATED, now()->subDays(22), ['checkout_session_id' => 'cs_demo_acme'], $invoice);

        $sent = $this->message($debtor, $invoice, MessageDirection::Outbound, MessageChannel::Email, MessageStatus::Sent, now()->subDays(22), [
            'body' => 'Per our agreement, invoice INV-20455 ($899.00) is now due. Payment can be completed at the link provided. Thank you.',
            'sequence_step' => 0,
            'model' => 'claude-opus-4-8',
            'input_tokens' => 1090,
            'output_tokens' => 41,
        ]);

        $this->event($debtor, Event::MESSAGE_DRAFTED, now()->subDays(22), ['model' => 'claude-opus-4-8'], $invoice, $sent);
        $this->event($debtor, Event::MESSAGE_APPROVED, now()->subDays(22), [], $invoice, $sent);
        $this->event($debtor, Event::MESSAGE_SENT, now()->subDays(22), ['channel' => 'email'], $invoice, $sent);
        $this->event($debtor, Event::PAYMENT_SUCCEEDED, now()->subDay(), [
            'amount_received_cents' => 89_900, 'payment_intent' => 'pi_demo_acme',
        ], $invoice, null);
    }

    private function seedDispute(): void
    {
        $debtor = Debtor::factory()->create([
            'name' => 'Priya Raman',
            'company' => 'Globex Corp',
            'phone' => '+15557654321',
            'tone_policy' => 'Professional, firm, and respectful.',
            'paused_at' => now()->subHours(2),
            'pause_reason' => ReplyClassification::Dispute->value,
        ]);

        $invoice = Invoice::factory()->for($debtor)->create([
            'number' => 'INV-20399',
            'amount_cents' => 1_520_000,
            'due_date' => now()->subDays(12),
            'status' => InvoiceStatus::Open,
            'payment_url' => 'https://checkout.stripe.test/c/demo_globex',
            'stripe_checkout_session_id' => 'cs_demo_globex',
        ]);

        $this->event($debtor, Event::INVOICE_CREATED, now()->subDays(15), ['number' => $invoice->number], $invoice);

        $sent = $this->message($debtor, $invoice, MessageDirection::Outbound, MessageChannel::Sms, MessageStatus::Sent, now()->subDays(5), [
            'body' => 'Reminder: invoice INV-20399 ($15,200.00) is past due. Reply or pay at the link we emailed. — LedgerNudge',
            'sequence_step' => 0,
            'model' => 'claude-opus-4-8',
            'input_tokens' => 1120,
            'output_tokens' => 47,
        ]);
        $this->event($debtor, Event::MESSAGE_SENT, now()->subDays(5), ['channel' => 'sms'], $invoice, $sent);

        $reply = $this->message($debtor, null, MessageDirection::Inbound, MessageChannel::Sms, MessageStatus::Received, now()->subHours(2), [
            'body' => 'This is wrong — we cancelled this order in writing back in April. We are not paying this.',
            'classification' => ReplyClassification::Dispute->value,
        ]);

        $this->event($debtor, Event::REPLY_RECEIVED, now()->subHours(2), ['channel' => 'sms'], null, $reply);
        $this->event($debtor, Event::REPLY_CLASSIFIED, now()->subHours(2), [
            'category' => ReplyClassification::Dispute->value,
            'rationale' => 'The debtor states the order was cancelled in writing and refuses payment.',
            'confidence' => 0.93,
        ], null, $reply);
        $this->event($debtor, Event::SEQUENCE_PAUSED, now()->subHours(2), [
            'reason' => ReplyClassification::Dispute->value,
        ], null, $reply);
    }

    private function message(
        Debtor $debtor,
        ?Invoice $invoice,
        MessageDirection $direction,
        MessageChannel $channel,
        MessageStatus $status,
        \DateTimeInterface $at,
        array $attributes,
    ): Message {
        $message = $debtor->messages()->create([
            'invoice_id' => $invoice?->id,
            'direction' => $direction,
            'channel' => $channel,
            'status' => $status,
            ...$attributes,
        ]);

        // Backdate so the thread and event log read as a realistic timeline.
        $message->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

        return $message;
    }

    private function event(
        Debtor $debtor,
        string $type,
        \DateTimeInterface $at,
        array $data = [],
        ?Invoice $invoice = null,
        ?Message $message = null,
    ): void {
        Event::create([
            'debtor_id' => $debtor->id,
            'invoice_id' => $invoice?->id,
            'message_id' => $message?->id,
            'type' => $type,
            'data' => $data,
        ])->forceFill(['created_at' => $at])->saveQuietly();
    }
}
