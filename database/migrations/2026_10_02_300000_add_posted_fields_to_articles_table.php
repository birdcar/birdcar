<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A distribution kit piece records when the owner posted it by hand and, optionally, where. Rolling back drops both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->timestampTz('posted_at')->nullable();
            $table->string('posted_url', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn(['posted_at', 'posted_url']);
        });
    }
};
