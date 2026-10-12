<?php

namespace App\Services\Publishing\Lint\Rules;

class FillerPhrases extends BannedPhrasesRule
{
    /** @var array<string, string> */
    private const REPLACEMENTS = [
        'in order to' => 'to',
        'due to the fact that' => 'because',
        'at this point in time' => 'now',
        'in the event that' => 'if',
        'has the ability to' => 'can',
    ];

    public function id(): string
    {
        return 'filler-phrases';
    }

    protected function phrases(): array
    {
        return [
            'in order to', 'due to the fact that', 'it is worth noting', "it's worth noting", 'it should be noted',
            'it is important to note', 'at this point in time', 'in the event that', 'has the ability to',
        ];
    }

    protected function message(string $excerpt): string
    {
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', $excerpt) ?? $excerpt);
        $replacement = self::REPLACEMENTS[$normalized] ?? null;

        return $replacement === null
            ? 'Cut the filler phrase "'.$excerpt.'" and state the point.'
            : 'Replace the filler phrase "'.$excerpt.'" with "'.$replacement.'".';
    }
}
