<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_activities', function (Blueprint $table): void {
            $table->string('ai_conversation_id', 36)->nullable()->index();
            $table->jsonb('pending_tool_approvals')->nullable();
            $table->jsonb('tool_decisions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('editorial_activities', function (Blueprint $table): void {
            $table->dropIndex(['ai_conversation_id']);
            $table->dropColumn(['ai_conversation_id', 'pending_tool_approvals', 'tool_decisions']);
        });
    }
};
