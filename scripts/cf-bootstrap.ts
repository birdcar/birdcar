/// <reference types="bun-types" />
/**
 * One-shot Cloudflare bootstrap for birdcar.dev.
 *
 * Provisions the SESSION KV namespaces (production + preview) that Astro
 * Actions uses for session-backed action results, then writes the namespace
 * IDs back into wrangler.jsonc so subsequent deploys have everything they
 * need.
 *
 * Idempotent: re-running after IDs are filled in does nothing destructive —
 * wrangler returns the existing namespace's ID rather than failing.
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
  console.log('\nNext steps:');
  console.log('  1. Set CONTACT_WEBHOOK_URL as a secret:');
  console.log('       bunx wrangler secret put CONTACT_WEBHOOK_URL');
  console.log('  2. Deploy:');
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
