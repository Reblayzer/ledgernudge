<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency ledger for incoming Stripe webhooks. The primary key is Stripe's
 * own event id, so a duplicate delivery (Stripe retries) collides on insert and
 * is skipped — the webhook is processed at most once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_events', function (Blueprint $table) {
            $table->string('id')->primary(); // Stripe event id, e.g. evt_...
            $table->string('type');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_events');
    }
};
