<?php

namespace App\Mail;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email body of an approved dunning message. The body text is Claude's
 * approved draft; the subject references the invoice.
 */
class DunningMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Message $message) {}

    public function envelope(): Envelope
    {
        $number = $this->message->invoice?->number;

        return new Envelope(
            subject: $number ? "Reminder: invoice {$number}" : 'Payment reminder',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.dunning',
            with: ['body' => $this->message->body],
        );
    }
}
