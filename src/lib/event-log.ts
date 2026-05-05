/**
 * Structured event logging for Workers Logs. Wraps `errorFields` so call
 * sites don't reimplement the unpack-Error dance, and gives every payload a
 * stable `event` key that the dashboard can filter on.
 *
 * The event names themselves are operational contract: changing one breaks
 * any saved log query. Keep them stable.
 */
import { errorFields } from './log';

type LogContext = Record<
  string,
  string | number | boolean | null | undefined
>;

export function logEvent(event: string, ctx: LogContext = {}): void {
  console.log({ event, ...ctx });
}

export function logWarn(
  event: string,
  ctx: LogContext = {},
  err?: unknown,
): void {
  console.warn({
    event,
    ...ctx,
    ...(err !== undefined ? { error: errorFields(err) } : {}),
  });
}

export function logError(
  event: string,
  ctx: LogContext,
  err: unknown,
): void {
  console.error({ event, ...ctx, error: errorFields(err) });
}
