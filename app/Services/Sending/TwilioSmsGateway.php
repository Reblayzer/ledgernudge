<?php

namespace App\Services\Sending;

use App\Services\Sending\Contracts\SmsGateway;
use Twilio\Rest\Client;

/**
 * Twilio-backed SMS gateway. The Twilio client is built lazily inside send() so
 * that merely constructing this gateway (e.g. for an email-only send, since
 * MessageSender depends on it) never requires Twilio credentials.
 */
class TwilioSmsGateway implements SmsGateway
{
    public function __construct(
        private ?string $sid,
        private ?string $token,
        private ?string $from,
    ) {}

    public function send(string $to, string $body): string
    {
        $client = new Client($this->sid, $this->token);

        $message = $client->messages->create($to, [
            'from' => $this->from,
            'body' => $body,
        ]);

        return $message->sid;
    }
}
