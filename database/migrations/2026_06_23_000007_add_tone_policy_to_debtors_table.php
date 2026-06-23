<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3: the per-client tone policy that steers Claude's drafting — a free-text
 * field, not a rules engine. In a multi-tenant build this would live on a clients
 * table; v1 is single-tenant, so it sits on the debtor (the account being dunned).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debtors', function (Blueprint $table) {
            $table->text('tone_policy')->nullable()->after('external_ref');
        });
    }

    public function down(): void
    {
        Schema::table('debtors', function (Blueprint $table) {
            $table->dropColumn('tone_policy');
        });
    }
};
