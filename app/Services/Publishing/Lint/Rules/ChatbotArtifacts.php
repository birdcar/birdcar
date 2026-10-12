<?php

namespace App\Services\Publishing\Lint\Rules;

class ChatbotArtifacts extends BannedPhrasesRule
{
    public function id(): string
    {
        return 'chatbot-artifacts';
    }

    protected function phrases(): array
    {
        return [
            'I hope this helps', 'let me know if you have', 'feel free to reach out', 'great question', "you're absolutely right",
            'here is an overview of', 'while details are limited', 'based on available information',
        ];
    }

    protected function message(string $excerpt): string
    {
        return 'Remove the chatbot phrase "'.$excerpt.'".';
    }
}
