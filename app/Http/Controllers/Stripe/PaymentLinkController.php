<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Stripe\StripeCheckout;
use Illuminate\Http\RedirectResponse;

class PaymentLinkController extends Controller
{
    /**
     * Create (or re-create) a Stripe Checkout payment link for an invoice.
     */
    public function store(Invoice $invoice, StripeCheckout $checkout): RedirectResponse
    {
        $checkout->createForInvoice($invoice);

        return back();
    }
}
