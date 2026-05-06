import { describe, test, expect } from 'bun:test';
import {
  APPROVAL_TIMEOUT,
  MODELS,
  NOTIFY_FROM,
  NOTIFY_TO,
  REPLY_FROM,
  SCORE_RULES,
  STEP_RETRY,
  STUCK_ROW_THRESHOLD_MINUTES,
  SWEEP_CRON,
} from './triage-config';

describe('MODELS', () => {
  test('exposes a model id for every workflow step that calls AI', () => {
    expect(MODELS.classify).toBeTruthy();
    expect(MODELS.qualify).toBeTruthy();
    expect(MODELS.draft).toBeTruthy();
  });

  test('uses Workers AI catalog ids prefixed with @cf/', () => {
    for (const id of [MODELS.classify, MODELS.qualify, MODELS.draft]) {
      expect(id.startsWith('@cf/')).toBe(true);
    }
  });
});

describe('STEP_RETRY', () => {
  test('declares retry config for every step the workflow runs', () => {
    const steps = ['classify', 'qualify', 'score', 'draft', 'persist', 'notify'] as const;
    for (const step of steps) {
      expect(STEP_RETRY[step]).toBeDefined();
      expect(STEP_RETRY[step].retries.limit).toBeGreaterThan(0);
      expect(STEP_RETRY[step].timeout).toBeTruthy();
    }
  });

  test('uses exponential backoff on long-running steps', () => {
    expect(STEP_RETRY.classify.retries.backoff).toBe('exponential');
    expect(STEP_RETRY.qualify.retries.backoff).toBe('exponential');
    expect(STEP_RETRY.draft.retries.backoff).toBe('exponential');
    expect(STEP_RETRY.persist.retries.backoff).toBe('exponential');
    expect(STEP_RETRY.notify.retries.backoff).toBe('exponential');
  });
});

describe('SCORE_RULES', () => {
  const buckets = [
    'industryFit',
    'geoStrong',
    'geoOk',
    'sizeFit',
    'problemRecognized',
  ] as const;

  test('every bucket is a non-empty array of strings', () => {
    for (const bucket of buckets) {
      expect(Array.isArray(SCORE_RULES[bucket])).toBe(true);
      expect(SCORE_RULES[bucket].length).toBeGreaterThan(0);
      for (const value of SCORE_RULES[bucket]) {
        expect(typeof value).toBe('string');
      }
    }
  });
});

describe('top-level invariants', () => {
  test('APPROVAL_TIMEOUT is a duration string', () => {
    expect(APPROVAL_TIMEOUT).toMatch(/\d+\s+(seconds?|minutes?|hours?|days?)/);
  });

  test('SWEEP_CRON is a 5-field cron expression', () => {
    expect(SWEEP_CRON.split(' ')).toHaveLength(5);
  });

  test('STUCK_ROW_THRESHOLD_MINUTES is a positive integer', () => {
    expect(STUCK_ROW_THRESHOLD_MINUTES).toBeGreaterThan(0);
    expect(Number.isInteger(STUCK_ROW_THRESHOLD_MINUTES)).toBe(true);
  });

  test('NOTIFY_TO is an email-shaped string', () => {
    expect(NOTIFY_TO).toMatch(/^[^@\s]+@[^@\s]+\.[^@\s]+$/);
  });

  test('NOTIFY_FROM and REPLY_FROM expose email and name', () => {
    expect(NOTIFY_FROM.email).toMatch(/@/);
    expect(NOTIFY_FROM.name).toBeTruthy();
    expect(REPLY_FROM.email).toMatch(/@/);
    expect(REPLY_FROM.name).toBeTruthy();
  });
});
