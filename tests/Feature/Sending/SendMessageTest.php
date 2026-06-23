<?php

namespace Tests\Feature\Sending;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Jobs\SendMessage;
use App\Mail\DunningMail;
use App\Models\Debtor;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Message;
use App\Services\Sending\Contracts\SmsGateway;
use App\Services\Sending\MessageSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase;

    private function approvedMessage(MessageChannel $channel, array $debtorAttrs = []): Message
    {
        $debtor = Debtor::factory()->create($debtorAttrs + [
            'email' => 'ap@acme.test',
            'phone' => '+15551230000',
        ]);
        $invoice = Invoice::factory()->for($debtor)->create();

        return Message::factory()->create([
            'debtor_id' => $debtor->id,
            'invoice_id' => $invoice->id,
            'channel' => $channel,
            'status' => MessageStatus::Approved,
            'body' => 'Please pay invoice INV-1.',
        ]);
    }

    private function fakeSms(): object
    {
        $gateway = new class implements SmsGateway
        {
            public ?string $to = null;

            public ?string $body = null;

            public int $calls = 0;

            public function send(string $to, string $body): string
            {
                $this->to = $to;
                $this->body = $body;
                $this->calls++;

                return 'SM_test_123';
            }
        };
        $this->app->instance(SmsGateway::class, $gateway);

        return $gateway;
    }

    public function test_sends_an_approved_email_and_marks_it_sent(): void
    {
        Mail::fake();
        $this->fakeSms();
        $message = $this->approvedMessage(MessageChannel::Email);

        app(MessageSender::class)->send($message);

        Mail::assertSent(DunningMail::class);
        $this->assertSame(MessageStatus::Sent, $message->refresh()->status);
        $this->assertDatabaseHas('events', [
            'message_id' => $message->id,
            'type' => Event::MESSAGE_SENT,
        ]);
    }

    public function test_sends_an_approved_sms_through_the_gateway(): void
    {
        $gateway = $this->fakeSms();
        $message = $this->approvedMessage(MessageChannel::Sms);

        app(MessageSender::class)->send($message);

        $this->assertSame(1, $gateway->calls);
        $this->assertSame('+15551230000', $gateway->to);
        $this->assertSame(MessageStatus::Sent, $message->refresh()->status);
        $this->assertDatabaseHas('events', [
            'message_id' => $message->id,
            'type' => Event::MESSAGE_SENT,
        ]);
    }

    public function test_marks_failed_when_the_debtor_has_no_destination(): void
    {
        Mail::fake();
        $this->fakeSms();
        $message = $this->approvedMessage(MessageChannel::Email, ['email' => null]);

        app(MessageSender::class)->send($message);

        Mail::assertNothingSent();
        $this->assertSame(MessageStatus::Failed, $message->refresh()->status);
        $this->assertDatabaseHas('events', [
            'message_id' => $message->id,
            'type' => Event::MESSAGE_SEND_FAILED,
        ]);
    }

    public function test_send_job_ignores_a_message_that_is_not_approved(): void
    {
        Mail::fake();
        $this->fakeSms();
        $message = $this->approvedMessage(MessageChannel::Email);
        $message->update(['status' => MessageStatus::PendingApproval]);

        SendMessage::dispatchSync($message);

        Mail::assertNothingSent();
        $this->assertSame(MessageStatus::PendingApproval, $message->refresh()->status);
        $this->assertSame(0, Event::where('type', Event::MESSAGE_SENT)->count());
    }
}
