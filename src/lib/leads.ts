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

export function getEnv(locals: App.Locals): Env | null {
  return (locals as { runtime?: { env?: Env } }).runtime?.env ?? null;
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
