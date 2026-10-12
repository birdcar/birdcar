<?php

use App\Models\ContentIdea;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves each article's idea text into its own content idea and gives articles a piece kind and an optional parent
 * piece. Rolling back copies each idea body back onto its pieces; summary titles, their source and parent links are
 * discarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_ideas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('summary_title', 120);
            $table->string('summary_title_source', 20)->default(ContentIdea::SOURCE_DERIVED);
            $table->text('body');
            $table->timestamps();

            $table->index(['author_id', 'created_at']);
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->foreignId('content_idea_id')->nullable();
            $table->string('kind', 30)->default('blog_post');
            $table->foreignId('parent_article_id')->nullable();
        });

        $this->backfillIdeas();

        Schema::table('articles', function (Blueprint $table): void {
            $table->foreignId('content_idea_id')->nullable(false)->change();
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->foreign('content_idea_id')->references('id')->on('content_ideas')->restrictOnDelete();
            $table->foreign('parent_article_id')->references('id')->on('articles')->nullOnDelete();
            $table->index(['content_idea_id', 'kind']);
            $table->dropColumn('idea');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->text('idea')->nullable();
        });

        DB::table('articles')->orderBy('id')->chunkById(200, function ($articles): void {
            $bodies = DB::table('content_ideas')
                ->whereIn('id', $articles->pluck('content_idea_id')->all())
                ->pluck('body', 'id');

            foreach ($articles as $article) {
                DB::table('articles')->where('id', $article->id)->update(['idea' => $bodies[$article->content_idea_id] ?? null]);
            }
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->dropForeign(['content_idea_id']);
            $table->dropForeign(['parent_article_id']);
            $table->dropIndex(['content_idea_id', 'kind']);
            $table->dropColumn(['content_idea_id', 'kind', 'parent_article_id']);
        });

        Schema::dropIfExists('content_ideas');
    }

    private function backfillIdeas(): void
    {
        DB::table('articles')->orderBy('id')->chunkById(200, function ($articles): void {
            $archived = DB::table('article_releases')
                ->whereIn('article_id', $articles->pluck('id')->all())
                ->where('origin', 'import')
                ->pluck('article_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            foreach ($articles as $article) {
                $body = (string) ($article->idea ?? '');
                $ideaId = DB::table('content_ideas')->insertGetId([
                    'author_id' => $article->author_id,
                    'summary_title' => in_array((int) $article->id, $archived, true)
                        ? ContentIdea::summaryTitleFromPublishedTitle($body)
                        : ContentIdea::deriveSummaryTitle($body),
                    'summary_title_source' => ContentIdea::SOURCE_DERIVED,
                    'body' => $body,
                    'created_at' => $article->created_at,
                    'updated_at' => $article->updated_at,
                ]);

                DB::table('articles')->where('id', $article->id)->update(['content_idea_id' => $ideaId]);
            }
        });
    }
};
