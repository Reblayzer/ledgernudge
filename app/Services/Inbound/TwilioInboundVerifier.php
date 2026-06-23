<?php

namespace App\Services\Inbound;

use App\Services\Inbound\Contracts\InboundSmsVerifier;
use Illuminate\Http\Request;
use Twilio\Security\RequestValidator;

/**
 * Verifies Twilio's X-Twilio-Signature using the official RequestValidator.
 * A missing auth token means we cannot verify, so we reject (secure default).
 */
class TwilioInboundVerifier implements InboundSmsVerifier
{
    public function __construct(private ?string $authToken) {}

    public function verify(Request $request): bool
    {
        if (! $this->authToken) {
            return false;
        }

        $validator = new RequestValidator($this->authToken);

        return $validator->validate(
            $request->header('X-Twilio-Signature', ''),
            $request->fullUrl(),
            $request->post(),
        );
    }
}
