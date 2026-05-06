import { describe, test, expect } from 'bun:test';
import { ClassifyOutput, QualifyOutput } from './ai-types';

describe('ClassifyOutput', () => {
  test('parses a well-formed payload', () => {
    const parsed = ClassifyOutput.parse({
      category: 'consulting-fit',
      confidence: 0.82,
      reasoning: 'CPA mentions a manual review queue.',
    });
    expect(parsed.category).toBe('consulting-fit');
    expect(parsed.confidence).toBeCloseTo(0.82);
  });

  test('accepts every documented category', () => {
    const categories = [
      'consulting-fit',
      'consulting-off-fit',
      'support-question',
      'vendor-pitch',
      'recruiting',
      'spam',
    ] as const;
    for (const category of categories) {
      const result = ClassifyOutput.safeParse({
        category,
        confidence: 0.5,
        reasoning: 'r',
      });
      expect(result.success).toBe(true);
    }
  });

  test('rejects an unknown category', () => {
    const result = ClassifyOutput.safeParse({
      category: 'something-else',
      confidence: 0.5,
      reasoning: 'r',
    });
    expect(result.success).toBe(false);
  });

  test('rejects confidence above 1', () => {
    const result = ClassifyOutput.safeParse({
      category: 'spam',
      confidence: 1.2,
      reasoning: 'r',
    });
    expect(result.success).toBe(false);
  });

  test('rejects negative confidence', () => {
    const result = ClassifyOutput.safeParse({
      category: 'spam',
      confidence: -0.1,
      reasoning: 'r',
    });
    expect(result.success).toBe(false);
  });

  test('rejects reasoning above the 500-char ceiling', () => {
    const result = ClassifyOutput.safeParse({
      category: 'spam',
      confidence: 0.5,
      reasoning: 'x'.repeat(501),
    });
    expect(result.success).toBe(false);
  });

  test('round-trips through JSON.stringify / parse', () => {
    const original = ClassifyOutput.parse({
      category: 'support-question',
      confidence: 0.4,
      reasoning: 'OSS issue follow-up',
    });
    const restored = ClassifyOutput.parse(JSON.parse(JSON.stringify(original)));
    expect(restored).toEqual(original);
  });
});

describe('QualifyOutput', () => {
  test('parses a well-formed payload', () => {
    const parsed = QualifyOutput.parse({
      industry: 'cpa',
      geography: 'fort-worth',
      size_signal: 'small-2-10',
      problem_shape: 'review-queue',
      urgency_signal: 'in-pain',
    });
    expect(parsed.industry).toBe('cpa');
    expect(parsed.urgency_signal).toBe('in-pain');
  });

  test('rejects payloads missing a required field', () => {
    const result = QualifyOutput.safeParse({
      industry: 'cpa',
      geography: 'fort-worth',
      size_signal: 'small-2-10',
      problem_shape: 'review-queue',
      // urgency_signal omitted
    });
    expect(result.success).toBe(false);
  });

  test('rejects unknown industry', () => {
    const result = QualifyOutput.safeParse({
      industry: 'restaurant',
      geography: 'fort-worth',
      size_signal: 'solo',
      problem_shape: 'other',
      urgency_signal: 'unknown',
    });
    expect(result.success).toBe(false);
  });

  test('round-trips through JSON.stringify / parse', () => {
    const original = QualifyOutput.parse({
      industry: 'mortgage',
      geography: 'dfw',
      size_signal: 'medium-10-50',
      problem_shape: 'system-glue',
      urgency_signal: 'scope-clear',
    });
    const restored = QualifyOutput.parse(JSON.parse(JSON.stringify(original)));
    expect(restored).toEqual(original);
  });
});
