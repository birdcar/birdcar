<?php

namespace App\Services\Publishing;

use App\Models\Publishing\PieceKind;

/**
 * A distribution kit piece keeps each channel field as one paragraph of its document, under a block id derived from the
 * field's role, so the text lives in exactly one place and the linter, the repair agent and the copy-out all find a field
 * by its id. A line break inside a field is a hard break, and URLs stay plain text so a copied field keeps them.
 *
 * Roles are `text` and `first_comment` (LinkedIn), `post:1` to `post:n` (Bluesky) and `subject`, `preview` and `body`
 * (email).
 */
final class KitDocument
{
    /** @var array<string, list<string>> the fields a drafted piece of each kit kind returns */
    public const FIELDS = [
        'linkedin_post' => ['text', 'first_comment'],
        'bluesky_thread' => ['posts'],
        'email_blurb' => ['subject', 'preview', 'body'],
    ];

    /** @return list<string> */
    public static function fieldsFor(PieceKind $kind): array
    {
        return self::FIELDS[$kind->value] ?? [];
    }

    public static function blockId(PieceKind $kind, string $role): string
    {
        return 'blk_'.substr(hash('sha256', 'kit:'.$kind->value.':'.$role), 0, 16);
    }

    /**
     * The canonical document for a piece's fields, one paragraph per field or Bluesky post in reading order.
     *
     * @param  array<array-key, mixed>  $fields
     * @return array{version: int, type: string, content: list<array<string, mixed>>}
     */
    public static function document(PieceKind $kind, array $fields): array
    {
        $content = [];
        foreach (self::rolesWithText($kind, $fields) as $role => $text) {
            $content[] = self::paragraph(self::blockId($kind, $role), $text);
        }

        return ['version' => 1, 'type' => 'doc', 'content' => $content];
    }

    /**
     * The piece's fields read back from its document. Blocks with no kit role are ignored.
     *
     * @param  array<array-key, mixed>  $document
     * @return array<string, string|list<string>>
     */
    public static function fields(PieceKind $kind, array $document): array
    {
        $texts = [];
        foreach (self::roles($kind, $document) as $blockId => $role) {
            $texts[$role] = self::text(self::node($document, $blockId) ?? []);
        }

        if ($kind === PieceKind::BlueskyThread) {
            $posts = [];
            foreach ($texts as $role => $text) {
                if (str_starts_with($role, 'post:')) {
                    $posts[] = $text;
                }
            }

            return ['posts' => $posts];
        }

        $fields = [];
        foreach (self::fieldsFor($kind) as $field) {
            $fields[$field] = $texts[$field] ?? '';
        }

        return $fields;
    }

    /**
     * Each kit block's role, keyed by block id, in document order. Bluesky posts are numbered by their id, so a post
     * keeps its role however the thread around it is edited.
     *
     * @param  array<array-key, mixed>  $document
     * @return array<string, string>
     */
    public static function roles(PieceKind $kind, array $document): array
    {
        $content = is_array($document['content'] ?? null) ? array_values($document['content']) : [];
        $candidates = [];
        if ($kind === PieceKind::BlueskyThread) {
            for ($number = 1; $number <= count($content); $number++) {
                $candidates[self::blockId($kind, 'post:'.$number)] = 'post:'.$number;
            }
        } else {
            foreach (self::fieldsFor($kind) as $field) {
                $candidates[self::blockId($kind, $field)] = $field;
            }
        }

        $roles = [];
        foreach ($content as $node) {
            $id = is_array($node) ? data_get($node, 'attrs.id') : null;
            if (is_string($id) && isset($candidates[$id])) {
                $roles[$id] = $candidates[$id];
            }
        }

        return $roles;
    }

    /**
     * A block's text as it is copied out: text nodes in order, a hard break as a new line.
     *
     * @param  array<array-key, mixed>  $node
     */
    public static function text(array $node): string
    {
        $text = '';
        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (! is_array($child)) {
                continue;
            }

            if (($child['type'] ?? null) === 'hardBreak') {
                $text .= "\n";
            } elseif (is_string($child['text'] ?? null)) {
                $text .= $child['text'];
            } else {
                $text .= self::text($child);
            }
        }

        return $text;
    }

    /** How the kit panel names a role. */
    public static function label(PieceKind $kind, string $role): string
    {
        if (str_starts_with($role, 'post:')) {
            return 'Post '.substr($role, 5);
        }

        return match ($role) {
            'text' => $kind === PieceKind::LinkedinPost ? 'Post text' : 'Text',
            'first_comment' => 'First comment',
            'subject' => 'Subject line',
            'preview' => 'Preview line',
            'body' => 'Body',
            default => ucfirst(str_replace('_', ' ', $role)),
        };
    }

    /**
     * @param  array<array-key, mixed>  $fields
     * @return array<string, string>
     */
    private static function rolesWithText(PieceKind $kind, array $fields): array
    {
        if ($kind === PieceKind::BlueskyThread) {
            $posts = is_array($fields['posts'] ?? null) ? array_values($fields['posts']) : [];
            $roles = [];
            foreach ($posts as $index => $post) {
                $roles['post:'.($index + 1)] = is_string($post) ? $post : '';
            }

            return $roles;
        }

        $roles = [];
        foreach (self::fieldsFor($kind) as $field) {
            $roles[$field] = is_string($fields[$field] ?? null) ? $fields[$field] : '';
        }

        return $roles;
    }

    /** @return array<string, mixed> */
    private static function paragraph(string $id, string $text): array
    {
        $lines = explode("\n", trim(str_replace(["\r\n", "\r"], "\n", $text)));
        $content = [];
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $content[] = ['type' => 'hardBreak'];
            }
            if ($line !== '') {
                $content[] = ['type' => 'text', 'text' => $line];
            }
        }

        $paragraph = ['type' => 'paragraph', 'attrs' => ['id' => $id]];
        if ($content !== []) {
            $paragraph['content'] = $content;
        }

        return $paragraph;
    }

    /**
     * @param  array<array-key, mixed>  $document
     * @return array<array-key, mixed>|null
     */
    private static function node(array $document, string $blockId): ?array
    {
        foreach (is_array($document['content'] ?? null) ? $document['content'] : [] as $node) {
            if (is_array($node) && data_get($node, 'attrs.id') === $blockId) {
                return $node;
            }
        }

        return null;
    }
}
