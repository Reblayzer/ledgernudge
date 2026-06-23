<?php

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
});

// Stateless: authenticated by Stripe signature, not a session. CSRF-exempt in bootstrap/app.php.
Route::post('stripe/webhook', WebhookController::class)->name('stripe.webhook');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
