<?php

use App\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices owed by a debtor. Money is stored in integer minor units (cents)
 * to avoid floating-point rounding. Stripe-specific columns are added in
 * Sprint 2 when the payment-link slice lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debtor_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('amount_paid_cents')->default(0);
            $table->string('currency', 3)->default('usd');
            $table->date('due_date');
            $table->string('status')->default(InvoiceStatus::Open->value);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
