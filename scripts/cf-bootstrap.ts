/// <reference types="bun-types" />
/**
 * One-shot Cloudflare bootstrap for birdcar.dev.
 *
 * Provisions every account-level resource the deployed worker needs that
 * isn't auto-created on deploy:
 *   - SESSION KV namespaces (prod + preview), used by Astro Actions for
 *     session-backed action results.
 *   - lead-triage Queue, used by the contact action to dispatch leads to
 *     the triage agent without coupling form latency to agent state.
 *
 * Then writes any newly-issued KV ids back into wrangler.jsonc so subsequent
 * deploys have everything they need.
 *
 * Idempotent: re-running after resources exist is a no-op — wrangler returns
 * the existing resource rather than failing.
 *
 * Out of scope (handle separately):
 *   - D1 database (`birdcar-leads`) and migrations — run `bun run db:migrate`.
 *   - send_email DNS verification — manual via the Cloudflare dashboard.
 *   - DOs and Workflows — created automatically on first `wrangler deploy`.
 *
 * Prereqs:
 *   - Logged into wrangler (bunx wrangler login)
 *   - Cloudflare account with Workers/Pages access
 *
 * Usage:
 *   bun run cf:bootstrap
 */
import { readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';

const CONFIG_PATH = join(process.cwd(), 'wrangler.jsonc');
const NAMESPACE_TITLE = 'birdcar-session';
const QUEUE_NAME = 'lead-triage';

interface NamespaceCreateResult {
  id: string;
  title: string;
}

class WranglerAuthError extends Error {
  constructor() {
    super(
      [
        '',
        'Wrangler is not authenticated.',
        '',
        'Log in once with:',
        '  bunx wrangler login',
        '',
        'That opens a browser OAuth flow. Then re-run:',
        '  bun run cf:bootstrap',
        '',
      ].join('\n'),
    );
    this.name = 'WranglerAuthError';
  }
}

function runWrangler(args: string[]): string {
  const result = spawnSync('bunx', ['wrangler', ...args], {
    encoding: 'utf8',
    stdio: ['inherit', 'pipe', 'pipe'],
  });
  const stderr = result.stderr?.toString() ?? '';
  if (/Authentication error|code:\s*10000/i.test(stderr)) {
    throw new WranglerAuthError();
  }
  if (result.status !== 0) {
    throw new Error(`wrangler ${args.join(' ')} failed:\n${stderr}`);
  }
  return result.stdout?.toString() ?? '';
}

function extractNamespaceId(stdout: string): string | null {
  // wrangler kv namespace create prints a JSON-ish snippet:
  //   { binding = "SESSION", id = "abcdef..." }
  // or, on newer versions, JSON proper. Try both.
  const idMatch = stdout.match(/"id":\s*"([a-f0-9]{32,})"/i)
    ?? stdout.match(/id\s*=\s*"([a-f0-9]{32,})"/i);
  return idMatch?.[1] ?? null;
}

async function listExistingNamespaces(): Promise<NamespaceCreateResult[]> {
  const out = runWrangler(['kv', 'namespace', 'list']);
  try {
    const parsed = JSON.parse(out) as NamespaceCreateResult[];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

async function ensureNamespace(title: string): Promise<string> {
  const existing = await listExistingNamespaces();
  const found = existing.find((ns) => ns.title === title);
  if (found) {
    console.log(`✓ KV namespace "${title}" already exists (${found.id})`);
    return found.id;
  }
  console.log(`→ Creating KV namespace "${title}"...`);
  const out = runWrangler(['kv', 'namespace', 'create', title]);
  const id = extractNamespaceId(out);
  if (!id) {
    throw new Error(`Could not parse namespace id from wrangler output:\n${out}`);
  }
  console.log(`✓ Created "${title}" (${id})`);
  return id;
}

interface QueueListEntry {
  queue_name?: string;
  name?: string;
}

async function listExistingQueues(): Promise<string[]> {
  const out = runWrangler(['queues', 'list']);
  try {
    const parsed = JSON.parse(out) as QueueListEntry[];
    if (!Array.isArray(parsed)) return [];
    return parsed
      .map((q) => q.queue_name ?? q.name)
      .filter((n): n is string => typeof n === 'string');
  } catch {
    return [];
  }
}

async function ensureQueue(name: string): Promise<void> {
  const existing = await listExistingQueues();
  if (existing.includes(name)) {
    console.log(`✓ Queue "${name}" already exists`);
    return;
  }
  console.log(`→ Creating queue "${name}"...`);
  runWrangler(['queues', 'create', name]);
  console.log(`✓ Created queue "${name}"`);
}

async function patchConfig(prodId: string, previewId: string): Promise<void> {
  const raw = await readFile(CONFIG_PATH, 'utf8');
  const next = raw
    .replace(/"id":\s*"TODO_PROD_KV_ID"/, `"id": "${prodId}"`)
    .replace(/"preview_id":\s*"TODO_PREVIEW_KV_ID"/, `"preview_id": "${previewId}"`);
  if (next === raw) {
    console.log('✓ wrangler.jsonc already up to date');
    return;
  }
  await writeFile(CONFIG_PATH, next, 'utf8');
  console.log(`✓ Wrote KV ids to ${CONFIG_PATH}`);
}

async function main(): Promise<void> {
  console.log('Bootstrapping Cloudflare for birdcar.dev\n');
  const prodId = await ensureNamespace(NAMESPACE_TITLE);
  const previewId = await ensureNamespace(`${NAMESPACE_TITLE}_preview`);
  await patchConfig(prodId, previewId);
  await ensureQueue(QUEUE_NAME);
  console.log('\nNext steps:');
  console.log('  1. Apply D1 migrations to the remote database:');
  console.log('       bun run db:migrate');
  console.log('  2. Set the WorkOS AuthKit secrets (admin dashboard auth gate):');
  console.log('       bunx wrangler secret put WORKOS_API_KEY');
  console.log('       bunx wrangler secret put WORKOS_CLIENT_ID');
  console.log('       bunx wrangler secret put WORKOS_REDIRECT_URI');
  console.log('       openssl rand -base64 32 | bunx wrangler secret put WORKOS_COOKIE_PASSWORD');
  console.log('  3. Verify the send_email sender domain in the Cloudflare dashboard.');
  console.log('  4. Deploy:');
  console.log('       bun run build && bunx wrangler deploy');
}

main().catch((err) => {
  if (err instanceof WranglerAuthError) {
    console.error(err.message);
  } else {
    console.error('cf-bootstrap failed:', err);
  }
  process.exit(1);
});
