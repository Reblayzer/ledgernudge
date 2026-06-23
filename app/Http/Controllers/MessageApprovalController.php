<?php

namespace App\Http\Controllers;

use App\Enums\MessageStatus;
use App\Models\Event;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The human-in-the-loop step: an operator edits and/or approves a drafted
 * message before anything is sent. v1 never auto-sends — approval is terminal.
 */
class MessageApprovalController extends Controller
{
    /** Edit a draft's body. Stays pending_approval; a human still approves after. */
    public function update(Request $request, Message $message): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message->update(['body' => $data['body']]);

        Event::create([
            'debtor_id' => $message->debtor_id,
            'invoice_id' => $message->invoice_id,
            'message_id' => $message->id,
            'user_id' => $request->user()->id,
            'type' => Event::MESSAGE_EDITED,
        ]);

        return back();
    }

    /** Approve a pending draft. Idempotent: approving anything else is a no-op. */
    public function approve(Request $request, Message $message): RedirectResponse
    {
        if ($message->status === MessageStatus::PendingApproval) {
            $message->update(['status' => MessageStatus::Approved]);

            Event::create([
                'debtor_id' => $message->debtor_id,
                'invoice_id' => $message->invoice_id,
                'message_id' => $message->id,
                'user_id' => $request->user()->id,
                'type' => Event::MESSAGE_APPROVED,
            ]);
        }

        return back();
    }
}
