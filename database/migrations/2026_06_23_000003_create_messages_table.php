<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound dunning messages and inbound debtor replies. A message belongs to a
 * debtor and optionally to a specific invoice. Drafting/approval/send columns
 * (Claude usage, classification) are layered in by later sprints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debtor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction'); // outbound | inbound
            $table->string('channel');   // email | sms
            $table->string('status');    // see App\Enums\MessageStatus
            $table->text('body')->nullable();
            $table->timestamps();

            $table->index(['debtor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
