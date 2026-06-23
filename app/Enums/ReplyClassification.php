<?php

namespace App\Enums;

/**
 * How Claude classifies an inbound debtor reply. A dispute or stop pauses the
 * sequence and flags a human; unknown also pauses — when the classifier is
 * unsure, v1 deliberately biases toward stopping and asking a human.
 */
enum ReplyClassification: string
{
    case Dispute = 'dispute';
    case PromiseToPay = 'promise_to_pay';
    case Paid = 'paid';
    case Stop = 'stop';
    case Unknown = 'unknown';

    public function pausesSequence(): bool
    {
        return match ($this) {
            self::Dispute, self::Stop, self::Unknown => true,
            self::PromiseToPay, self::Paid => false,
        };
    }

    public static function fromModel(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Unknown;
    }
}
