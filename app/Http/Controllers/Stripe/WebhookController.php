<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Controller;
use App\Models\StripeEvent;
use App\Services\Stripe\WebhookReconciler;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Receives Stripe webhooks. Verifies the signature, records the event id for
 * idempotency (duplicate deliveries are acknowledged but not re-applied), then
 * reconciles the payment against the invoice.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, WebhookReconciler $reconciler): Response
    {
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                (string) $secret,
            );
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            return response('Invalid signature', 400);
        }

        try {
            StripeEvent::create([
                'id' => $event->id,
                'type' => $event->type,
                'payload' => $event->toArray(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Already seen this event id — acknowledge so Stripe stops retrying.
            return response('Duplicate', 200);
        }

        $reconciler->handle($event);

        return response('ok', 200);
    }
}
