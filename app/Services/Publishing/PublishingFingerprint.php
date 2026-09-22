<?php

namespace App\Services\Publishing;

use InvalidArgumentException;

class PublishingFingerprint
{
    public function hash(mixed $value): string
    {
        return hash('sha256', $this->canonicalJson($value));
    }

    public function canonicalJson(mixed $value): string
    {
        return json_encode(
            $this->canonicalize($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
    }

    private function canonicalize(mixed $value): mixed
    {
        if (is_float($value) && ! is_finite($value)) {
            throw new InvalidArgumentException('Non-finite numbers cannot be fingerprinted.');
        }

        if (is_resource($value)) {
            throw new InvalidArgumentException('Resources cannot be fingerprinted.');
        }

        if (is_object($value)) {
            if (! $value instanceof \JsonSerializable) {
                throw new InvalidArgumentException('Objects must be JSON serializable to be fingerprinted.');
            }

            return $this->canonicalize($value->jsonSerialize());
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);

        $canonical = [];

        foreach ($value as $key => $item) {
            $canonical[(string) $key] = $this->canonicalize($item);
        }

        return $canonical;
    }
}
