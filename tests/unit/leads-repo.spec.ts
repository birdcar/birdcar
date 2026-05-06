import { describe, it, expect } from 'vitest';
import { env } from 'cloudflare:workers';
import { LeadsRepo } from '../../src/lib/leads-repo';

const baseLead = (id: string) => ({
  id,
  submittedAt: '2026-05-05T00:00:00Z',
  name: 'Alex',
  email: 'alex@example.com',
  message: 'A test message that has plenty of content for the body.',
  userAgent: null,
  source: 'tests',
});

describe('LeadsRepo.insert + findById', () => {
  it('round-trips a row by id', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    await repo.insert(baseLead('R_INSERT'));
    const row = await repo.findById('R_INSERT');
    expect(row).not.toBeNull();
    expect(row?.email).toBe('alex@example.com');
    expect(row?.status).toBe('pending');
  });

  it('returns null when the id is unknown', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    expect(await repo.findById('NOT_A_REAL_ID')).toBeNull();
  });
});

describe('LeadsRepo.patch', () => {
  it('returns the updated row with the new fields', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    await repo.insert(baseLead('R_PATCH'));
    const updated = await repo.patch('R_PATCH', { status: 'processing', score: 5 });
    expect(updated?.status).toBe('processing');
    expect(updated?.score).toBe(5);
  });

  it('returns null when patching a missing row', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    expect(await repo.patch('NOT_A_REAL_ID', { score: 1 })).toBeNull();
  });
});

describe('LeadsRepo.markStatus', () => {
  it('updates status and outcome together', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    await repo.insert(baseLead('R_MARK'));
    await repo.markStatus('R_MARK', 'done', 'approved');
    const row = await repo.findById('R_MARK');
    expect(row?.status).toBe('done');
    expect(row?.outcome).toBe('approved');
  });

  it('updates only status when outcome is omitted', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    await repo.insert(baseLead('R_MARK_ONLY_STATUS'));
    await repo.markStatus('R_MARK_ONLY_STATUS', 'processing');
    const row = await repo.findById('R_MARK_ONLY_STATUS');
    expect(row?.status).toBe('processing');
    expect(row?.outcome).toBeNull();
  });
});

describe('notification slot claim/release', () => {
  it('returns true on first claim and false on second claim', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    await repo.insert(baseLead('R_NOTIFY'));
    expect(await repo.claimNotificationSlot('R_NOTIFY')).toBe(true);
    expect(await repo.claimNotificationSlot('R_NOTIFY')).toBe(false);
  });

  it('allows reclaim after release', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    await repo.insert(baseLead('R_NOTIFY_RELEASE'));
    expect(await repo.claimNotificationSlot('R_NOTIFY_RELEASE')).toBe(true);
    await repo.releaseNotificationSlot('R_NOTIFY_RELEASE');
    expect(await repo.claimNotificationSlot('R_NOTIFY_RELEASE')).toBe(true);
  });

  it('returns false when claiming on an unknown id', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    expect(await repo.claimNotificationSlot('UNKNOWN')).toBe(false);
  });
});

describe('response slot claim/release', () => {
  it('returns true on first claim and false on second claim', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    await repo.insert(baseLead('R_RESP'));
    expect(await repo.claimResponseSlot('R_RESP')).toBe(true);
    expect(await repo.claimResponseSlot('R_RESP')).toBe(false);
  });

  it('allows reclaim after release', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    await repo.insert(baseLead('R_RESP_RELEASE'));
    expect(await repo.claimResponseSlot('R_RESP_RELEASE')).toBe(true);
    await repo.releaseResponseSlot('R_RESP_RELEASE');
    expect(await repo.claimResponseSlot('R_RESP_RELEASE')).toBe(true);
  });
});

describe('resetStaleProcessing', () => {
  it('resets only processing rows older than the cutoff', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    // Insert two processing rows with different updated_at values.
    await repo.insert(baseLead('R_STALE'));
    await repo.markStatus('R_STALE', 'processing');
    // The default updated_at is datetime('now'); seed an older value via patch.
    // We can't backdate, so use a future cutoff to capture both, then a past
    // cutoff to capture neither.
    const futureCutoff = '2099-01-01T00:00:00Z';
    const reset = await repo.resetStaleProcessing(futureCutoff);
    expect(reset.map((r) => r.id)).toContain('R_STALE');

    const row = await repo.findById('R_STALE');
    expect(row?.status).toBe('pending');
  });

  it('is idempotent — running twice returns no rows on the second pass', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    await repo.insert(baseLead('R_STALE_IDEMP'));
    await repo.markStatus('R_STALE_IDEMP', 'processing');
    const futureCutoff = '2099-01-01T00:00:00Z';
    await repo.resetStaleProcessing(futureCutoff);
    const second = await repo.resetStaleProcessing(futureCutoff);
    expect(second).toEqual([]);
  });

  it('does not touch rows with other statuses', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    await repo.insert(baseLead('R_DONE'));
    await repo.markStatus('R_DONE', 'done');
    const futureCutoff = '2099-01-01T00:00:00Z';
    const reset = await repo.resetStaleProcessing(futureCutoff);
    expect(reset.map((r) => r.id)).not.toContain('R_DONE');
  });
});

describe('findStalePending', () => {
  it('returns pending rows submitted before the cutoff', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    await repo.insert({ ...baseLead('R_OLD'), submittedAt: '2024-01-01T00:00:00Z' });
    await repo.insert({ ...baseLead('R_NEW'), submittedAt: '2099-01-01T00:00:00Z' });
    const cutoff = '2030-01-01T00:00:00Z';
    const stale = await repo.findStalePending(cutoff);
    const ids = stale.map((r) => r.id);
    expect(ids).toContain('R_OLD');
    expect(ids).not.toContain('R_NEW');
  });

  it('returns nothing when no pending rows exist before the cutoff', async () => {
    const repo = new LeadsRepo(env.LEADS_DB);
    const stale = await repo.findStalePending('1900-01-01T00:00:00Z');
    expect(stale).toEqual([]);
  });
});
