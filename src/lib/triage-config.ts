/**
 * Constants for the lead-triage agent + workflow. All knobs in one place
 * so prompt or model changes don't require editing the workflow body.
 */

export const MODELS = {
  classify: '@cf/meta/llama-3.1-8b-instruct',
  qualify: '@cf/meta/llama-3.1-8b-instruct',
  draft: '@cf/meta/llama-3.1-8b-instruct',
  // Swap to '@cf/meta/llama-3.3-70b-instruct-fp8-fast' if draft voice slips.
} as const;

export const STEP_RETRY = {
  classify: { retries: { limit: 3, delay: '5 seconds', backoff: 'exponential' }, timeout: '30 seconds' },
  qualify: { retries: { limit: 3, delay: '5 seconds', backoff: 'exponential' }, timeout: '30 seconds' },
  score: { retries: { limit: 1 }, timeout: '5 seconds' },
  draft: { retries: { limit: 2, delay: '10 seconds', backoff: 'exponential' }, timeout: '2 minutes' },
  persist: { retries: { limit: 3, delay: '2 seconds', backoff: 'exponential' }, timeout: '10 seconds' },
  notify: { retries: { limit: 3, delay: '5 seconds', backoff: 'exponential' }, timeout: '15 seconds' },
} as const;

export const APPROVAL_TIMEOUT = '7 days' as const;

export const SCORE_RULES = {
  industryFit: ['cpa', 'mortgage', 'realtor', 'property-mgmt', 'local-agency'] as const,
  geoStrong: ['fort-worth', 'dfw'] as const,
  geoOk: ['texas'] as const,
  sizeFit: ['small (2-10)', 'medium (10-50)'] as const,
  problemRecognized: ['review-queue', 'system-glue', 'internal-tool', 'reporting'] as const,
} as const;

export const STUCK_ROW_THRESHOLD_MINUTES = 10;
export const SWEEP_CRON = '*/5 * * * *';

export const NOTIFY_TO = 'hi@birdcar.dev';
export const NOTIFY_FROM = { email: 'noreply@birdcar.dev', name: 'Birdcar leads' } as const;
export const REPLY_FROM = { email: 'hi@birdcar.dev', name: 'Nick Cannariato' } as const;
