<?php

namespace App\Services\Publishing;

use App\Models\Publishing\EditorialActivityKind;
use InvalidArgumentException;

class EditorialOutput
{
    public const VERSION = 2;

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int|string>  $knownEvidenceIds
     * @param  array<int|string, string|null>  $evidenceTextsById
     * @return array<string, mixed>
     */
    public function validate(EditorialActivityKind $kind, array $payload, array $knownEvidenceIds = [], array $evidenceTextsById = []): array
    {
        $payload = $this->normalizeJsonEncodedFields($payload);
        $this->rejectUnknownOversized($payload, $this->allowedKeys($kind));

        return match ($kind) {
            EditorialActivityKind::Interview => $this->validateInterview($payload),
            EditorialActivityKind::ResearchChallenge => $this->validateResearch($payload, $knownEvidenceIds, $evidenceTextsById),
            EditorialActivityKind::Plan => $this->validatePlan($payload),
            EditorialActivityKind::Draft => $this->validateDraft($payload),
            EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer => $this->validateFindings($payload, $knownEvidenceIds, $evidenceTextsById),
            EditorialActivityKind::Reconciliation => $this->validateReconciliation($payload),
            EditorialActivityKind::Recheck => $this->validateRecheck($payload, $knownEvidenceIds, $evidenceTextsById),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int|string>  $knownEvidenceIds
     * @return array<string, mixed>
     */
    public function validateStructureBeforeRetrieval(EditorialActivityKind $kind, array $payload, array $knownEvidenceIds = []): array
    {
        $payload = $this->normalizeJsonEncodedFields($payload);
        $this->rejectUnknownOversized($payload, $this->allowedKeys($kind));

        if ($kind === EditorialActivityKind::ResearchChallenge) {
            $this->validateResearchStructureBeforeRetrieval($payload, $knownEvidenceIds);
        }

        return $payload;
    }

    /** @return list<string> */
    private function allowedKeys(EditorialActivityKind $kind): array
    {
        return match ($kind) {
            EditorialActivityKind::Interview => ['questions', 'brief', 'angleOptions'],
            EditorialActivityKind::ResearchChallenge => ['claims', 'sourceReferences', 'contradictions', 'gaps'],
            EditorialActivityKind::Plan => ['outline', 'argument', 'visualPlan'],
            EditorialActivityKind::Draft => ['document', 'metadataProposals'],
            EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer => ['findings'],
            EditorialActivityKind::Reconciliation => ['groups', 'conflicts'],
            EditorialActivityKind::Recheck => ['resolved', 'unresolved', 'newBlockingFindings'],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeJsonEncodedFields(array $payload): array
    {
        foreach (['document', 'metadataProposals', 'proposed_patch'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = $this->decodeJsonField($payload[$key], $key);
            }
        }

        foreach (['findings', 'newBlockingFindings'] as $collection) {
            if (! is_array($payload[$collection] ?? null)) {
                continue;
            }

            foreach ($payload[$collection] as $index => $finding) {
                if (is_array($finding) && array_key_exists('proposed_patch', $finding)) {
                    $payload[$collection][$index]['proposed_patch'] = $this->optionalPatch($finding['proposed_patch']);
                }
            }
        }

        return $payload;
    }

    /**
     * A patch is an optional suggestion the owner must still accept, so an unparseable one is dropped rather than
     * discarding the whole review.
     *
     * @return array<mixed>|null
     */
    private function optionalPatch(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    private function decodeJsonField(mixed $value, string $field): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new InvalidArgumentException('Agent output field must be a JSON object or existing array: '.$field);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $allowed
     */
    private function rejectUnknownOversized(array $payload, array $allowed): void
    {
        foreach ($payload as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Agent output contained an unknown field: '.$key);
            }

            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
            if (strlen($encoded) > (int) config('publishing_agents.limits.max_field_bytes', 32768)) {
                throw new InvalidArgumentException('Agent output field is too large: '.$key);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateInterview(array $payload): array
    {
        if (! is_array($payload['questions'] ?? null) || ! is_array($payload['brief'] ?? null) || ! is_array($payload['angleOptions'] ?? null)) {
            throw new InvalidArgumentException('Interview output must include questions, brief and angle options.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int|string>  $knownEvidenceIds
     * @param  array<int|string, string|null>  $evidenceTextsById
     * @return array<string, mixed>
     */
    private function validateResearch(array $payload, array $knownEvidenceIds, array $evidenceTextsById): array
    {
        foreach (['claims', 'sourceReferences', 'contradictions', 'gaps'] as $key) {
            if (! is_array($payload[$key] ?? null)) {
                throw new InvalidArgumentException('Research output must include '.$key.'.');
            }
        }
        $this->ensureEvidenceReferencesExist($payload, $knownEvidenceIds, $evidenceTextsById);
        foreach (['claims', 'contradictions'] as $key) {
            foreach ($payload[$key] as $item) {
                if (is_array($item)) {
                    $this->ensureSeverityIsSupported($item);
                    $this->ensureMaterialResearchSupportIsResolved($item, $key);
                    $this->ensureContradictionsRemainBlocking($item);
                }
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int|string>  $knownEvidenceIds
     */
    private function validateResearchStructureBeforeRetrieval(array $payload, array $knownEvidenceIds): void
    {
        foreach (['claims', 'sourceReferences', 'contradictions', 'gaps'] as $key) {
            if (! is_array($payload[$key] ?? null)) {
                throw new InvalidArgumentException('Research output must include '.$key.'.');
            }
        }

        $sourceReferences = array_values($payload['sourceReferences']);
        if (count($sourceReferences) > 10) {
            throw new InvalidArgumentException('Research output referenced too many public sources.');
        }

        $known = $knownEvidenceIds;
        foreach ($sourceReferences as $index => $reference) {
            if (! is_array($reference)) {
                throw new InvalidArgumentException('Research source references must be objects.');
            }

            foreach ($reference as $key => $value) {
                if (! in_array($key, ['url', 'title', 'local_id', 'localId', 'ref', 'reference', 'source_ref', 'sourceRef', 'content'], true)) {
                    throw new InvalidArgumentException('Research source reference contained an unknown field: '.$key);
                }

                if ($value !== null && ! is_scalar($value)) {
                    throw new InvalidArgumentException('Research source reference fields must be scalar.');
                }

                if (is_string($value) && strlen($value) > 2048) {
                    throw new InvalidArgumentException('Research source reference field is too large: '.$key);
                }
            }

            $known = [...$known, ...$this->responseLocalReferenceKeys($reference, $index)];
        }

        $this->ensureEvidenceReferenceStructure($payload, array_values(array_unique($known, SORT_REGULAR)));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validatePlan(array $payload): array
    {
        foreach (['outline', 'argument', 'visualPlan'] as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new InvalidArgumentException('Plan output must include '.$key.'.');
            }
        }

        if (! is_array($payload['outline']) || ! is_array($payload['visualPlan'])) {
            throw new InvalidArgumentException('Plan outline and visual plan must be arrays.');
        }

        if (! is_string($payload['argument']) || trim($payload['argument']) === '') {
            throw new InvalidArgumentException('Plan argument must be a non-empty string.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateDraft(array $payload): array
    {
        if (! is_array($payload['document'] ?? null) || ! is_array($payload['metadataProposals'] ?? null)) {
            throw new InvalidArgumentException('Draft output must include document and metadata proposals.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int|string>  $knownEvidenceIds
     * @param  array<int|string, string|null>  $evidenceTextsById
     * @return array<string, mixed>
     */
    private function validateFindings(array $payload, array $knownEvidenceIds, array $evidenceTextsById): array
    {
        if (! is_array($payload['findings'] ?? null)) {
            throw new InvalidArgumentException('Review output must include findings.');
        }

        $this->ensureFindingCount($payload['findings']);
        foreach ($payload['findings'] as $finding) {
            if (! is_array($finding) || ! is_string($finding['statement'] ?? null)) {
                throw new InvalidArgumentException('Each finding must include a statement.');
            }
            $this->ensureSeverityIsSupported($finding);
            $sourceIds = $finding['supporting_source_ids'] ?? [];
            if (! is_array($sourceIds)) {
                throw new InvalidArgumentException('Finding evidence references must be an array.');
            }
            $this->ensureEvidenceIdsExist($sourceIds, $knownEvidenceIds);
            $this->ensureEvidenceReferencesExist($finding, $knownEvidenceIds, $evidenceTextsById);
            $this->ensureContradictionsRemainBlocking($finding);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateReconciliation(array $payload): array
    {
        if (! is_array($payload['groups'] ?? null) || ! is_array($payload['conflicts'] ?? null)) {
            throw new InvalidArgumentException('Reconciliation output must include groups and conflicts.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int|string>  $knownEvidenceIds
     * @param  array<int|string, string|null>  $evidenceTextsById
     * @return array<string, mixed>
     */
    private function validateRecheck(array $payload, array $knownEvidenceIds, array $evidenceTextsById): array
    {
        foreach (['resolved', 'unresolved', 'newBlockingFindings'] as $key) {
            if (! is_array($payload[$key] ?? null)) {
                throw new InvalidArgumentException('Recheck output must include '.$key.'.');
            }
        }
        $this->ensureRecheckResolutionItems($payload['resolved'], 'resolved');
        $this->ensureRecheckResolutionItems($payload['unresolved'], 'unresolved');
        $this->ensureFindingCount($payload['newBlockingFindings']);
        $this->ensureEvidenceReferencesExist($payload['newBlockingFindings'], $knownEvidenceIds, $evidenceTextsById);
        foreach ($payload['newBlockingFindings'] as $finding) {
            if (is_array($finding)) {
                $this->ensureSeverityIsSupported($finding);
                $this->ensureContradictionsRemainBlocking($finding);
                if (($finding['severity'] ?? null) !== 'blocking') {
                    throw new InvalidArgumentException('New blocking recheck findings must remain blocking.');
                }
            }
        }

        return $payload;
    }

    /** @param array<int, mixed> $findings */
    private function ensureFindingCount(array $findings): void
    {
        if (count($findings) > (int) config('publishing_agents.limits.max_findings_per_activity', 25)) {
            throw new InvalidArgumentException('Agent output contained too many findings.');
        }
    }

    /** @param array<string, mixed> $finding */
    private function ensureSeverityIsSupported(array $finding): void
    {
        $severity = $finding['severity'] ?? null;
        if ($severity !== null && (! is_string($severity) || ! in_array($severity, ['advisory', 'blocking'], true))) {
            throw new InvalidArgumentException('Agent output used an unsupported finding severity.');
        }
    }

    /** @param array<int, mixed> $items */
    private function ensureRecheckResolutionItems(array $items, string $field): void
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Recheck '.$field.' entries must be objects.');
            }

            $findingId = $item['finding_id'] ?? $item['findingId'] ?? null;
            $blockId = $item['block_id'] ?? $item['blockId'] ?? null;
            if ($findingId !== null && ! is_int($findingId)) {
                throw new InvalidArgumentException('Recheck resolution references must use integer finding IDs.');
            }
            if ($blockId !== null && (! is_string($blockId) || trim($blockId) === '')) {
                throw new InvalidArgumentException('Recheck resolution references must use non-empty block IDs.');
            }
            if ($findingId === null && $blockId === null) {
                throw new InvalidArgumentException('Recheck resolution entries must reference a finding or block.');
            }
        }
    }

    /**
     * @param  list<int|string>  $knownEvidenceIds
     */
    private function ensureEvidenceReferenceStructure(mixed $values, array $knownEvidenceIds): void
    {
        if (! is_array($values)) {
            return;
        }

        foreach ($values as $key => $value) {
            if (in_array($key, ['supporting_source_ids', 'evidence_ids', 'source_ids'], true)) {
                $this->ensureEvidenceIdsExist($value, $knownEvidenceIds);

                continue;
            }

            if (in_array($key, ['supporting_source_refs', 'evidence_refs', 'source_refs'], true)) {
                $this->ensureEvidenceRefsExist($value, $knownEvidenceIds);

                continue;
            }

            if (in_array($key, ['supporting_source_id', 'evidence_id', 'source_id'], true)) {
                $this->ensureEvidenceIdsExist([$value], $knownEvidenceIds);

                continue;
            }

            if (in_array($key, ['supporting_source_ref', 'evidence_ref', 'source_ref'], true)) {
                $this->ensureEvidenceRefsExist([$value], $knownEvidenceIds);

                continue;
            }

            if (in_array($key, ['supporting_quotations', 'supporting_quotes', 'quotations'], true)) {
                $this->ensureSupportingQuotationStructure($value, $knownEvidenceIds);

                continue;
            }

            if (is_array($value)) {
                $this->ensureEvidenceReferenceStructure($value, $knownEvidenceIds);
            }
        }
    }

    /**
     * @param  list<int|string>  $knownEvidenceIds
     */
    private function ensureSupportingQuotationStructure(mixed $quotations, array $knownEvidenceIds): void
    {
        if (! is_array($quotations)) {
            throw new InvalidArgumentException('Supporting quotations must be an array.');
        }

        foreach ($quotations as $quotation) {
            if (! is_array($quotation)) {
                throw new InvalidArgumentException('Supporting quotations must contain objects.');
            }

            $sourceId = $quotation['source_id'] ?? $quotation['evidence_id'] ?? null;
            $sourceRef = $quotation['source_ref'] ?? $quotation['evidence_ref'] ?? null;
            if (is_int($sourceId)) {
                $this->ensureEvidenceIdsExist([$sourceId], $knownEvidenceIds);
            } elseif (is_string($sourceRef) && trim($sourceRef) !== '') {
                $this->ensureEvidenceRefsExist([$sourceRef], $knownEvidenceIds);
            } else {
                throw new InvalidArgumentException('Supporting quotations must reference an integer source ID or response-local source reference.');
            }

            $quote = $quotation['quote'] ?? $quotation['text'] ?? null;
            if (! is_string($quote) || trim($quote) === '' || strlen($quote) > 4000) {
                throw new InvalidArgumentException('Supporting quotations must include bounded non-empty quotation text.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $reference
     * @return list<string>
     */
    private function responseLocalReferenceKeys(array $reference, int $index): array
    {
        $keys = ['sourceReferences.'.$index, 'source:'.$index, (string) $index];
        foreach (['id', 'local_id', 'localId', 'ref', 'reference', 'source_ref', 'sourceRef'] as $field) {
            $value = $reference[$field] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                $keys[] = (string) $value;
            }
        }

        $url = $reference['url'] ?? null;
        if (is_string($url) && $url !== '') {
            $keys[] = $url;
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  list<int|string>  $knownEvidenceIds
     * @param  array<int|string, string|null>  $evidenceTextsById
     */
    private function ensureEvidenceReferencesExist(mixed $values, array $knownEvidenceIds, array $evidenceTextsById): void
    {
        if (! is_array($values)) {
            return;
        }

        foreach ($values as $key => $value) {
            if (in_array($key, ['supporting_source_ids', 'evidence_ids', 'source_ids'], true)) {
                if (! is_array($value)) {
                    throw new InvalidArgumentException('Evidence references must be arrays of source IDs.');
                }
                $this->ensureEvidenceIdsExist($value, $knownEvidenceIds);

                continue;
            }

            if (in_array($key, ['supporting_source_refs', 'evidence_refs', 'source_refs'], true)) {
                if (! is_array($value)) {
                    throw new InvalidArgumentException('Evidence references must be arrays of source references.');
                }
                $this->ensureEvidenceRefsExist($value, $knownEvidenceIds);

                continue;
            }

            if (in_array($key, ['supporting_source_id', 'evidence_id', 'source_id'], true)) {
                $this->ensureEvidenceIdsExist([$value], $knownEvidenceIds);

                continue;
            }

            if (in_array($key, ['supporting_source_ref', 'evidence_ref', 'source_ref'], true)) {
                $this->ensureEvidenceRefsExist([$value], $knownEvidenceIds);

                continue;
            }

            if (in_array($key, ['supporting_quotations', 'supporting_quotes', 'quotations'], true)) {
                $this->ensureSupportingQuotations($value, $knownEvidenceIds, $evidenceTextsById);

                continue;
            }

            if (is_array($value)) {
                $this->ensureEvidenceReferencesExist($value, $knownEvidenceIds, $evidenceTextsById);
            }
        }
    }

    /** @param array<string, mixed> $item */
    private function ensureMaterialResearchSupportIsResolved(array $item, string $collection): void
    {
        $statement = $item['statement'] ?? null;
        if (! is_string($statement) || trim($statement) === '') {
            return;
        }

        $quotations = $item['supporting_quotations'] ?? $item['supporting_quotes'] ?? [];
        $hasQuote = is_array($quotations) && count($quotations) > 0;
        $unresolved = ($item['unresolved'] ?? false) === true
            || is_string($item['unresolved_reason'] ?? null)
            || in_array($item['status'] ?? null, ['unresolved', 'unsupported', 'blocking'], true)
            || (($item['severity'] ?? null) === 'blocking' && $collection === 'contradictions');

        if (! $hasQuote && ! $unresolved) {
            throw new InvalidArgumentException('Material research claims and contradictions require grounded quotations or must remain unresolved/blocking.');
        }
    }

    /**
     * @param  list<int|string>  $knownEvidenceIds
     */
    private function ensureEvidenceIdsExist(mixed $values, array $knownEvidenceIds): void
    {
        if (! is_array($values)) {
            throw new InvalidArgumentException('Evidence references must be arrays of source IDs.');
        }

        foreach ($values as $value) {
            if (! is_int($value)) {
                throw new InvalidArgumentException('Evidence references must be integer source IDs.');
            }

            if (! in_array($value, $knownEvidenceIds, true)) {
                throw new InvalidArgumentException('Agent output referenced an unknown evidence source.');
            }
        }
    }

    /**
     * @param  list<int|string>  $knownEvidenceIds
     */
    private function ensureEvidenceRefsExist(mixed $values, array $knownEvidenceIds): void
    {
        if (! is_array($values)) {
            throw new InvalidArgumentException('Evidence references must be arrays of source references.');
        }

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException('Evidence references must be non-empty source reference strings.');
            }

            if (! in_array($value, $knownEvidenceIds, true)) {
                throw new InvalidArgumentException('Agent output referenced an unknown evidence source.');
            }
        }
    }

    /**
     * @param  list<int|string>  $knownEvidenceIds
     * @param  array<int|string, string|null>  $evidenceTextsById
     */
    private function ensureSupportingQuotations(mixed $quotations, array $knownEvidenceIds, array $evidenceTextsById): void
    {
        if (! is_array($quotations)) {
            throw new InvalidArgumentException('Supporting quotations must be an array.');
        }

        foreach ($quotations as $quotation) {
            if (! is_array($quotation)) {
                throw new InvalidArgumentException('Supporting quotations must contain objects.');
            }

            $sourceId = $quotation['source_id'] ?? $quotation['evidence_id'] ?? null;
            $sourceRef = $quotation['source_ref'] ?? $quotation['evidence_ref'] ?? null;
            $quote = $quotation['quote'] ?? $quotation['text'] ?? null;
            if (is_int($sourceId)) {
                $sourceKey = $sourceId;
                $this->ensureEvidenceIdsExist([$sourceId], $knownEvidenceIds);
            } elseif (is_string($sourceRef) && trim($sourceRef) !== '') {
                $sourceKey = $sourceRef;
                $this->ensureEvidenceRefsExist([$sourceRef], $knownEvidenceIds);
            } else {
                throw new InvalidArgumentException('Supporting quotations must reference an integer source ID or response-local source reference.');
            }

            if (! is_string($quote) || trim($quote) === '') {
                throw new InvalidArgumentException('Supporting quotations must include non-empty quotation text.');
            }

            $sourceText = $evidenceTextsById[$sourceKey] ?? null;
            if (! is_string($sourceText) || trim($sourceText) === '') {
                throw new InvalidArgumentException('Supporting quotation source text is missing.');
            }

            if (! $this->containsQuotation($sourceText, $quote)) {
                throw new InvalidArgumentException('Supporting quotation was not found in the retained source text.');
            }
        }
    }

    /**
     * Quotations must reproduce the source's words in order. Line breaks, repeated spaces and list markers are
     * formatting, so a quote spanning bulleted lines still matches while changed or elided words do not.
     */
    private function containsQuotation(string $sourceText, string $quote): bool
    {
        if (str_contains($sourceText, $quote)) {
            return true;
        }

        $normalizedQuote = $this->withoutFormatting($quote);

        return $normalizedQuote !== '' && str_contains($this->withoutFormatting($sourceText), $normalizedQuote);
    }

    private function withoutFormatting(string $text): string
    {
        $withoutListMarkers = preg_replace('/^[ \t]*(?:[-*•]|\d+[.)])[ \t]+/mu', '', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $withoutListMarkers) ?? $withoutListMarkers);
    }

    /** @param array<string, mixed> $finding */
    private function ensureContradictionsRemainBlocking(array $finding): void
    {
        $quotes = $finding['supporting_quotations'] ?? $finding['supporting_quotes'] ?? [];
        $hasContradiction = false;
        if (is_array($quotes)) {
            foreach ($quotes as $quote) {
                if (is_array($quote) && (($quote['relationship'] ?? null) === 'contradicts' || ($quote['contradicts'] ?? false) === true)) {
                    $hasContradiction = true;
                    break;
                }
            }
        }

        if ($hasContradiction && ($finding['severity'] ?? null) !== 'blocking') {
            throw new InvalidArgumentException('Contradictory evidence must remain blocking.');
        }
    }
}
