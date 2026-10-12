<?php

namespace App\Services\Publishing\Lint\Rules;

class CorporateVocabulary extends BannedPhrasesRule
{
    public function id(): string
    {
        return 'corporate-vocabulary';
    }

    protected function phrases(): array
    {
        return ['synergy', 'circle back', 'leverage', 'bandwidth', 'align', 'holistic', 'robust', 'seamless'];
    }

    protected function message(string $excerpt): string
    {
        return '"'.$excerpt.'" is corporate vocabulary. Say what you mean in plain words.';
    }
}
