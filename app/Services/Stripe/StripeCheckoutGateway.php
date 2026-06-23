<?php

namespace App\Services\Stripe;

use App\Services\Stripe\Contracts\CheckoutGateway;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class StripeCheckoutGateway implements CheckoutGateway
{
    public function __construct(private StripeClient $stripe) {}

    public function createSession(array $params): Session
    {
        return $this->stripe->checkout->sessions->create($params);
    }
}
