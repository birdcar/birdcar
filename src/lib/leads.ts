import { asc, eq, inArray, sql } from 'drizzle-orm';
import type { D1Database, KVNamespace } from '@cloudflare/workers-types';
import { getDb } from '../db/client';
import { leads, type Lead } from '../db/schema';

export interface CloudflareEnv {
  LEADS_DB: D1Database;
  WORKER_PULL_TOKEN?: string;
  SESSION?: KVNamespace;
}

export function getEnv(locals: App.Locals): CloudflareEnv | null {
  return (locals as { runtime?: { env?: CloudflareEnv } }).runtime?.env ?? null;
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

/**
 * Select pending leads, then mark them processing in a separate UPDATE.
 *
 * D1 doesn't support multi-statement transactions, and Drizzle's builder
 * doesn't expose `UPDATE ... WHERE id IN (SELECT ... LIMIT n) RETURNING *`.
 * Two queries with a small race window is fine for a single n8n poller.
 */
export async function claimPendingLeads(
  d1: D1Database,
  limit = 50,
): Promise<Lead[]> {
  const db = getDb(d1);
  const pending = await db
    .select({ id: leads.id })
    .from(leads)
    .where(eq(leads.status, 'pending'))
    .orderBy(asc(leads.submittedAt))
    .limit(limit);
  if (pending.length === 0) return [];
  const ids = pending.map((row) => row.id);
  const claimed = await db
    .update(leads)
    .set({ status: 'processing', updatedAt: sql`(datetime('now'))` })
    .where(inArray(leads.id, ids))
    .returning();
  return claimed;
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
