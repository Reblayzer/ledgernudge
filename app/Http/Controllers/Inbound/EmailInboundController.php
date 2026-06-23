<?php

namespace App\Http\Controllers\Inbound;

use App\Enums\MessageChannel;
use App\Http\Controllers\Controller;
use App\Models\Debtor;
use App\Services\Inbound\InboundReplyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Simplified inbound email route: accepts a parsed {from, body} payload (as a
 * mail provider's inbound-parse webhook would post), matches the sender to a
 * debtor by email, and records + classifies the reply. A production deployment
 * would additionally verify the provider's signature — out of scope for v1.
 */
class EmailInboundController extends Controller
{
    public function __invoke(Request $request, InboundReplyService $replies): Response
    {
        $data = $request->validate([
            'from' => ['required', 'email'],
            'body' => ['required', 'string'],
        ]);

        $debtor = Debtor::where('email', $data['from'])->first();

        if ($debtor) {
            $replies->record($debtor, MessageChannel::Email, $data['body']);
        }

        return response()->noContent();
    }
}
