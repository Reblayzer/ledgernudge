<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4: which dunning sequence step (days overdue: 0 / 7 / 14) a message
 * belongs to, so the scheduled worker enqueues each step at most once per
 * invoice. Null for ad-hoc drafts not tied to the sequence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedSmallInteger('sequence_step')->nullable()->after('output_tokens');
            $table->index(['invoice_id', 'sequence_step']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['invoice_id', 'sequence_step']);
            $table->dropColumn('sequence_step');
        });
    }
};
