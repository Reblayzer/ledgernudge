<?php

namespace App\Services\Sending\Contracts;

/**
 * Seam over SMS delivery so the send pipeline can be tested without Twilio.
 * Returns the provider's message id (the Twilio SID) on success.
 */
interface SmsGateway
{
    public function send(string $to, string $body): string;
}
