import { eq, sql } from 'drizzle-orm';
import type { D1Database } from '@cloudflare/workers-types';
import { getDb } from '../db/client';
import { leads, type Lead } from '../db/schema';
import type { Env } from '../types';

/**
 * Re-export of the runtime env type for code paths that previously imported
 * `CloudflareEnv` from this file. Kept as a type alias for transitional
 * compatibility; new code should import `Env` from `../types` directly.
 */
export type CloudflareEnv = Env;

/**
 * Resolve the runtime Cloudflare env. Astro 6 removed `Astro.locals.runtime.env`;
 * the supported pattern is `import { env } from 'cloudflare:workers'`.
 *
 * Imported lazily so this module can be transitively pulled into the
 * Node-prerender bundle (via Astro's action manifest) without crashing
 * the build — `cloudflare:workers` is workerd-only and Node's loader
 * rejects the scheme. The dynamic import means the resolution only
 * happens when the function is actually called, which is at request
 * time inside the deployed worker.
 *
 * The `locals` parameter is kept on the signature for call-site readability;
 * callers still pass it and we ignore it.
 */
export async function getEnv(_locals: App.Locals): Promise<Env | null> {
  const { env } = await import('cloudflare:workers');
  return (env as Env) ?? null;
}

export async function insertLead(
  d1: D1Database,
  input: {
    id: string;
    submittedAt: string;
    name: string;
    email: string;
    message: string;
    userAgent: string | null;
    source?: string;
  },
): Promise<void> {
  const db = getDb(d1);
  await db.insert(leads).values({
    id: input.id,
    submittedAt: input.submittedAt,
    name: input.name,
    email: input.email,
    message: input.message,
    userAgent: input.userAgent ?? null,
    source: input.source ?? 'birdcar.dev/contact',
  });
}

export async function getLeadById(
  d1: D1Database,
  id: string,
): Promise<Lead | null> {
  const db = getDb(d1);
  const [row] = await db.select().from(leads).where(eq(leads.id, id)).limit(1);
  return row ?? null;
}

export type LeadPatch = Partial<
  Pick<
    Lead,
    'status' | 'category' | 'qualification' | 'score' | 'draft' | 'outcome' | 'respondedAt'
  >
>;

export async function patchLead(
  d1: D1Database,
  id: string,
  patch: LeadPatch,
): Promise<Lead | null> {
  const db = getDb(d1);
  const updated = await db
    .update(leads)
    .set({ ...patch, updatedAt: sql`(datetime('now'))` })
    .where(eq(leads.id, id))
    .returning();
  return updated[0] ?? null;
}
