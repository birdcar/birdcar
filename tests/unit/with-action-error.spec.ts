import { describe, it, expect, vi, beforeEach } from 'vitest';

// astro:actions is a virtual module produced only during `astro build`;
// vitest-pool-workers can't resolve it. Stub ActionError as a real class so
// `instanceof` in `wrapAction` works.
vi.mock('astro:actions', () => ({
  defineAction: (config: { handler: (input: unknown, ctx: unknown) => unknown }) =>
    config.handler,
  ActionError: class ActionError extends Error {
    code: string;
    constructor(opts: { code: string; message: string }) {
      super(opts.message);
      this.code = opts.code;
    }
  },
}));

const { ActionError } = await import('astro:actions');
const { wrapAction } = await import('../../src/lib/with-action-error');

const ctx = {} as Parameters<Parameters<typeof wrapAction>[1]>[1];

beforeEach(() => {
  vi.restoreAllMocks();
});

describe('wrapAction', () => {
  it('returns the wrapped handler result on the happy path', async () => {
    const handler = wrapAction(
      { event: 'demo.ok', userMessage: 'Something failed.' },
      async (input: { x: number }) => input.x + 1,
    );
    expect(await handler({ x: 41 }, ctx)).toBe(42);
  });

  it('re-throws an existing ActionError unchanged', async () => {
    const original = new ActionError({
      code: 'BAD_REQUEST',
      message: 'invalid input',
    });
    const handler = wrapAction(
      { event: 'demo.bad', userMessage: 'Generic message' },
      async () => {
        throw original;
      },
    );
    await expect(handler({}, ctx)).rejects.toBe(original);
  });

  it('wraps a non-ActionError in INTERNAL_SERVER_ERROR with the user message', async () => {
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    const handler = wrapAction(
      { event: 'demo.boom', userMessage: 'Sorry, something went wrong.' },
      async () => {
        throw new Error('upstream timeout');
      },
    );
    try {
      await handler({}, ctx);
      expect.fail('handler should have thrown');
    } catch (err) {
      expect(err).toBeInstanceOf(ActionError);
      const wrapped = err as InstanceType<typeof ActionError>;
      expect(wrapped.code).toBe('INTERNAL_SERVER_ERROR');
      expect(wrapped.message).toBe('Sorry, something went wrong.');
    }
    expect(errorSpy).toHaveBeenCalled();
    const payload = errorSpy.mock.calls[0]?.[0] as Record<string, unknown>;
    expect(payload.event).toBe('demo.boom');
    const error = payload.error as { name: string; message: string };
    expect(error.message).toBe('upstream timeout');
  });

  it('logs via the event-log convention with the configured event name', async () => {
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    const handler = wrapAction(
      { event: 'lead.persist.failed', userMessage: 'Could not save.' },
      async () => {
        throw 'string thrown';
      },
    );
    await expect(handler({}, ctx)).rejects.toBeInstanceOf(ActionError);
    const payload = errorSpy.mock.calls[0]?.[0] as Record<string, unknown>;
    expect(payload.event).toBe('lead.persist.failed');
    const error = payload.error as { name: string; message: string };
    expect(error.name).toBe('Unknown');
    expect(error.message).toBe('string thrown');
  });
});
