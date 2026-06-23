<?php

namespace App\Enums;

/**
 * Lifecycle of an invoice. Reconciliation (Sprint 2) moves an invoice to
 * Partial / Paid / Failed based on Stripe webhooks; Void is a manual close.
 */
enum InvoiceStatus: string
{
    case Open = 'open';
    case Partial = 'partial';
    case Paid = 'paid';
    case Failed = 'failed';
    case Void = 'void';

    public function isOutstanding(): bool
    {
        return $this === self::Open || $this === self::Partial;
    }
}
