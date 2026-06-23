<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A debtor is a person/business that owes money on one or more invoices.
 * In v1 (single-tenant) there is one operator org; multi-tenant brand
 * isolation is intentionally out of scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debtors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            // Client's own reference for this customer; used to de-dup CSV imports.
            $table->string('external_ref')->nullable()->unique();
            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debtors');
    }
};
