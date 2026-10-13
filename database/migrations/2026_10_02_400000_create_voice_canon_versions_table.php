<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable voice canon versions. The active version is the row activated most recently, so activation timestamps keep
 * microseconds. Rolling back drops every Admin edit; the canon falls back to the repo files.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_canon_versions', function (Blueprint $table): void {
            $table->id();
            $table->char('hash', 64)->unique();
            $table->string('source', 10);
            $table->foreignId('parent_version_id')->nullable()->constrained('voice_canon_versions')->nullOnDelete();
            $table->jsonb('files');
            $table->jsonb('external_hashes');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('activated_at', 6)->nullable()->index();
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_canon_versions');
    }
};
