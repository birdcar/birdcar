/// <reference types="bun-types" />
/**
 * One-shot Cloudflare bootstrap for birdcar.dev.
 *
 * Provisions every account-level resource the deployed worker needs that
 * isn't auto-created on deploy:
 *   - SESSION KV namespaces (prod + preview), used by Astro Actions for
 *     session-backed action results.
 *   - birdcar-leads D1 database, used by the contact form, agent, and
 *     workflow for lead persistence.
 *   - lead-triage Queue, used by the contact action to dispatch leads to
 *     the triage agent without coupling form latency to agent state.
 *
 * Then writes any newly-issued resource ids back into wrangler.jsonc so
 * subsequent deploys have everything they need.
 *
 * Interactively configures WorkOS AuthKit, per environment:
 *   - Prompts for prod API key + client ID, generates a prod cookie password,
 *     pushes all four as production wrangler secrets
 *   - Prompts for staging/dev API key + client ID, generates a separate
 *     cookie password, writes them to the local .env so `wrangler dev`
 *     works against the staging app
 *   - Offers a "use the same creds for both" shortcut for single-env setups
 *
 * Idempotent: re-running after resources exist is a no-op — wrangler returns
 * the existing resource rather than failing. WorkOS prompts can be skipped
 * if you've already set the secrets.
 *
 * Optionally also runs D1 migrations (delegates to `bun run db:migrate`)
 * once the database id is patched in. The `db:migrate` script stays
 * standalone for incremental migrations after the initial bootstrap.
 *
 * Out of scope (handle separately):
 *   - send_email DNS verification — manual via the Cloudflare dashboard.
 *   - DOs and Workflows — created automatically on first `wrangler deploy`.
 *   - WorkOS dashboard config (creating the AuthKit app, registering the
 *     two redirect URIs, enabling passkeys) — manual one-time setup.
 *
 * Prereqs:
 *   - Logged into wrangler (bunx wrangler login)
 *   - Cloudflare account with Workers/Pages access
 *   - WorkOS AuthKit application(s) created — one per environment if you
 *     want prod/staging isolation. Each app needs its own redirect URI:
 *       Prod app    → https://birdcar.dev/admin/callback
 *       Staging app → http://localhost:4321/admin/callback
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
const D1_DATABASE_NAME = 'birdcar-leads';
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

interface D1ListEntry {
  uuid: string;
  name: string;
}

function listExistingD1Databases(): D1ListEntry[] {
  const out = runWrangler(['d1', 'list', '--json']);
  try {
    const parsed = JSON.parse(out) as D1ListEntry[];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function extractD1Uuid(stdout: string): string | null {
  // `wrangler d1 create` either prints a JSON snippet with `database_id`,
  // a TOML-style binding block, or a "Successfully created" string. Try
  // each shape — first match wins.
  const fromJson = stdout.match(/"database_id":\s*"([0-9a-f-]{36})"/i);
  if (fromJson) return fromJson[1];
  const fromToml = stdout.match(/database_id\s*=\s*"([0-9a-f-]{36})"/i);
  if (fromToml) return fromToml[1];
  const fromCreate = stdout.match(/Created your new D1 database[\s\S]*?"([0-9a-f-]{36})"/i);
  if (fromCreate) return fromCreate[1];
  return null;
}

async function ensureD1Database(name: string): Promise<string> {
  const existing = listExistingD1Databases();
  const found = existing.find((db) => db.name === name);
  if (found) {
    console.log(`✓ D1 database "${name}" already exists (${found.uuid})`);
    return found.uuid;
  }
  console.log(`→ Creating D1 database "${name}"...`);
  const out = runWrangler(['d1', 'create', name]);
  const uuid = extractD1Uuid(out);
  if (!uuid) {
    throw new Error(`Could not parse database uuid from wrangler output:\n${out}`);
  }
  console.log(`✓ Created D1 database "${name}" (${uuid})`);
  return uuid;
}

async function patchD1Id(uuid: string): Promise<void> {
  const raw = await readFile(CONFIG_PATH, 'utf8');
  // Match the `database_id` key inside the d1_databases block. The config
  // only has one D1 binding so a global single-line replace is fine.
  const pattern = /"database_id":\s*"[^"]*"/;
  if (!pattern.test(raw)) {
    console.warn('⚠ wrangler.jsonc has no "database_id" entry to patch — skipping');
    return;
  }
  const next = raw.replace(pattern, `"database_id": "${uuid}"`);
  if (next === raw) {
    console.log('✓ wrangler.jsonc database_id already up to date');
    return;
  }
  await writeFile(CONFIG_PATH, next, 'utf8');
  console.log(`✓ Wrote D1 database_id to ${CONFIG_PATH}`);
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

/**
 * Optional: hand off to the existing `db:migrate` script. Kept as a
 * separate package.json entry so it can also be invoked standalone
 * (e.g. when adding a new migration after the initial bootstrap).
 */
