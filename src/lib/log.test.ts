import { describe, test, expect } from 'bun:test';
import { errorFields } from './log';

describe('errorFields', () => {
  test('unpacks an Error instance into name, message, stack', () => {
    const err = new Error('boom');
    const fields = errorFields(err);
    expect(fields.name).toBe('Error');
    expect(fields.message).toBe('boom');
    expect(typeof fields.stack).toBe('string');
    expect(fields.stack?.length).toBeGreaterThan(0);
  });

  test('preserves a custom Error subclass name', () => {
    class TimeoutError extends Error {
      override name = 'TimeoutError';
    }
    const fields = errorFields(new TimeoutError('took too long'));
    expect(fields.name).toBe('TimeoutError');
    expect(fields.message).toBe('took too long');
  });

  test('coerces a string thrown value', () => {
    const fields = errorFields('plain string');
    expect(fields.name).toBe('Unknown');
    expect(fields.message).toBe('plain string');
    expect(fields.stack).toBeUndefined();
  });

  test('coerces undefined', () => {
    const fields = errorFields(undefined);
    expect(fields.name).toBe('Unknown');
    expect(fields.message).toBe('undefined');
  });

  test('coerces null', () => {
    const fields = errorFields(null);
    expect(fields.name).toBe('Unknown');
    expect(fields.message).toBe('null');
  });

  test('coerces a non-Error object via String()', () => {
    const fields = errorFields({ a: 1 });
    expect(fields.name).toBe('Unknown');
    expect(fields.message).toBe('[object Object]');
  });

  test('does not throw on a circular object', () => {
    const obj: Record<string, unknown> = { a: 1 };
    obj.self = obj;
    const fields = errorFields(obj);
    expect(fields.name).toBe('Unknown');
    expect(fields.message).toBe('[object Object]');
  });
});
