<?php

namespace App\Actions;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ReadWriting
{
    public function render(string $body): string
    {
        $blocks = [];
        $markdown = preg_replace_callback(
            '/^@figure kind=diagram name=(walkthrough|reporting) caption="((?:[^"\\\\\r\n]|\\\\["\\\\])+)"[\t ]*\R@endfigure[\t ]*$/m',
            function (array $match) use (&$blocks): string {
                $key = 'BIRDCARBLOCK'.count($blocks).'END';
                $blocks['<p>'.$key.'</p>'] = view('components.marketing.article-diagram', [
                    'name' => $match[1],
                    'caption' => strtr($match[2], ['\\"' => '"', '\\\\' => '\\']),
                ])->render();

                return "\n\n{$key}\n\n";
            },
            $body,
        ) ?? $body;

        $markdown = preg_replace_callback(
            '/^@figure kind=chart type=(line|bar) src=\.\/data\/([a-z0-9-]+\.json) width=wide caption="([^"]+)"\R@endfigure\s*$/m',
            function (array $match) use (&$blocks): string {
                $key = 'BIRDCARBLOCK'.count($blocks).'END';
                /** @var array{x: string, series: list<array{key: string, label: string}>, data: list<array<string, int|string>>} $data */
                $data = json_decode(File::get(resource_path('writing/data/'.$match[2])), true, flags: JSON_THROW_ON_ERROR);
                $blocks['<p>'.$key.'</p>'] = view('components.marketing.article-chart', [
                    'chart' => $data,
                    'type' => $match[1],
                    'caption' => $match[3],
                ])->render();

                return "\n\n{$key}\n\n";
            },
            $markdown,
        ) ?? $markdown;

        $markdown = preg_replace_callback(
            '/^@(aside|callout)(?: title="([^"\r\n]+)"| type=key)\R(.*?)\R@end\1[\t ]*$/ms',
            function (array $match) use (&$blocks): string {
                $key = 'BIRDCARBLOCK'.count($blocks).'END';
                $noteWithAuthorLinks = preg_replace('/\(@([a-z][a-z0-9_-]*)\)/i', '([@$1](/authors/$1))', $match[3]) ?? $match[3];
                $blocks['<p>'.$key.'</p>'] = view('components.marketing.article-note', [
                    'title' => $match[1] === 'callout' ? 'Key takeaway' : $match[2],
                    'html' => Str::markdown($noteWithAuthorLinks, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
                ])->render();

                return "\n\n{$key}\n\n";
            },
            $markdown,
        ) ?? $markdown;

        return strtr(Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]), $blocks);
    }
}
