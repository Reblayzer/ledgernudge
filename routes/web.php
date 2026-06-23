<?php

use App\Http\Controllers\InvoiceDraftController;
use App\Http\Controllers\MessageApprovalController;
use App\Http\Controllers\Stripe\PaymentLinkController;
use App\Http\Controllers\Stripe\WebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::post('invoices/{invoice}/payment-link', [PaymentLinkController::class, 'store'])
        ->name('invoices.payment-link.store');

    // Claude drafting + human-in-the-loop approval (never auto-sends in v1).
    Route::post('invoices/{invoice}/draft', [InvoiceDraftController::class, 'store'])
        ->name('invoices.draft.store');
    Route::patch('messages/{message}', [MessageApprovalController::class, 'update'])
        ->name('messages.update');
    Route::post('messages/{message}/approve', [MessageApprovalController::class, 'approve'])
        ->name('messages.approve');
});

// Stateless: authenticated by Stripe signature, not a session. CSRF-exempt in bootstrap/app.php.
Route::post('stripe/webhook', WebhookController::class)->name('stripe.webhook');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
