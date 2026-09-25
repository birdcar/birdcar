<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PreviewArticleRequest;
use App\Services\Publishing\ArticleDocument;
use Illuminate\Http\Response;

class PreviewArticleController
{
    public function __invoke(PreviewArticleRequest $request, ArticleDocument $documents): Response
    {
        $release = $request->selectedRelease();
        if ($release !== null) {
            $article = $release->article;
            $payload = $release->getAttribute('payload');
            $payload = is_array($payload) ? $payload : [];
            $metadata = $payload['metadata'] ?? [];
            $html = $payload['rendered_document']['html'] ?? '';
            $label = 'Exact release #'.$release->id.' · '.ucfirst($release->status);
        } else {
            $revision = $request->selectedRevision();
            $article = $revision->article;
            $metadata = $revision->metadata ?? [];
            $document = $revision->getAttribute('document');
            $html = $documents->renderHtml(is_array($document) ? $document : []);
            $label = 'Working revision #'.$revision->number;
        }

        return response()
            ->view('admin.publishing.preview', [
                'article' => $article,
                'metadata' => $metadata,
                'label' => $label,
                'html' => $html,
            ])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
