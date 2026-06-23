<?php

namespace App\Http\Controllers\Inbound;

use App\Enums\MessageChannel;
use App\Http\Controllers\Controller;
use App\Models\Debtor;
use App\Services\Inbound\Contracts\InboundSmsVerifier;
use App\Services\Inbound\InboundReplyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Twilio inbound SMS webhook. Verifies the signature, matches the sender to a
 * debtor by phone, and records + classifies the reply. Returns empty TwiML.
 */
class TwilioInboundController extends Controller
{
    public function __invoke(
        Request $request,
        InboundSmsVerifier $verifier,
        InboundReplyService $replies,
    ): Response {
        abort_unless($verifier->verify($request), HttpResponse::HTTP_FORBIDDEN);

        $debtor = Debtor::where('phone', $request->input('From'))->first();

        if ($debtor) {
            $replies->record($debtor, MessageChannel::Sms, (string) $request->input('Body'));
        }

        return response('<Response></Response>', HttpResponse::HTTP_OK)
            ->header('Content-Type', 'text/xml');
    }
}
