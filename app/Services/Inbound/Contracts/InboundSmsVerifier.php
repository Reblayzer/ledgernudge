<?php

namespace App\Services\Inbound\Contracts;

use Illuminate\Http\Request;

/**
 * Verifies that an inbound SMS webhook genuinely came from the provider.
 * Seamed so the controller can be tested without recomputing a real signature.
 */
interface InboundSmsVerifier
{
    public function verify(Request $request): bool;
}
