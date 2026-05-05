import { defineConfig } from 'vitest/config';
import {
  cloudflarePool,
  cloudflareTest,
} from '@cloudflare/vitest-pool-workers';

// Reuse wrangler.jsonc as the source of truth for bindings (D1, KV, AI, DO,
// Workflow, Queue, Email). Only inject test-only secrets via miniflare bindings
// so workos.ts and friends typecheck and execute without hitting workos.com.
// `WorkersPoolOptions` is declared but not exported by the package; both
// `cloudflareTest` and `cloudflarePool` accept the inferred shape directly.
const workerOptions = {
  // Tests need a worker entry to instantiate the LeadTriageAgent DO and
  // LeadTriageWorkflow. The production entry (src/worker.ts) imports
  // @astrojs/cloudflare's `virtual:astro-cloudflare:config` which only
  // resolves during `astro build`. tests/test-worker.ts re-exports the
  // DO + Workflow classes plus a stub default fetch.
  main: './tests/test-worker.ts',
  wrangler: { configPath: './wrangler.jsonc' },
  miniflare: {
    bindings: {
      WORKOS_API_KEY: 'test_api_key',
      WORKOS_CLIENT_ID: 'client_test',
      WORKOS_REDIRECT_URI: 'http://localhost:4321/admin/callback',
      WORKOS_COOKIE_PASSWORD: 'a'.repeat(32),
    },
  },
};

// `setupFiles` (not `globalSetup`) is required so the setup runs inside the
// pool-workers worker context where `import { env } from 'cloudflare:workers'`
// resolves to the test bindings. `globalSetup` runs in a separate Node process
// that has no access to the worker env, so migrations cannot apply there.
export default defineConfig({
  plugins: [cloudflareTest(workerOptions)],
  test: {
    pool: cloudflarePool(workerOptions),
    include: ['tests/**/*.spec.ts'],
    setupFiles: ['./tests/setup.ts'],
  },
});
