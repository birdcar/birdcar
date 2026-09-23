<?php

namespace App\Http\Requests;

use App\Models\Article;
use App\Models\ArticleRevision;
use Illuminate\Foundation\Http\FormRequest;

class PreviewArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $routeArticle = $this->route('article');
        $article = $routeArticle instanceof Article
            ? $routeArticle
            : Article::query()->where('slug', (string) $routeArticle)->first();

        return $article instanceof Article
            && $this->user()?->can('view', $article) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'revision' => ['nullable', 'integer', 'exists:article_revisions,id'],
        ];
    }

    public function selectedRevision(): ArticleRevision
    {
        $routeArticle = $this->route('article');
        $article = $routeArticle instanceof Article
            ? $routeArticle
            : Article::query()->where('slug', (string) $routeArticle)->firstOrFail();
        $revisionId = $this->integer('revision') ?: $article->working_revision_id;

        /** @var ArticleRevision $revision */
        $revision = ArticleRevision::query()
            ->whereKey($revisionId)
            ->where('article_id', $article->id)
            ->firstOrFail();

        return $revision;
    }
}
