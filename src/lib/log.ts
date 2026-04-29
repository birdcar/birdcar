/**
 * Structured logging helpers for Workers Logs.
 *
 * Cloudflare's observability serializes objects passed to console.* directly
 * into the queryable telemetry stream — strings get stored as a single
 * message blob and aren't filterable. Logging objects with a stable
 * `event` key and event-specific fields lets us filter by event name and
 * group by `leadId` / `workflowId` in the dashboard later.
 *
 * `Error` instances don't serialize cleanly via the default JSON path
 * (`name`, `message`, `stack` are non-enumerable), so unpack them
 * explicitly when including an error in a log payload.
 *
 * Event naming convention: `<surface>.<action>[.<outcome>]`. Examples:
 *   `lead.received`, `lead.persist.failed`, `queue.batch.received`,
 *   `queue.dispatched`, `agent.queueLead.ok`, `sweep.pending.retriggered`.
 */
export interface ErrorFields {
  name: string;
  message: string;
  stack?: string;
}

export function errorFields(err: unknown): ErrorFields {
  if (err instanceof Error) {
    return {
      name: err.name,
      message: err.message,
      stack: err.stack,
    };
  }
  return {
    name: 'Unknown',
    message: String(err),
  };
}
