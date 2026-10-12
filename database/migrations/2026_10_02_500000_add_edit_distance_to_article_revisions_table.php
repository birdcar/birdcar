<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An owner revision saved straight after an agent-authored one records how many words the owner changed, and which
 * agent revision it was measured against. The baseline is a single-column foreign key: a composite (article_id, id)
 * key would null the required article_id on delete. Rolling back drops both columns and every recorded distance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_revisions', function (Blueprint $table): void {
            $table->integer('agent_edit_distance')->nullable();
            $table->foreignId('agent_baseline_revision_id')->nullable()->constrained('article_revisions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('article_revisions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('agent_baseline_revision_id');
            $table->dropColumn('agent_edit_distance');
        });
    }
};
