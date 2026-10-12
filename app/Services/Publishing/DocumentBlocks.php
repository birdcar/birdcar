<?php

namespace App\Services\Publishing;

use RuntimeException;

/**
 * Finds and replaces blocks by attrs.id in a Tiptap document, the shared mechanics of bounded block patches.
 */
final class DocumentBlocks
{
    /**
     * The block with this id, the index path to it, and whether it or any ancestor is protected.
     *
     * @param  array<array-key, mixed>  $document
     * @return array{path: list<int>, node: array<string, mixed>, protected: bool}|null
     */
    public function find(array $document, string $blockId): ?array
    {
        return $this->findInNodes(is_array($document['content'] ?? null) ? $document['content'] : [], $blockId, [], false);
    }

    /**
     * @param  array<array-key, mixed>  $document
     * @param  list<int>  $path
     * @param  array<string, mixed>  $replacement
     * @return array<array-key, mixed>
     */
    public function replace(array $document, array $path, array $replacement): array
    {
        $nodes = is_array($document['content'] ?? null) ? $document['content'] : [];
        $document['content'] = $this->replaceNodeAtPath($nodes, $path, $replacement);

        return $document;
    }

    /**
     * Two paths overlap when one block contains the other, so replacing both would lose one change.
     *
     * @param  list<int>  $left
     * @param  list<int>  $right
     */
    public function overlaps(array $left, array $right): bool
    {
        $limit = min(count($left), count($right));
        for ($index = 0; $index < $limit; $index++) {
            if ($left[$index] !== $right[$index]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<array-key, mixed>  $nodes
     * @param  list<int>  $path
     * @return array{path: list<int>, node: array<string, mixed>, protected: bool}|null
     */
    private function findInNodes(array $nodes, string $blockId, array $path, bool $protected): ?array
    {
        foreach ($nodes as $index => $node) {
            if (! is_array($node)) {
                continue;
            }

            /** @var array<string, mixed> $node */
            $currentPath = [...$path, (int) $index];
            $nodeProtected = $protected || data_get($node, 'attrs.protected') === true;
            if (data_get($node, 'attrs.id') === $blockId) {
                return ['path' => $currentPath, 'node' => $node, 'protected' => $nodeProtected];
            }

            if (is_array($node['content'] ?? null)) {
                $match = $this->findInNodes($node['content'], $blockId, $currentPath, $nodeProtected);
                if ($match !== null) {
                    return $match;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $nodes
     * @param  list<int>  $path
     * @param  array<string, mixed>  $replacement
     * @return array<array-key, mixed>
     */
    private function replaceNodeAtPath(array $nodes, array $path, array $replacement): array
    {
        $index = array_shift($path);
        if ($index === null || ! array_key_exists($index, $nodes)) {
            throw new RuntimeException('Patch target block was not found.');
        }

        if ($path === []) {
            $nodes[$index] = $replacement;

            return $nodes;
        }

        $node = $nodes[$index];
        if (! is_array($node) || ! is_array($node['content'] ?? null)) {
            throw new RuntimeException('Patch target block was not found.');
        }

        $node['content'] = $this->replaceNodeAtPath($node['content'], $path, $replacement);
        $nodes[$index] = $node;

        return $nodes;
    }
}
