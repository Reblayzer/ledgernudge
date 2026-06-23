<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3: record which model drafted a message and what it cost in tokens.
 * Null for messages that were not AI-drafted (e.g. inbound replies).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('model')->nullable()->after('body');
            $table->unsignedInteger('input_tokens')->nullable()->after('model');
            $table->unsignedInteger('output_tokens')->nullable()->after('input_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['model', 'input_tokens', 'output_tokens']);
        });
    }
};
