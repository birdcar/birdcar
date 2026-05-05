/**
 * Wrap an Astro action handler so unanticipated errors get logged via the
 * event-log convention and surfaced to the client as a uniform
 * `INTERNAL_SERVER_ERROR`. Pre-shaped `ActionError` throws pass through
 * unchanged so per-action validation messages still reach the user.
 */
import { ActionError } from 'astro:actions';
import type { ActionAPIContext } from 'astro:actions';
import { logError } from './event-log';

export interface WrapActionOptions {
  /** Event name for the error log entry. */
  event: string;
  /** Message shown to the user when an unexpected error fires. */
  userMessage: string;
}

export function wrapAction<I, O>(
  opts: WrapActionOptions,
  fn: (input: I, ctx: ActionAPIContext) => Promise<O>,
): (input: I, ctx: ActionAPIContext) => Promise<O> {
  return async (input, ctx) => {
    try {
      return await fn(input, ctx);
    } catch (err) {
      if (err instanceof ActionError) throw err;
      logError(opts.event, {}, err);
      throw new ActionError({
        code: 'INTERNAL_SERVER_ERROR',
        message: opts.userMessage,
      });
    }
  };
}