function runMigrations(): boolean {
  if (!confirmDefault('\nApply D1 migrations now (`bun run db:migrate`)?', true)) {
    console.log('Skipped D1 migrations — run `bun run db:migrate` when ready.');
    return false;
  }
  console.log('→ Running `bun run db:migrate`...');
  const result = spawnSync('bun', ['run', 'db:migrate'], {
    stdio: 'inherit',
  });
  if (result.status !== 0) {
    throw new Error(`bun run db:migrate failed (exit ${result.status})`);
  }
  console.log('✓ D1 migrations applied');
  return true;
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

interface WorkOSCreds {
  apiKey: string;
  clientId: string;
  cookiePassword: string;
}

interface WorkOSConfig {
  prod: WorkOSCreds | null;
  local: WorkOSCreds | null;
}

function collectCreds(label: string): WorkOSCreds {
  console.log(`\n  ${label} credentials:`);
  const apiKey = ask(`    WORKOS_API_KEY (sk_test_... or sk_live_...): `);
  const clientId = ask(`    WORKOS_CLIENT_ID (client_...): `);
  const cookiePassword = generateCookiePassword();
  console.log(`    ✓ Generated 32-byte WORKOS_COOKIE_PASSWORD`);
  return { apiKey, clientId, cookiePassword };
}

async function configureWorkOS(): Promise<WorkOSConfig> {
  console.log('\n--- WorkOS AuthKit ---');
  console.log('Pre-reqs (one-time, in https://dashboard.workos.com/):');
  console.log('  - One AuthKit app per environment (prod + staging are separate)');
  console.log(`  - Prod app has ${PROD_REDIRECT_URI} registered`);
  console.log(`  - Staging app has ${DEV_REDIRECT_URI} registered`);
  console.log('  - Passkeys enabled in authentication methods\n');

  if (!confirmDefault('Configure WorkOS now?', true)) {
    console.log('Skipping WorkOS configuration.');
    return { prod: null, local: null };
  }

  const sameForBoth = confirmDefault(
    'Use the same WorkOS credentials for prod and local dev?',
    false,
  );

  if (sameForBoth) {
    const shared = collectCreds('Shared');
    return { prod: shared, local: shared };
  }

  const prod = collectCreds('Production');
  const local = collectCreds('Staging / local dev');
  return { prod, local };
}

function pushProdSecrets(prod: WorkOSCreds): void {
  if (!confirmDefault('\nPush production credentials as wrangler secrets?', true)) {
    console.log('Skipped prod secret upload — set them manually with `bunx wrangler secret put` later.');
    return;
  }
  setSecret('WORKOS_API_KEY', prod.apiKey);
  setSecret('WORKOS_CLIENT_ID', prod.clientId);
  setSecret('WORKOS_REDIRECT_URI', PROD_REDIRECT_URI);
  setSecret('WORKOS_COOKIE_PASSWORD', prod.cookiePassword);
}

/**
 * Write or update the local .env file with the staging/dev creds.
 * Existing non-WorkOS keys (e.g. S3_*) are preserved — we only replace
 * the four WORKOS_* lines (or append them if missing).
 */
async function writeEnvFile(local: WorkOSCreds): Promise<void> {
  let baseContent: string;
  if (existsSync(ENV_PATH)) {
    if (!confirmDefault(`\n${ENV_PATH} exists. Update WORKOS_* values in place?`, true)) {
      console.log('✓ Leaving .env unchanged');
      return;
    }
    baseContent = await readFile(ENV_PATH, 'utf8');
  } else {
    console.log(`→ Creating ${ENV_PATH} from .env.example`);
    baseContent = await readFile(ENV_EXAMPLE_PATH, 'utf8');
  }

  const replacements: Record<string, string> = {
    WORKOS_API_KEY: local.apiKey,
    WORKOS_CLIENT_ID: local.clientId,
    WORKOS_REDIRECT_URI: DEV_REDIRECT_URI,
    WORKOS_COOKIE_PASSWORD: local.cookiePassword,
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
  const d1Uuid = await ensureD1Database(D1_DATABASE_NAME);
  await patchD1Id(d1Uuid);
  await ensureQueue(QUEUE_NAME);

  const migrationsApplied = runMigrations();

  const workos = await configureWorkOS();
  if (workos.prod) pushProdSecrets(workos.prod);
  if (workos.local) await writeEnvFile(workos.local);

  console.log('\nNext steps:');
  let step = 1;
  if (!migrationsApplied) {
    console.log(`  ${step++}. Apply D1 migrations to the remote database:`);
    console.log('       bun run db:migrate');
  }
  console.log(`  ${step++}. Verify the send_email sender domain in the Cloudflare dashboard.`);
  console.log(`  ${step++}. Deploy:`);
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
