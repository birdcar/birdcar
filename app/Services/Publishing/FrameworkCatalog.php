<?php

namespace App\Services\Publishing;

use RuntimeException;

/**
 * The post types and narrative frameworks in the canon's frameworks/catalog.json. Activities freeze the decoded catalog
 * into their input, so a re-plan or an output validation judges against the canon version its activity started with.
 *
 * @phpstan-type PostType array{label: string, template: string, words: array{0: int, 1: int}, frameworks: list<string>}
 * @phpstan-type Framework array{name: string, file: string, complexity: string, min_words: int}
 */
final readonly class FrameworkCatalog
{
    public const PATH = 'frameworks/catalog.json';

    private const COMPLEXITIES = ['low', 'medium', 'high'];

    /** A high-complexity framework needs room: post types capped at this many words exclude it. */
    private const HIGH_COMPLEXITY_MIN_UPPER_BOUND = 1500;

    /**
     * @param  array<string, PostType>  $postTypes
     * @param  array<string, Framework>  $frameworks
     * @param  list<array{0: string, 1: string}>  $avoidCombinations
     */
    public function __construct(public array $postTypes, public array $frameworks, public array $avoidCombinations) {}

    public static function fromSnapshot(CanonSnapshot $snapshot): self
    {
        $content = $snapshot->files[CanonSnapshot::CANON_PREFIX.self::PATH] ?? null;
        if ($content === null) {
            throw new RuntimeException('The voice canon file '.self::PATH.' is missing.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('The voice canon file '.self::PATH.' is not valid JSON.');
        }

        return self::fromArray($decoded);
    }

    /**
     * @param  array<mixed>  $decoded
     */
    public static function fromArray(array $decoded): self
    {
        $postTypes = [];
        foreach (self::section($decoded, 'post_types') as $slug => $postType) {
            $words = $postType['words'] ?? null;
            if (! is_string($slug) || ! is_string($postType['label'] ?? null) || ! is_string($postType['template'] ?? null)
                || ! is_array($words) || count($words) !== 2 || ! is_int($words[0] ?? null) || ! is_int($words[1] ?? null) || $words[0] > $words[1]) {
                throw new RuntimeException('The framework catalog post type '.(is_string($slug) ? $slug : '?').' needs a label, a template and a [min, max] word range.');
            }

            $postTypes[$slug] = [
                'label' => $postType['label'],
                'template' => $postType['template'],
                'words' => [$words[0], $words[1]],
                'frameworks' => self::slugs($postType['frameworks'] ?? null, 'post type '.$slug),
            ];
        }

        $frameworks = [];
        foreach (self::section($decoded, 'frameworks') as $slug => $framework) {
            if (! is_string($slug) || ! is_string($framework['name'] ?? null) || ! is_string($framework['file'] ?? null)
                || ! in_array($framework['complexity'] ?? null, self::COMPLEXITIES, true) || ! is_int($framework['min_words'] ?? null)) {
                throw new RuntimeException('The framework catalog framework '.(is_string($slug) ? $slug : '?').' needs a name, a file, a complexity and min_words.');
            }

            $frameworks[$slug] = [
                'name' => $framework['name'],
                'file' => $framework['file'],
                'complexity' => (string) $framework['complexity'],
                'min_words' => $framework['min_words'],
            ];
        }

        foreach ($postTypes as $slug => $postType) {
            foreach ($postType['frameworks'] as $framework) {
                if (! array_key_exists($framework, $frameworks)) {
                    throw new RuntimeException("The framework catalog post type {$slug} lists the unknown framework {$framework}.");
                }
            }
        }

        $avoid = [];
        $pairs = $decoded['avoid_combinations'] ?? [];
        foreach (is_array($pairs) ? $pairs : [] as $pair) {
            if (! is_array($pair) || count($pair) !== 2 || ! is_string($pair[0] ?? null) || ! is_string($pair[1] ?? null)) {
                throw new RuntimeException('The framework catalog avoid_combinations entries must be pairs of framework slugs.');
            }
            $avoid[] = [$pair[0], $pair[1]];
        }

        return new self($postTypes, $frameworks, $avoid);
    }

    /**
     * @return array{post_types: array<string, PostType>, frameworks: array<string, Framework>, avoid_combinations: list<array{0: string, 1: string}>}
     */
    public function toArray(): array
    {
        return ['post_types' => $this->postTypes, 'frameworks' => $this->frameworks, 'avoid_combinations' => $this->avoidCombinations];
    }

    /** @return list<string> */
    public function postTypeSlugs(): array
    {
        return array_keys($this->postTypes);
    }

    public function hasPostType(string $postType): bool
    {
        return array_key_exists($postType, $this->postTypes);
    }

    public function postTypeLabel(string $postType): string
    {
        return $this->postTypes[$postType]['label'] ?? $postType;
    }

    public function frameworkName(string $slug): string
    {
        return $this->frameworks[$slug]['name'] ?? $slug;
    }

    /** Canon-relative path of the post type's template, such as post-types/work-story.md. */
    public function templatePath(string $postType): ?string
    {
        return $this->postTypes[$postType]['template'] ?? null;
    }

    /** Canon-relative path of the framework file, such as frameworks/the-jeremiad.md. */
    public function frameworkPath(string $slug): ?string
    {
        return $this->frameworks[$slug]['file'] ?? null;
    }

    /**
     * Frameworks listed for the post type that its length does not exclude, in the post type's order. Without a word
     * count the post type's upper bound is the length.
     *
     * @return list<string>
     */
    public function allowedFor(string $postType, ?int $words = null): array
    {
        $type = $this->postTypes[$postType] ?? null;
        if ($type === null) {
            return [];
        }

        $upper = $words ?? $type['words'][1];

        return array_values(array_filter($type['frameworks'], fn (string $slug): bool => ! $this->isLengthExcluded($slug, $upper)));
    }

    public function isLengthExcluded(string $framework, int $upperBound): bool
    {
        $definition = $this->frameworks[$framework] ?? null;
        if ($definition === null) {
            return true;
        }

        return $definition['min_words'] > $upperBound
            || ($definition['complexity'] === 'high' && $upperBound <= self::HIGH_COMPLEXITY_MIN_UPPER_BOUND);
    }

    /** Order-insensitive: the guide's avoid list names pairs, not a primary and a secondary. */
    public function avoids(string $first, string $second): bool
    {
        foreach ($this->avoidCombinations as [$left, $right]) {
            if (($left === $first && $right === $second) || ($left === $second && $right === $first)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every reason a Planner framework proposal is unusable for the post type, or an empty list when it is valid.
     *
     * @param  array<mixed>  $framework
     * @return list<string>
     */
    public function validate(array $framework, string $postType): array
    {
        if (! $this->hasPostType($postType)) {
            return ['The post type "'.$postType.'" is not in the framework catalog; allowed post types: '.implode(', ', $this->postTypeSlugs()).'.'];
        }

        $allowed = $this->allowedFor($postType);
        $allowedList = implode(', ', $allowed);
        $errors = [];

        foreach (['primary', 'runner_up'] as $key) {
            $slug = $framework[$key] ?? null;
            $label = $key === 'primary' ? 'primary framework' : 'runner-up framework';
            if (! is_string($slug) || trim($slug) === '') {
                $errors[] = 'The plan needs a '.$label.'; allowed frameworks for a '.$postType.' post: '.$allowedList.'.';
            } elseif (! in_array($slug, $allowed, true)) {
                $errors[] = 'The '.$label.' "'.$slug.'" is not allowed for a '.$postType.' post; allowed frameworks: '.$allowedList.'.';
            }

            $reason = data_get($framework, 'reasons.'.$key);
            if (! is_string($reason) || trim($reason) === '') {
                $errors[] = 'The plan needs a one-sentence reason for its '.$label.'.';
            }
        }

        $primary = $framework['primary'] ?? null;
        if (is_string($primary) && $primary !== '' && $primary === ($framework['runner_up'] ?? null)) {
            $errors[] = 'The runner-up framework must differ from the primary framework.';
        }

        $secondary = $framework['secondary'] ?? null;
        if ($secondary !== null) {
            $slug = is_array($secondary) ? ($secondary['framework'] ?? null) : null;
            $heading = is_array($secondary) ? ($secondary['section_heading'] ?? null) : null;
            if (! is_string($slug) || ! in_array($slug, $allowed, true)) {
                $errors[] = 'The secondary framework must be one of the allowed frameworks for a '.$postType.' post: '.$allowedList.'.';
            } elseif ($slug === $primary) {
                $errors[] = 'The secondary framework must differ from the primary framework.';
            } elseif (is_string($primary) && $this->avoids($primary, $slug)) {
                $errors[] = 'The framework guide says to avoid combining "'.$primary.'" with "'.$slug.'".';
            }

            if (! is_string($heading) || trim($heading) === '') {
                $errors[] = 'The secondary framework must name the section it shapes.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<mixed>  $decoded
     * @return array<array-key, array<mixed>>
     */
    private static function section(array $decoded, string $key): array
    {
        $section = $decoded[$key] ?? null;
        if (! is_array($section) || $section === []) {
            throw new RuntimeException("The framework catalog needs a non-empty {$key} object.");
        }

        $entries = [];
        foreach ($section as $slug => $entry) {
            if (! is_array($entry)) {
                throw new RuntimeException("The framework catalog {$key} entry {$slug} must be an object.");
            }
            $entries[$slug] = $entry;
        }

        return $entries;
    }

    /** @return list<string> */
    private static function slugs(mixed $values, string $owner): array
    {
        if (! is_array($values) || $values === []) {
            throw new RuntimeException("The framework catalog {$owner} must list its frameworks.");
        }

        $slugs = [];
        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                throw new RuntimeException("The framework catalog {$owner} lists an invalid framework.");
            }
            $slugs[] = $value;
        }

        return $slugs;
    }
}
