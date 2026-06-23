<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 5: a paused debtor sequence (set when a reply is classified as a
 * dispute / stop / unknown) and the classification stamped on the inbound
 * message for quick display in the operator inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debtors', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('tone_policy');
            $table->string('pause_reason')->nullable()->after('paused_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('classification')->nullable()->after('sequence_step');
        });
    }

    public function down(): void
    {
        Schema::table('debtors', function (Blueprint $table) {
            $table->dropColumn(['paused_at', 'pause_reason']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('classification');
        });
    }
};
