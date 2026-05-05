import { beforeAll, afterEach } from 'vitest';
import { env } from 'cloudflare:workers';

// Drizzle-kit emits the `--> statement-breakpoint` token between statements.
// Split on it so each `prepare(...).run()` gets exactly one statement (D1's
// `prepare()` rejects multi-statement strings).
// Used only by String.split, which doesn't need the /g flag.
const STATEMENT_BREAK = /-->\s*statement-breakpoint\s*/;

/**
 * Apply every migrations/*.sql file in lexicographic order. We deliberately
 * do NOT trust migrations/meta/_journal.json — it lists only the drizzle-kit
 * generated migration (`0000`), but `0001`-`0003` were authored by hand and
 * never journaled. Globbing the directory ensures every SQL file ships into
 * the test database regardless of authoring tool.
 */
async function applyMigrations(): Promise<void> {
  const files = import.meta.glob('../migrations/*.sql', {
    query: '?raw',
    import: 'default',
    eager: true,
  }) as Record<string, string>;

  const ordered = Object.entries(files).sort(([a], [b]) => a.localeCompare(b));

  for (const [path, sql] of ordered) {
    // Only drizzle-kit-generated migrations (e.g. 0000) use the breakpoint;
    // hand-authored ones are single statements that may start with `--`
    // comments. Split on the breakpoint, trim, and keep any chunk that
    // contains real SQL after stripping leading line comments.
    const statements = sql
      .split(STATEMENT_BREAK)
      .map((s) => s.trim())
      .filter((s) => {
        if (s.length === 0) return false;
        const sansLeadingComments = s.replace(/^(?:\s*--[^\n]*\n)+/g, '').trim();
        return sansLeadingComments.length > 0;
      });

    for (const statement of statements) {
      try {
        await env.LEADS_DB.prepare(statement).run();
      } catch (err) {
        const message = err instanceof Error ? err.message : String(err);
        throw new Error(`Migration ${path} failed on statement:\n${statement}\n\n${message}`);
      }
    }
  }
}

beforeAll(async () => {
  await applyMigrations();
});

afterEach(async () => {
  // Per-test isolation: wipe the leads table between tests so workflow/queue
  // tests don't leak state. Schema stays. DO sqlite (agent_activity,
  // pendingApprovals state) is not wiped here — tests that interact with
  // the agent should clear their own DO state in their own `beforeEach`,
  // typically by namespacing with a unique `idFromName(...)` per test or
  // by resetting state inside `runInDurableObject`. `abortAllDurableObjects`
  // would seem cleaner but breaks `vi.mock('ai', ...)` for subsequent tests
  // — pool-workers re-instantiates the worker isolate after abort and the
  // mock factory does not re-apply.
  await env.LEADS_DB.prepare('DELETE FROM leads').run();
});
