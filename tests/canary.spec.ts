import { describe, it, expect } from 'vitest';
import { env } from 'cloudflare:workers';
import { eq } from 'drizzle-orm';
import { getDb } from '../src/db/client';
import { leads } from '../src/db/schema';

describe('canary', () => {
  it('all wrangler.jsonc bindings resolve on env', () => {
    expect(env.LEADS_DB).toBeDefined();
    expect(env.AI).toBeDefined();
    expect(env.LEAD_TRIAGE_AGENT).toBeDefined();
    expect(env.LEAD_TRIAGE_WORKFLOW).toBeDefined();
    expect(env.LEAD_TRIAGE_QUEUE).toBeDefined();
    // EMAIL is `remote: true` in wrangler.jsonc — miniflare exposes the
    // binding shape but may not invoke .send locally. Existence is enough.
    expect(env.EMAIL).toBeDefined();
    // SESSION KV is optional in our type augmentation; in tests it should
    // still be defined because wrangler.jsonc declares it.
    expect(env.SESSION).toBeDefined();
  });

  it('WorkOS test secrets are injected', () => {
    expect(env.WORKOS_API_KEY).toBe('test_api_key');
    expect(env.WORKOS_CLIENT_ID).toBe('client_test');
    expect(env.WORKOS_REDIRECT_URI).toBe('http://localhost:4321/admin/callback');
    expect(env.WORKOS_COOKIE_PASSWORD).toHaveLength(32);
  });

  it('migrations applied — D1 round-trip succeeds with default status and timestamps', async () => {
    const db = getDb(env.LEADS_DB);
    await db.insert(leads).values({
      id: 'CANARY_LEAD',
      submittedAt: '2026-05-05T00:00:00Z',
      name: 'Canary',
      email: 'canary@example.com',
      message: 'Smoke test for the test harness.',
      userAgent: null,
      source: 'canary',
    });

    const [row] = await db.select().from(leads).where(eq(leads.id, 'CANARY_LEAD')).limit(1);
    expect(row).toBeDefined();
    expect(row.status).toBe('pending');
    expect(row.source).toBe('canary');
    expect(row.createdAt).toBeTruthy();
    expect(row.updatedAt).toBeTruthy();
  });
});
