<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PreviewArticleRequest;
use App\Services\Publishing\ArticleDocument;
use Illuminate\Http\Response;

class PreviewArticleController
{
    public function __invoke(PreviewArticleRequest $request, ArticleDocument $documents): Response
    {
        $revision = $request->selectedRevision();
        $document = $revision->getAttribute('document');
        $html = $documents->renderHtml(is_array($document) ? $document : []);

        return response()
            ->view('admin.publishing.preview', [
                'article' => $revision->article,
                'revision' => $revision,
                'html' => $html,
            ])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
