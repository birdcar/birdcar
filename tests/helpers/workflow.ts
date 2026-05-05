import { env } from 'cloudflare:workers';
import { eq } from 'drizzle-orm';
import { getDb } from '../../src/db/client';
import { leads, type Lead } from '../../src/db/schema';

/**
 * Poll D1 until a lead reaches the expected status, or fail with the
 * last-seen state. The Cloudflare workflow runs asynchronously; tests need
 * a bounded wait rather than a fixed delay.
 */
export async function waitForLeadStatus(
  id: string,
  status: Lead['status'],
  timeoutMs = 5000,
): Promise<Lead> {
  const db = getDb(env.LEADS_DB);
  const deadline = Date.now() + timeoutMs;
  let last: Lead | undefined;

  while (Date.now() < deadline) {
    const [row] = await db.select().from(leads).where(eq(leads.id, id)).limit(1);
    last = row;
    if (row?.status === status) return row;
    await new Promise((resolve) => setTimeout(resolve, 50));
  }

  throw new Error(
    `waitForLeadStatus(${id}, '${status}') timed out after ${timeoutMs}ms; last seen: ${
      last ? JSON.stringify({ status: last.status, outcome: last.outcome }) : 'no row'
    }`,
  );
}
