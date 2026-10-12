<?php

namespace App\Actions\Publishing;

use App\Models\Article;

/**
 * What a board move did: it moved, it needs a dialog before it can happen (a reason, or the workspace release
 * checklist), or it was refused with a message that names the gate.
 */
final readonly class MoveResult
{
    public const MOVED = 'moved';

    public const NEEDS_DIALOG = 'needs_dialog';

    public const REFUSED = 'refused';

    public function __construct(public string $outcome, public ?string $message = null, public ?string $dialog = null, public ?Article $piece = null) {}

    public static function moved(?string $message = null): self
    {
        return new self(self::MOVED, $message);
    }

    public static function needsDialog(string $dialog, ?Article $piece = null): self
    {
        return new self(self::NEEDS_DIALOG, dialog: $dialog, piece: $piece);
    }

    public static function refused(string $message): self
    {
        return new self(self::REFUSED, $message);
    }

    public function wasRefused(): bool
    {
        return $this->outcome === self::REFUSED;
    }

    public function needs(string $dialog): bool
    {
        return $this->outcome === self::NEEDS_DIALOG && $this->dialog === $dialog;
    }
}
