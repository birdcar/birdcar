<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->text('slug')->unique();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('idea')->nullable();
            $table->foreignId('working_revision_id')->nullable();
            $table->foreignId('published_release_id')->nullable();
            $table->foreignId('current_attempt_id')->nullable();
            $table->timestampTz('first_published_at')->nullable();
            $table->timestamps();

            $table->index('working_revision_id');
            $table->index('published_release_id');
            $table->index('current_attempt_id');
        });

        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('parent_revision_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origin');
            $table->jsonb('document');
            $table->jsonb('metadata');
            $table->string('content_hash', 64);
            $table->string('client_mutation_id')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'id']);
            $table->unique(['article_id', 'number']);
            $table->unique(['article_id', 'client_mutation_id']);
            $table->foreign(['article_id', 'parent_revision_id'])->references(['article_id', 'id'])->on('article_revisions');
        });

        Schema::create('publishing_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage');
            $table->foreignId('input_version')->nullable();
            $table->jsonb('brief');
            $table->jsonb('angle');
            $table->jsonb('plan');
            $table->jsonb('interview_context');
            $table->timestampTz('paused_at')->nullable();
            $table->text('pause_reason')->nullable();
            $table->timestampTz('parked_at')->nullable();
            $table->text('parked_reason')->nullable();
            $table->timestampTz('abandoned_at')->nullable();
            $table->text('abandoned_reason')->nullable();
            $table->unsignedBigInteger('allowance_nano_usd')->default(5_000_000_000);
            $table->timestamps();

            $table->unique(['article_id', 'id']);
            $table->foreign(['article_id', 'input_version'])->references(['article_id', 'id'])->on('article_revisions');
            $table->index(['stage', 'paused_at']);
            $table->index(['parked_at', 'abandoned_at']);
        });

        Schema::create('article_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->nullable();
            $table->foreignId('revision_id');
            $table->string('origin');
            $table->jsonb('payload');
            $table->string('release_hash', 64);
            $table->string('status');
            $table->timestampTz('scheduled_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'id']);
            $table->unique(['article_id', 'release_hash']);
            $table->foreign(['article_id', 'attempt_id'])->references(['article_id', 'id'])->on('publishing_attempts');
            $table->foreign(['article_id', 'revision_id'])->references(['article_id', 'id'])->on('article_revisions');
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('editorial_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('publishing_attempts')->cascadeOnDelete();
            $table->string('kind');
            $table->string('input_hash', 64);
            $table->foreignId('revision_id')->nullable()->constrained('article_revisions')->nullOnDelete();
            $table->foreignId('release_id')->nullable()->constrained('article_releases')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at');
            $table->timestampTz('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(['attempt_id', 'kind', 'input_hash']);
            $table->index('invalidated_at');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->foreign(['id', 'working_revision_id'])->references(['article_id', 'id'])->on('article_revisions');
            $table->foreign(['id', 'published_release_id'])->references(['article_id', 'id'])->on('article_releases');
            $table->foreign(['id', 'current_attempt_id'])->references(['article_id', 'id'])->on('publishing_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('editorial_approvals');
        Schema::dropIfExists('article_releases');
        Schema::dropIfExists('publishing_attempts');
        Schema::dropIfExists('article_revisions');
        Schema::dropIfExists('articles');
        Schema::enableForeignKeyConstraints();
    }
};
