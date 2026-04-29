// Custom worker entry. The Astro Cloudflare adapter defaults `main` to
// `@astrojs/cloudflare/entrypoints/server`, which only exports a default
// fetch handler. Miniflare's DO wrapper does `import * as mod from <main>`
// and reads `mod.LeadTriageAgent` / `mod.LeadTriageWorkflow` at sync time —
// without those named exports, sync throws `Class extends undefined`.
//
// Wired in via `main: "src/worker.ts"` in wrangler.jsonc; the adapter's
// config customizer respects a user-set `main` and skips its default.
import server from '@astrojs/cloudflare/entrypoints/server';

export { LeadTriageAgent } from './agents/lead-triage-agent';
export { LeadTriageWorkflow } from './workflows/lead-triage-workflow';
export default server;
