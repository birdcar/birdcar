import { describe, it, expect } from 'vitest';
import { getCloudflareEnv } from '../../src/lib/env';

describe('getCloudflareEnv', () => {
  it('returns the runtime env when cloudflare:workers is available', async () => {
    const result = await getCloudflareEnv();
    expect(result).not.toBeNull();
    // miniflare bindings injected via vitest.config.ts are present here.
    expect((result as { WORKOS_CLIENT_ID?: string }).WORKOS_CLIENT_ID).toBe(
      'client_test',
    );
  });

  it('exposes the LEADS_DB binding (so callers can pass it to LeadsRepo)', async () => {
    const result = await getCloudflareEnv();
    expect(result).not.toBeNull();
    expect((result as { LEADS_DB?: unknown }).LEADS_DB).toBeDefined();
  });
});
