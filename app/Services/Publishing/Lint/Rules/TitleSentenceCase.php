<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintTarget;

/** The on-page title follows the same sentence case as the body headings. The SEO title and description do not. */
class TitleSentenceCase extends VoiceRule
{
    use CountsCapitalizedWords;

    public function id(): string
    {
        return 'title-sentence-case';
    }

    public function check(LintTarget $target): array
    {
        $title = $target->metadataValue('title');
        if ($title === null || ! $this->isTitleCase($title)) {
            return [];
        }

        $title = trim($title);

        return [$this->metadataHit('title', $title, 'Write the title "'.$title.'" in sentence case: capitalize the first word and proper nouns only.')];
    }
}
