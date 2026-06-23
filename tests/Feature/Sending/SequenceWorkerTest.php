<?php

namespace Tests\Feature\Sending;

use App\Enums\InvoiceStatus;
use App\Jobs\DraftDunningMessage;
use App\Models\Invoice;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SequenceWorkerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function invoiceDueDaysAgo(int $days, InvoiceStatus $status = InvoiceStatus::Open): Invoice
    {
        return Invoice::factory()->create([
            'due_date' => now()->subDays($days)->toDateString(),
            'status' => $status,
        ]);
    }

    public function test_enqueues_a_draft_for_the_due_step(): void
    {
        $invoice = $this->invoiceDueDaysAgo(8); // step 7 is due

        $this->artisan('dunning:advance')->assertSuccessful();

        Queue::assertPushed(
            DraftDunningMessage::class,
            fn (DraftDunningMessage $job) => $job->invoice->is($invoice) && $job->step === 7,
        );
    }

    public function test_uses_step_zero_on_the_due_date(): void
    {
        $invoice = $this->invoiceDueDaysAgo(0);

        $this->artisan('dunning:advance')->assertSuccessful();

        Queue::assertPushed(
            DraftDunningMessage::class,
            fn (DraftDunningMessage $job) => $job->invoice->is($invoice) && $job->step === 0,
        );
    }

    public function test_does_not_enqueue_for_an_invoice_not_yet_due(): void
    {
        Invoice::factory()->create([
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => InvoiceStatus::Open,
        ]);

        $this->artisan('dunning:advance')->assertSuccessful();

        Queue::assertNotPushed(DraftDunningMessage::class);
    }

    public function test_does_not_re_enqueue_a_step_already_drafted(): void
    {
        $invoice = $this->invoiceDueDaysAgo(8);
        Message::factory()->create([
            'debtor_id' => $invoice->debtor_id,
            'invoice_id' => $invoice->id,
            'sequence_step' => 7,
        ]);

        $this->artisan('dunning:advance')->assertSuccessful();

        Queue::assertNotPushed(DraftDunningMessage::class);
    }

    public function test_skips_invoices_that_are_no_longer_outstanding(): void
    {
        $this->invoiceDueDaysAgo(20, InvoiceStatus::Paid);

        $this->artisan('dunning:advance')->assertSuccessful();

        Queue::assertNotPushed(DraftDunningMessage::class);
    }

    public function test_skips_invoices_whose_debtor_sequence_is_paused(): void
    {
        $invoice = $this->invoiceDueDaysAgo(8);
        $invoice->debtor->update(['paused_at' => now(), 'pause_reason' => 'dispute']);

        $this->artisan('dunning:advance')->assertSuccessful();

        Queue::assertNotPushed(DraftDunningMessage::class);
    }
}
