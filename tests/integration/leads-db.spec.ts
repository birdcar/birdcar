import { describe, it, expect } from 'vitest';
import { env } from 'cloudflare:workers';
import { getDb } from '../../src/db/client';
import { leads } from '../../src/db/schema';
import { getLeadById, insertLead, patchLead } from '../../src/lib/leads';

describe('insertLead', () => {
  it('populates source default when caller omits it', async () => {
    await insertLead(env.LEADS_DB, {
      id: 'L_DEFAULT_SOURCE',
      submittedAt: '2026-05-05T00:00:00Z',
      name: 'A',
      email: 'a@b.co',
      message: 'A message that needs replying to.',
      userAgent: null,
    });
    const row = await getLeadById(env.LEADS_DB, 'L_DEFAULT_SOURCE');
    expect(row?.source).toBe('birdcar.dev/contact');
  });

  it('honors explicit source', async () => {
    await insertLead(env.LEADS_DB, {
      id: 'L_CUSTOM_SOURCE',
      submittedAt: '2026-05-05T00:00:00Z',
      name: 'A',
      email: 'a@b.co',
      message: 'Another message that needs replying to.',
      userAgent: null,
      source: 'tests',
    });
    const row = await getLeadById(env.LEADS_DB, 'L_CUSTOM_SOURCE');
    expect(row?.source).toBe('tests');
  });
});

describe('getLeadById', () => {
  it('returns null when the row is missing — does not throw', async () => {
    const row = await getLeadById(env.LEADS_DB, 'NOT_A_REAL_ID');
    expect(row).toBeNull();
  });
});

describe('patchLead', () => {
  it('returns the updated row with the new fields', async () => {
    await insertLead(env.LEADS_DB, {
      id: 'L_PATCH',
      submittedAt: '2026-05-05T00:00:00Z',
      name: 'A',
      email: 'a@b.co',
      message: 'Message body.',
      userAgent: null,
    });

    const updated = await patchLead(env.LEADS_DB, 'L_PATCH', {
      status: 'processing',
      score: 5,
    });
    expect(updated?.status).toBe('processing');
    expect(updated?.score).toBe(5);
  });

  it('returns null when patching a missing row', async () => {
    const updated = await patchLead(env.LEADS_DB, 'NOT_A_REAL_ID', { status: 'done' });
    expect(updated).toBeNull();
  });

  it('refreshes updated_at on patch (trigger fires)', async () => {
    await insertLead(env.LEADS_DB, {
      id: 'L_PATCH_TS',
      submittedAt: '2026-05-05T00:00:00Z',
      name: 'A',
      email: 'a@b.co',
      message: 'Message body.',
      userAgent: null,
    });
    const original = await getLeadById(env.LEADS_DB, 'L_PATCH_TS');
    await new Promise((resolve) => setTimeout(resolve, 1100)); // datetime('now') has 1s resolution
    const updated = await patchLead(env.LEADS_DB, 'L_PATCH_TS', { score: 3 });
    expect(updated?.updatedAt).not.toBe(original?.updatedAt);
  });
});

describe('schema invariants', () => {
  // Drizzle's `enum` is type-level only — SQLite has no CHECK constraint on
  // this column. A future migration could add CHECK; this `.fails` test will
  // start passing the day it lands and signals the schema fix.
  it.fails('rejects an insert with a status outside the enum', async () => {
    const db = getDb(env.LEADS_DB);
    await expect(
      db.insert(leads).values({
        id: 'L_BAD_STATUS',
        submittedAt: '2026-05-05T00:00:00Z',
        name: 'A',
        email: 'a@b.co',
        message: 'Message body.',
        userAgent: null,
        status: 'invalid' as never,
      }),
    ).rejects.toThrow();
  });

  it('handles concurrent patches without losing both updates (last-write-wins)', async () => {
    await insertLead(env.LEADS_DB, {
      id: 'L_CONCURRENT',
      submittedAt: '2026-05-05T00:00:00Z',
      name: 'A',
      email: 'a@b.co',
      message: 'Concurrent patch test message body.',
      userAgent: null,
    });

    const [a, b] = await Promise.all([
      patchLead(env.LEADS_DB, 'L_CONCURRENT', { score: 1 }),
      patchLead(env.LEADS_DB, 'L_CONCURRENT', { score: 2 }),
    ]);
    // Both calls resolve (no exception thrown).
    expect(a).toBeTruthy();
    expect(b).toBeTruthy();
    // Final value is one of the two — last-write-wins.
    const final = await getLeadById(env.LEADS_DB, 'L_CONCURRENT');
    expect([1, 2]).toContain(final?.score);
  });
});
