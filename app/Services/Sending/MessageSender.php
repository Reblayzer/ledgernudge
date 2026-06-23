<?php

namespace App\Services\Sending;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Mail\DunningMail;
use App\Models\Event;
use App\Models\Message;
use App\Services\Sending\Contracts\SmsGateway;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Delivers an approved message over its channel (email or SMS), then records the
 * outcome: status Sent + message.sent on success, status Failed + message.send_failed
 * on any error. Delivery failures are swallowed here (logged as an event) so a
 * single bad address doesn't crash the queue worker.
 */
class MessageSender
{
    public function __construct(private SmsGateway $sms) {}

    public function send(Message $message): void
    {
        $message->loadMissing('debtor', 'invoice');

        try {
            match ($message->channel) {
                MessageChannel::Email => $this->sendEmail($message),
                MessageChannel::Sms => $this->sendSms($message),
            };

            $message->update(['status' => MessageStatus::Sent]);
            $this->log($message, Event::MESSAGE_SENT, ['channel' => $message->channel->value]);
        } catch (Throwable $e) {
            $message->update(['status' => MessageStatus::Failed]);
            $this->log($message, Event::MESSAGE_SEND_FAILED, [
                'channel' => $message->channel->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendEmail(Message $message): void
    {
        $email = $message->debtor->email;

        if (! $email) {
            throw new RuntimeException('Debtor has no email address.');
        }

        Mail::to($email)->send(new DunningMail($message));
    }

    private function sendSms(Message $message): void
    {
        $phone = $message->debtor->phone;

        if (! $phone) {
            throw new RuntimeException('Debtor has no phone number.');
        }

        $this->sms->send($phone, (string) $message->body);
    }

    private function log(Message $message, string $type, array $data): void
    {
        Event::create([
            'debtor_id' => $message->debtor_id,
            'invoice_id' => $message->invoice_id,
            'message_id' => $message->id,
            'type' => $type,
            'data' => $data,
        ]);
    }
}
