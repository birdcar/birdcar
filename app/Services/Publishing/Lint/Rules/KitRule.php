<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Models\Publishing\PieceKind;
use App\Services\Publishing\Lint\LintTarget;

/**
 * A channel rule for distribution kit pieces. Lengths count characters as written, and URLs are found in the text as
 * well as in link marks, because a kit piece keeps its links as plain text so a copied field keeps them.
 *
 * @phpstan-import-type LintBlock from LintTarget
 */
abstract class KitRule extends VoiceRule
{
    /** A bare URL: a scheme or www. prefix, or a domain followed by a path. Trailing punctuation is trimmed off. */
    private const URL = '~(?:https?://|www\.)[^\s<>"]+|(?<![\w@./-])(?:[a-z0-9-]+\.)+[a-z]{2,}/[^\s<>"]*~iu';

    /** @return list<PieceKind> */
    abstract protected function kinds(): array;

    public function appliesTo(PieceKind $kind): bool
    {
        return in_array($kind, $this->kinds(), true);
    }

    /**
     * Every URL in a block: bare URLs in its text, then the href of each link mark whose text is not itself a URL.
     *
     * @param  LintBlock  $block
     * @return list<string>
     */
    protected function urls(array $block): array
    {
        $urls = $this->bareUrls($block['text']);
        foreach ($block['links'] as $link) {
            if ($this->bareUrls($link['text']) === []) {
                $urls[] = $link['href'];
            }
        }

        return $urls;
    }

    /** @return list<string> */
    protected function bareUrls(string $text): array
    {
        if (preg_match_all(self::URL, $text, $matches) < 1) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (string $url): string => rtrim($url, '.,;:!?)]\'"'), $matches[0]), static fn (string $url): bool => $url !== ''));
    }

    /** Text with every bare URL replaced, so a URL's dots and slashes never read as sentences or numbering. */
    protected function withoutUrls(string $text): string
    {
        return preg_replace(self::URL, 'URL', $text) ?? $text;
    }

    /** Two URLs name the same page when they match ignoring the scheme, www., letter case, a trailing slash, query and fragment. */
    protected function samePage(string $left, string $right): bool
    {
        return $this->pageKey($left) === $this->pageKey($right);
    }

    private function pageKey(string $url): string
    {
        $key = mb_strtolower(trim($url));
        $key = preg_replace('~^[a-z][a-z0-9+.-]*://~', '', $key) ?? $key;
        $key = preg_replace('~^www\.~', '', $key) ?? $key;
        $key = preg_replace('~[?#].*$~', '', $key) ?? $key;

        return rtrim($key, '/');
    }
}
