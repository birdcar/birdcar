<?php

namespace App\Services\Publishing;

use InvalidArgumentException;

class ArticleDocumentValidationException extends InvalidArgumentException
{
    /**
     * @param  list<array{path: string, message: string}>  $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct($this->formatMessage($errors));
    }

    /**
     * @return list<array{path: string, message: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param  list<array{path: string, message: string}>  $errors
     */
    private function formatMessage(array $errors): string
    {
        if ($errors === []) {
            return 'The article document is invalid.';
        }

        return 'The article document is invalid: '.implode('; ', array_map(
            static fn (array $error): string => $error['path'].': '.$error['message'],
            $errors,
        ));
    }
}
