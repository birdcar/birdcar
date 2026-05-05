// Phase 1 skeleton — Phase 2 populates with workflow-state fixtures.
// Builders return canonical lead rows in each status the tests need.
import type { NewLead } from '../../src/db/schema';

const BASE: NewLead = {
  id: 'L_TEST_BASE',
  submittedAt: '2026-01-01T00:00:00Z',
  name: 'Test Lead',
  email: 'lead@example.com',
  message: 'I would like to talk about a problem we have.',
  userAgent: null,
  source: 'tests',
};

export const lead = {
  pending: (overrides: Partial<NewLead> = {}): NewLead => ({
    ...BASE,
    ...overrides,
    id: overrides.id ?? `L_PENDING_${cryptoSafeId()}`,
    status: 'pending',
  }),
  processing: (overrides: Partial<NewLead> = {}): NewLead => ({
    ...BASE,
    ...overrides,
    id: overrides.id ?? `L_PROCESSING_${cryptoSafeId()}`,
    status: 'processing',
  }),
  awaitingApproval: (overrides: Partial<NewLead> = {}): NewLead => ({
    ...BASE,
    ...overrides,
    id: overrides.id ?? `L_AWAITING_${cryptoSafeId()}`,
    status: 'awaiting-approval',
  }),
  done: (overrides: Partial<NewLead> = {}): NewLead => ({
    ...BASE,
    ...overrides,
    id: overrides.id ?? `L_DONE_${cryptoSafeId()}`,
    status: 'done',
    outcome: overrides.outcome ?? 'approved',
  }),
};

function cryptoSafeId(): string {
  return crypto.randomUUID().replace(/-/g, '').slice(0, 8);
}
