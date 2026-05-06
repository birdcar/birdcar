import { describe, test, expect, mock } from 'bun:test';
import { getTriageAgent } from './agent-stub';
import type { Env } from '../types';

describe('getTriageAgent', () => {
  test('addresses the singleton instance via idFromName("global")', () => {
    const idFromName = mock(() => 'STUB_DO_ID');
    const stub = { call: () => 'stubbed' };
    const get = mock(() => stub);
    const env = {
      LEAD_TRIAGE_AGENT: { idFromName, get },
    } as unknown as Env;

    const result = getTriageAgent(env);

    expect(idFromName).toHaveBeenCalledTimes(1);
    expect(idFromName).toHaveBeenCalledWith('global');
    expect(get).toHaveBeenCalledTimes(1);
    expect(get).toHaveBeenCalledWith('STUB_DO_ID');
    expect(result as unknown).toBe(stub);
  });
});
