<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Dunning\DunningDraftService;
use Illuminate\Http\RedirectResponse;

class InvoiceDraftController extends Controller
{
    /**
     * Ask Claude to draft the next dunning message for an invoice. The draft is
     * saved as pending_approval for an operator to review — never sent.
     */
    public function store(Invoice $invoice, DunningDraftService $drafts): RedirectResponse
    {
        $drafts->draftFor($invoice);

        return back();
    }
}
