<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AskAuthor implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function description(): Stringable|string
    {
        return 'Ask the human author for missing editorial interview answers before continuing.';
    }

    public function handle(Request $request): Stringable|string
    {
        $questions = $request->array('questions');
        $answers = $request->string('answers')->toString();

        if ($questions === [] || Arr::where($questions, fn (mixed $question): bool => ! is_string($question) || trim($question) === '') !== []) {
            throw new InvalidArgumentException('AskAuthor requires non-empty questions.');
        }

        if (trim($answers) === '') {
            throw new InvalidArgumentException('AskAuthor requires non-empty human answers.');
        }

        return json_encode([
            'type' => 'author_answers',
            'answers' => $answers,
        ], JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'questions' => $schema->array()->items($schema->string()->min(1))->required(),
            'answers' => $schema->string()->nullable(),
        ];
    }
}
