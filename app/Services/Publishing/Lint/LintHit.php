<?php

namespace App\Services\Publishing\Lint;

/**
 * One place a manuscript breaks a mechanical voice rule. Body hits point at a block id; metadata hits name the field.
 */
final readonly class LintHit
{
    public function __construct(
        public string $ruleId,
        public ?string $blockId,
        public string $excerpt,
        public string $message,
        public ?string $field = null,
        public int $occurrence = 1,
    ) {}

    /**
     * Overrides match by this hash, so an override survives edits elsewhere but not a change to the offending text. A
     * repeat of the same excerpt in one place hashes apart, so an override never covers a second occurrence added later.
     */
    public function inputHash(): string
    {
        $parts = [$this->ruleId, $this->location(), $this->excerpt];
        if ($this->occurrence > 1) {
            $parts[] = (string) $this->occurrence;
        }

        return hash('sha256', implode('|', $parts));
    }

    public function withOccurrence(int $occurrence): self
    {
        return new self($this->ruleId, $this->blockId, $this->excerpt, $this->message, $this->field, $occurrence);
    }

    public function location(): string
    {
        return $this->blockId ?? '$.metadata.'.($this->field ?? 'unknown');
    }
}
