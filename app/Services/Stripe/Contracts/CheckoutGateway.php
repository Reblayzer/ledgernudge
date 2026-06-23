<?php

namespace App\Services\Stripe\Contracts;

use Stripe\Checkout\Session;

/**
 * Thin seam over Stripe's Checkout Session creation so the application code can
 * be unit-tested with an in-memory fake instead of the live Stripe SDK.
 */
interface CheckoutGateway
{
    /** @param array<string, mixed> $params */
    public function createSession(array $params): Session;
}
