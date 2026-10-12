<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enables pgvector and adds the exemplar section index plus the retrieval context a plan approval stores on its
 * attempt. Rolling back drops the sections, their embeddings and every stored retrieval context; the vector
 * extension stays installed because dropping it could break other databases' objects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        Schema::create('exemplar_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->nullable()->constrained('article_releases')->nullOnDelete();
            $table->string('kind', 30);
            $table->integer('position');
            $table->string('heading')->nullable();
            $table->text('text');
            $table->char('content_hash', 64);
            $table->vector('embedding', dimensions: 1536)->nullable()->index('exemplar_sections_embedding_hnsw');
            $table->timestampTz('embedded_at')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'content_hash']);
        });

        Schema::table('publishing_attempts', function (Blueprint $table): void {
            $table->jsonb('exemplar_context')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('publishing_attempts', function (Blueprint $table): void {
            $table->dropColumn('exemplar_context');
        });

        Schema::dropIfExists('exemplar_sections');
    }
};
