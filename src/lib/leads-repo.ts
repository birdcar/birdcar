import type { D1Database } from '@cloudflare/workers-types';
import { and, eq, isNull, lt, sql } from 'drizzle-orm';
import { getDb, type Database } from '../db/client';
import { leads, type Lead, type NewLead } from '../db/schema';

/**
 * Typed repository for the `leads` table. Encapsulates the SQL-and-Drizzle
 * incantations that previously lived inline across the action handler,
 * agent, and workflow.
 *
 * The class is constructed once per request (or once per workflow step)
 * with a D1Database; drizzle-orm/d1 is stateless, so reuse across calls
 * within the same execution context is safe.
 */
export class LeadsRepo {
  private db: Database;

  constructor(d1: D1Database) {
    this.db = getDb(d1);
  }

  async insert(input: NewLead): Promise<void> {
    await this.db.insert(leads).values(input);
  }

  async findById(id: string): Promise<Lead | null> {
    const [row] = await this.db.select().from(leads).where(eq(leads.id, id)).limit(1);
    return row ?? null;
  }

  async patch(id: string, patch: Partial<Lead>): Promise<Lead | null> {
    const [updated] = await this.db
      .update(leads)
      .set({ ...patch, updatedAt: sql`(datetime('now'))` })
      .where(eq(leads.id, id))
      .returning();
    return updated ?? null;
  }

  async markStatus(
    id: string,
    status: Lead['status'],
    outcome?: Lead['outcome'],
  ): Promise<void> {
    await this.db
      .update(leads)
      .set({ status, ...(outcome ? { outcome } : {}) })
      .where(eq(leads.id, id));
  }

  /**
   * Atomically claim the notify-nick slot for a lead. Returns true if the
   * caller won the claim, false if another worker already notified.
   */
  async claimNotificationSlot(id: string): Promise<boolean> {
    const claim = await this.db
      .update(leads)
      .set({ notifiedAt: sql`(datetime('now'))` })
      .where(and(eq(leads.id, id), isNull(leads.notifiedAt)))
      .returning({ id: leads.id });
    return claim.length > 0;
  }

  /** Roll back a notify-nick claim after a failed email send. */
  async releaseNotificationSlot(id: string): Promise<void> {
    await this.db.update(leads).set({ notifiedAt: null }).where(eq(leads.id, id));
  }

  /** Atomically claim the send-reply slot. */
  async claimResponseSlot(id: string): Promise<boolean> {
    const claim = await this.db
      .update(leads)
      .set({ respondedAt: sql`(datetime('now'))` })
      .where(and(eq(leads.id, id), isNull(leads.respondedAt)))
      .returning({ id: leads.id });
    return claim.length > 0;
  }

  /** Roll back a send-reply claim after a failed email send. */
  async releaseResponseSlot(id: string): Promise<void> {
    await this.db.update(leads).set({ respondedAt: null }).where(eq(leads.id, id));
  }

  /**
   * Reset stale `processing` rows back to `pending`. Cutoff is an ISO 8601
   * timestamp; rows whose `updatedAt` is older are considered stalled.
   * Returns the IDs that were reset.
   */
  async resetStaleProcessing(cutoffIso: string): Promise<{ id: string }[]> {
    return this.db
      .update(leads)
      .set({ status: 'pending' })
      .where(and(eq(leads.status, 'processing'), lt(leads.updatedAt, cutoffIso)))
      .returning({ id: leads.id });
  }

  /**
   * Find `pending` rows whose `submittedAt` is older than the cutoff. The
   * caller decides what to do with them (typically: re-trigger the workflow).
   */
  async findStalePending(cutoffIso: string): Promise<{ id: string }[]> {
    return this.db
      .select({ id: leads.id })
      .from(leads)
      .where(and(eq(leads.status, 'pending'), lt(leads.submittedAt, cutoffIso)));
  }
}
