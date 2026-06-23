<?php

namespace App\Enums;

/**
 * Lifecycle of an outbound message. Claude drafts to PendingApproval (Sprint 3);
 * a human approves; the queue worker sends (Sprint 4). Inbound replies are stored
 * as Received (Sprint 5). Failed captures a delivery error from email/SMS.
 */
enum MessageStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Received = 'received';
}
