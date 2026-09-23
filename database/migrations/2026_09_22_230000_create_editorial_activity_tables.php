<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publishing_attempts', function (Blueprint $table): void {
            $table->unsignedInteger('review_cycle')->default(1)->after('allowance_nano_usd');
            $table->boolean('recheck_used')->default(false)->after('review_cycle');
            $table->jsonb('allowance_changes')->nullable()->after('recheck_used');
        });

        Schema::create('editorial_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->constrained('publishing_attempts')->cascadeOnDelete();
            $table->foreignId('initiating_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind');
            $table->string('status');
            $table->string('stage');
            $table->foreignId('input_version')->nullable();
            $table->foreignId('revision_id')->nullable();
            $table->string('revision_hash', 64)->nullable();
            $table->unsignedInteger('review_cycle')->default(1);
            $table->string('batch_key')->nullable();
            $table->string('idempotency_key')->unique();
            $table->unsignedInteger('prompt_version')->default(1);
            $table->string('prompt_hash', 64);
            $table->jsonb('input')->nullable();
            $table->jsonb('model_snapshot')->nullable();
            $table->jsonb('response')->nullable();
            $table->jsonb('proposal')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->timestampTz('available_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('paused_at')->nullable();
            $table->text('pause_reason')->nullable();
            $table->text('error_reason')->nullable();
            $table->string('generation_id')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'id']);
            $table->foreign(['article_id', 'attempt_id'])->references(['article_id', 'id'])->on('publishing_attempts')->cascadeOnDelete();
            $table->foreign(['article_id', 'input_version'])->references(['article_id', 'id'])->on('article_revisions')->nullOnDelete();
            $table->foreign(['article_id', 'revision_id'])->references(['article_id', 'id'])->on('article_revisions')->nullOnDelete();
            $table->index(['status', 'available_at']);
            $table->index(['attempt_id', 'review_cycle', 'kind']);
        });

        Schema::create('agent_budget_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('publishing_attempts')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('editorial_activities')->nullOnDelete();
            $table->unsignedInteger('call_number');
            $table->string('local_call_key')->unique();
            $table->unsignedBigInteger('reserved_nano_usd');
            $table->unsignedBigInteger('actual_nano_usd')->nullable();
            $table->string('state');
            $table->jsonb('price_snapshot')->nullable();
            $table->jsonb('request_bound')->nullable();
            $table->string('provider_generation_id')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->text('retained_unknown_reason')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'call_number']);
            $table->index(['attempt_id', 'state']);
        });

        Schema::create('evidence_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->constrained('publishing_attempts')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('editorial_activities')->nullOnDelete();
            $table->string('source_type');
            $table->text('url')->nullable();
            $table->text('final_url')->nullable();
            $table->text('title')->nullable();
            $table->timestampTz('retrieved_at')->nullable();
            $table->string('retrieval_method')->nullable();
            $table->text('extracted_text')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->jsonb('origin_metadata')->nullable();
            $table->text('unresolved_reason')->nullable();
            $table->boolean('restricted_processing_consent')->default(false);
            $table->boolean('publication_permission')->default(false);
            $table->foreignId('consent_actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('consented_at')->nullable();
            $table->timestamps();

            $table->index(['attempt_id', 'source_type']);
        });

        Schema::create('editorial_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->constrained('publishing_attempts')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('editorial_activities')->nullOnDelete();
            $table->unsignedInteger('review_cycle')->default(1);
            $table->foreignId('revision_id')->nullable();
            $table->string('input_hash', 64)->nullable();
            $table->string('lens');
            $table->string('kind');
            $table->string('severity');
            $table->string('block_id')->nullable();
            $table->string('expected_subtree_hash', 64)->nullable();
            $table->text('statement');
            $table->text('rationale')->nullable();
            $table->jsonb('supporting_source_ids')->nullable();
            $table->jsonb('supporting_quotations')->nullable();
            $table->jsonb('proposed_patch')->nullable();
            $table->string('reconciliation_state')->nullable();
            $table->string('reconciliation_group')->nullable();
            $table->foreignId('reconciled_into_finding_id')->nullable()->constrained('editorial_findings')->nullOnDelete();
            $table->jsonb('reconciliation_payload')->nullable();
            $table->string('disposition')->nullable();
            $table->text('disposition_reason')->nullable();
            $table->foreignId('disposition_actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('disposed_at')->nullable();
            $table->timestampTz('stale_at')->nullable();
            $table->timestamps();

            $table->foreign(['article_id', 'attempt_id'])->references(['article_id', 'id'])->on('publishing_attempts')->cascadeOnDelete();
            $table->foreign(['article_id', 'revision_id'])->references(['article_id', 'id'])->on('article_revisions')->nullOnDelete();
            $table->index(['attempt_id', 'review_cycle', 'revision_id']);
            $table->index(['attempt_id', 'severity', 'disposition', 'stale_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_findings');
        Schema::dropIfExists('evidence_sources');
        Schema::dropIfExists('agent_budget_reservations');
        Schema::dropIfExists('editorial_activities');
        Schema::table('publishing_attempts', function (Blueprint $table): void {
            $table->dropColumn(['review_cycle', 'recheck_used', 'allowance_changes']);
        });
    }
};
