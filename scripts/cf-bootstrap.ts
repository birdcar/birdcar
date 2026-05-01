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
 * Interactively configures WorkOS AuthKit:
 *   - Prompts for API key + client ID
 *   - Generates a 32-byte cookie-sealing password
 *   - Pushes the four WORKOS_* values as production secrets (wrangler secret put)
 *   - Writes/updates the local .env file so `wrangler dev` works without
 *     a second round of typing
 *
 * Idempotent: re-running after resources exist is a no-op — wrangler returns
 * the existing resource rather than failing. WorkOS prompts can be skipped
 * if you've already set the secrets.
 *
 * Out of scope (handle separately):
 *   - D1 database (`birdcar-leads`) and migrations — run `bun run db:migrate`.
 *   - send_email DNS verification — manual via the Cloudflare dashboard.
 *   - DOs and Workflows — created automatically on first `wrangler deploy`.
 *   - WorkOS dashboard config (creating the AuthKit app, registering the
 *     two redirect URIs, enabling passkeys) — manual one-time setup.
 *
 * Prereqs:
 *   - Logged into wrangler (bunx wrangler login)
 *   - Cloudflare account with Workers/Pages access
 *   - WorkOS AuthKit application created with both redirect URIs registered:
 *       https://birdcar.dev/admin/callback
 *       http://localhost:4321/admin/callback
 *
 * Usage:
 *   bun run cf:bootstrap
 */
import { readFile, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';

const CONFIG_PATH = join(process.cwd(), 'wrangler.jsonc');
const ENV_PATH = join(process.cwd(), '.env');
const ENV_EXAMPLE_PATH = join(process.cwd(), '.env.example');
const NAMESPACE_TITLE = 'birdcar-session';
const QUEUE_NAME = 'lead-triage';
const PROD_REDIRECT_URI = 'https://birdcar.dev/admin/callback';
const DEV_REDIRECT_URI = 'http://localhost:4321/admin/callback';

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

function ask(question: string, options?: { allowEmpty?: boolean }): string {
  const answer = (prompt(question) ?? '').trim();
  if (!answer && !options?.allowEmpty) {
    throw new Error(`Required value not provided: ${question}`);
  }
  return answer;
}

function confirmDefault(question: string, defaultYes: boolean): boolean {
  const suffix = defaultYes ? ' [Y/n]' : ' [y/N]';
  const raw = (prompt(`${question}${suffix}`) ?? '').trim().toLowerCase();
  if (!raw) return defaultYes;
  return raw === 'y' || raw === 'yes';
}

function generateCookiePassword(): string {
  return randomBytes(32).toString('base64');
}

function setSecret(name: string, value: string): void {
  console.log(`→ Setting prod secret ${name}...`);
  const result = spawnSync('bunx', ['wrangler', 'secret', 'put', name], {
    input: value,
    encoding: 'utf8',
    stdio: ['pipe', 'pipe', 'pipe'],
  });
  const stderr = result.stderr?.toString() ?? '';
  if (/Authentication error|code:\s*10000/i.test(stderr)) {
    throw new WranglerAuthError();
  }
  if (result.status !== 0) {
    throw new Error(`wrangler secret put ${name} failed:\n${stderr}`);
  }
  console.log(`✓ ${name} set`);
}

interface WorkOSSecrets {
  apiKey: string;
  clientId: string;
  cookiePassword: string;
}

async function configureWorkOS(): Promise<WorkOSSecrets | null> {
  console.log('\n--- WorkOS AuthKit ---');
  console.log('Pre-reqs (one-time, in https://dashboard.workos.com/):');
  console.log('  - AuthKit application created');
  console.log(`  - Redirect URIs registered: ${PROD_REDIRECT_URI} and ${DEV_REDIRECT_URI}`);
  console.log('  - Passkeys enabled in authentication methods\n');

  if (!confirmDefault('Configure WorkOS now?', true)) {
    console.log('Skipping WorkOS configuration.');
    return null;
  }

  const apiKey = ask('WORKOS_API_KEY (sk_test_... or sk_live_...): ');
  const clientId = ask('WORKOS_CLIENT_ID (client_...): ');
  const cookiePassword = generateCookiePassword();
  console.log('✓ Generated 32-byte WORKOS_COOKIE_PASSWORD');

  if (confirmDefault('Push these as production secrets via wrangler?', true)) {
    setSecret('WORKOS_API_KEY', apiKey);
    setSecret('WORKOS_CLIENT_ID', clientId);
    setSecret('WORKOS_REDIRECT_URI', PROD_REDIRECT_URI);
    setSecret('WORKOS_COOKIE_PASSWORD', cookiePassword);
  } else {
    console.log('Skipped prod secret upload — set them manually with `bunx wrangler secret put` later.');
  }

  return { apiKey, clientId, cookiePassword };
}

/**
 * Write or update the local .env file with the values we just collected.
 * Existing non-WorkOS keys (e.g. S3_*) are preserved — we only replace
 * the four WORKOS_* lines (or append them if missing).
 */
async function writeEnvFile(workos: WorkOSSecrets): Promise<void> {
  let baseContent: string;
  if (existsSync(ENV_PATH)) {
    if (!confirmDefault(`${ENV_PATH} exists. Update WORKOS_* values in place?`, true)) {
      console.log('✓ Leaving .env unchanged');
      return;
    }
    baseContent = await readFile(ENV_PATH, 'utf8');
  } else {
    console.log(`→ Creating ${ENV_PATH} from .env.example`);
    baseContent = await readFile(ENV_EXAMPLE_PATH, 'utf8');
  }

  const replacements: Record<string, string> = {
    WORKOS_API_KEY: workos.apiKey,
    WORKOS_CLIENT_ID: workos.clientId,
    WORKOS_REDIRECT_URI: DEV_REDIRECT_URI,
    WORKOS_COOKIE_PASSWORD: workos.cookiePassword,
  };

  let content = baseContent;
  for (const [key, value] of Object.entries(replacements)) {
    const pattern = new RegExp(`^${key}=.*$`, 'm');
    if (pattern.test(content)) {
      content = content.replace(pattern, `${key}=${value}`);
    } else {
      content += `${content.endsWith('\n') ? '' : '\n'}${key}=${value}\n`;
    }
  }

  await writeFile(ENV_PATH, content, 'utf8');
  console.log(`✓ Wrote ${ENV_PATH}`);
}

async function main(): Promise<void> {
  console.log('Bootstrapping Cloudflare for birdcar.dev\n');
  const prodId = await ensureNamespace(NAMESPACE_TITLE);
  const previewId = await ensureNamespace(`${NAMESPACE_TITLE}_preview`);
  await patchConfig(prodId, previewId);
  await ensureQueue(QUEUE_NAME);

  const workos = await configureWorkOS();
  if (workos) await writeEnvFile(workos);

  console.log('\nNext steps:');
  console.log('  1. Apply D1 migrations to the remote database:');
  console.log('       bun run db:migrate');
  console.log('  2. Verify the send_email sender domain in the Cloudflare dashboard.');
  console.log('  3. Deploy:');
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
