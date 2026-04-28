# Implementation Spec: Lead Triage Agent — Phase 2

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Phase 2 layers the human surface on top of the Phase 1 backend. Two cuts:

1. **WorkOS AuthKit middleware + auth routes.** Astro middleware checks for a sealed `wos-session` cookie on every request to `/admin/*` or `/agents/*` (the WebSocket upgrade path the dashboard uses to talk to the agent). Unauthenticated browsers redirect to `/admin/login`, which redirects to the AuthKit hosted login URL. The callback route exchanges the auth code for a sealed session, sets the cookie, and redirects to the original URL. Logout clears the cookie and redirects to AuthKit's logout URL. Passkeys are enabled in the WorkOS dashboard.

2. **Admin dashboard pages + vanilla-JS WebSocket client.** Three Astro SSR pages: `/admin/leads` (list of pending approvals), `/admin/leads/[id]` (single lead detail with full draft + Approve/Edit/Discard buttons), `/admin/activity` (recent workflow activity table). Each page hydrates a small vanilla-JS module that uses `AgentClient` from `agents/client` to subscribe to agent state in real-time. Approve/Edit/Discard buttons fire callable methods on the agent over WebSocket; the agent calls `approveWorkflow`/`rejectWorkflow`, the workflow resumes from `waitForApproval`, and the reply email goes out.

The Phase 1 backend already handles every state transition; Phase 2 just provides the HTTP and WebSocket surface for a human to drive it. No schema changes, no new bindings beyond the WorkOS secrets, no changes to the agent or workflow classes.

## Feedback Strategy

**Inner-loop command**: `bun run check`

**Playground**: `bunx wrangler dev --remote` for the full stack including AuthKit redirects and DO WebSocket connections. AuthKit sessions in dev require a localhost redirect URI registered in the WorkOS application settings (`http://localhost:4321/admin/callback`). The dashboard page hot-reloads via Astro's dev server; the agent state pushes update through the live WebSocket.

**Why this approach**: AuthKit redirects are HTTP-shaped; the fastest validation is a real browser hitting `/admin/leads` and seeing the redirect → AuthKit page → callback → dashboard. The WebSocket sync is "open the page, submit a contact form in another tab, watch the dashboard list update" — visible in the same browser window. No new test infrastructure needed.

## File Changes

### New Files

| File Path | Purpose |
|---|---|
| `src/lib/workos.ts` | Wrapper around `@workos-inc/node` SDK: `getAuthorizationUrl`, `authenticateWithCode`, `loadSealedSession`, helpers for cookie set/clear |
| `src/pages/admin/login.astro` | Server-rendered redirect to AuthKit hosted login URL |
| `src/pages/admin/callback.ts` | Exchange `?code=` for sealed session, set cookie, redirect to original URL |
| `src/pages/admin/logout.ts` | Clear cookie, redirect to AuthKit logout URL |
| `src/pages/admin/index.astro` | Dashboard landing — links to /admin/leads and /admin/activity |
| `src/pages/admin/leads/index.astro` | Pending approvals list (server-rendered shell + client hydration) |
| `src/pages/admin/leads/[id].astro` | Single lead detail with draft + action buttons |
| `src/pages/admin/activity/index.astro` | Recent workflow activity (rendered from `agent.getRecentActivity()` server-side) |
| `src/scripts/admin-client.ts` | Shared: instantiate `AgentClient` connected to `LEAD_TRIAGE` agent named "global" |
| `src/scripts/admin-leads-list.ts` | List view: subscribe to `state.pendingApprovals`, render rows via DOM nodes |
| `src/scripts/admin-lead-detail.ts` | Detail view: handle Approve/Edit/Discard button clicks |
| `src/styles/admin.css` | Admin-only chrome — status pills, activity table styles, draft viewer; uses existing design tokens |

### Modified Files

| File Path | Changes |
|---|---|
| `src/middleware.ts` | Add auth gate: on `/admin/*` and `/agents/*`, validate `wos-session` cookie via `loadSealedSession().authenticate()`; redirect to `/admin/login` (with `state=<original-path>`) on failure for HTTP, return 401 for WebSocket upgrades |
| `wrangler.jsonc` | Add a comment block for the four `WORKOS_*` secrets (no `vars` entries — they're secrets) |
| `.env.example` | Add `WORKOS_API_KEY`, `WORKOS_CLIENT_ID`, `WORKOS_REDIRECT_URI`, `WORKOS_COOKIE_PASSWORD` with usage notes |
| `package.json` | Add `@workos-inc/node` dep |
| `src/types.ts` | Extend `Env` with the four `WORKOS_*` fields |

### Deleted Files

None.

## Implementation Details

### 1. Add deps

```sh
bun add @workos-inc/node
```

The SDK works in CF Workers' V8 isolates because the auth path uses `fetch` + WebCrypto — no `node:crypto`, `node:fs`, or other Node-only deps. Verify by running `bunx wrangler dev` after install.

### 2. WorkOS env vars

**File**: `.env.example`

```sh
# WorkOS AuthKit gates the admin dashboard.
# Get values from the WorkOS dashboard for the AuthKit application:
#   https://dashboard.workos.com/

# API key for server-side calls (sk_test_... in dev, sk_live_... in prod).
WORKOS_API_KEY=

# Client ID for the AuthKit application.
WORKOS_CLIENT_ID=

# Where AuthKit redirects after a successful login.
# In dev: http://localhost:4321/admin/callback
# In prod: https://birdcar.dev/admin/callback
WORKOS_REDIRECT_URI=

# 32-character (or longer) password for sealing session cookies.
# Generate with: openssl rand -base64 32
WORKOS_COOKIE_PASSWORD=
```

Set as Cloudflare secrets:

```sh
bunx wrangler secret put WORKOS_API_KEY
bunx wrangler secret put WORKOS_CLIENT_ID
bunx wrangler secret put WORKOS_REDIRECT_URI
bunx wrangler secret put WORKOS_COOKIE_PASSWORD
```

In the WorkOS dashboard:
- Create an AuthKit application
- Set redirect URI to `https://birdcar.dev/admin/callback` (and `http://localhost:4321/admin/callback` for dev)
- Enable passkeys in authentication methods
- Note the Client ID and create an API key

### 3. WorkOS wrapper

**File**: `src/lib/workos.ts`

```typescript
import { WorkOS } from '@workos-inc/node';
import type { Env } from '../types';

const COOKIE_NAME = 'wos-session';

export function getWorkOS(env: Env): WorkOS {
  return new WorkOS(env.WORKOS_API_KEY);
}

export function getAuthorizationUrl(env: Env, returnPath: string): string {
  const workos = getWorkOS(env);
  return workos.userManagement.getAuthorizationUrl({
    provider: 'authkit',
    clientId: env.WORKOS_CLIENT_ID,
    redirectUri: env.WORKOS_REDIRECT_URI,
    state: returnPath,
  });
}

export async function authenticateWithCode(env: Env, code: string) {
  const workos = getWorkOS(env);
  return workos.userManagement.authenticateWithCode({
    code,
    clientId: env.WORKOS_CLIENT_ID,
    session: {
      sealSession: true,
      cookiePassword: env.WORKOS_COOKIE_PASSWORD,
    },
  });
}

export interface SessionUser {
  id: string;
  email: string;
  firstName?: string;
  lastName?: string;
}

export async function validateSession(
  env: Env,
  cookieValue: string | undefined,
): Promise<{ user: SessionUser; refreshedCookie?: string } | null> {
  if (!cookieValue) return null;
  const workos = getWorkOS(env);
  const session = workos.userManagement.loadSealedSession({
    sessionData: cookieValue,
    cookiePassword: env.WORKOS_COOKIE_PASSWORD,
  });
  const auth = await session.authenticate();
  if (auth.authenticated) {
    return {
      user: {
        id: auth.user.id,
        email: auth.user.email,
        firstName: auth.user.firstName,
        lastName: auth.user.lastName,
      },
    };
  }
  // Try refresh.
  try {
    const refreshed = await session.refresh();
    if (refreshed.authenticated) {
      return {
        user: {
          id: refreshed.user.id,
          email: refreshed.user.email,
          firstName: refreshed.user.firstName,
          lastName: refreshed.user.lastName,
        },
        refreshedCookie: refreshed.sealedSession,
      };
    }
  } catch {
    /* fall through to null */
  }
  return null;
}

export async function getLogoutUrl(env: Env, cookieValue: string): Promise<string> {
  const workos = getWorkOS(env);
  const session = workos.userManagement.loadSealedSession({
    sessionData: cookieValue,
    cookiePassword: env.WORKOS_COOKIE_PASSWORD,
  });
  return session.getLogoutUrl();
}

export function buildSessionCookie(value: string): string {
  return `${COOKIE_NAME}=${value}; Path=/; HttpOnly; Secure; SameSite=Lax; Max-Age=2592000`;
}

export function buildClearedSessionCookie(): string {
  return `${COOKIE_NAME}=; Path=/; HttpOnly; Secure; SameSite=Lax; Max-Age=0`;
}

export function readSessionCookie(request: Request): string | undefined {
  const cookieHeader = request.headers.get('cookie');
  if (!cookieHeader) return undefined;
  const match = cookieHeader.match(/(?:^|;\s*)wos-session=([^;]+)/);
  return match?.[1];
}
```

**Failure modes**:
- `validateSession` returns null on any failure → middleware redirects (HTTP) or 401s (WS)
- Refresh failure → cookie cleared on next response, treated as logged out
- WorkOS API outage → `loadSealedSession` is local (just decrypts the cookie); `authenticate()` may fail if it tries to validate against WorkOS — fall through to refresh; if refresh fails, treat as logged out

### 4. Middleware extension

**File**: `src/middleware.ts`

Extend the existing markdown content-negotiation middleware. The order matters: auth gate runs *before* the markdown branch (no point serving `.md` of a gated path).

```typescript
import { defineMiddleware } from 'astro:middleware';
import {
  buildSessionCookie,
  readSessionCookie,
  validateSession,
  type SessionUser,
} from './lib/workos';

declare global {
  namespace App {
    interface Locals {
      user?: SessionUser;
    }
  }
}

export const onRequest = defineMiddleware(async (context, next) => {
  const { request, url } = context;
  const isAdmin = url.pathname.startsWith('/admin');
  const isAgentWS = url.pathname.startsWith('/agents');

  // Auth-required paths, but exempt the auth flow itself.
  const requiresAuth =
    (isAdmin || isAgentWS) &&
    !url.pathname.startsWith('/admin/login') &&
    !url.pathname.startsWith('/admin/callback');

  if (requiresAuth) {
    const env = (context.locals as { runtime?: { env?: any } }).runtime?.env;
    if (!env) {
      return new Response('runtime unavailable', { status: 500 });
    }
    const cookie = readSessionCookie(request);
    const session = await validateSession(env, cookie);
    if (!session) {
      // WebSocket upgrade can't redirect; HTTP can.
      if (request.headers.get('upgrade') === 'websocket') {
        return new Response('unauthorized', { status: 401 });
      }
      const returnPath = url.pathname + url.search;
      const loginUrl = `/admin/login?return=${encodeURIComponent(returnPath)}`;
      return Response.redirect(new URL(loginUrl, url).toString(), 302);
    }
    context.locals.user = session.user;
    // If we refreshed the session, propagate the new cookie on the response.
    if (session.refreshedCookie) {
      const response = await next();
      response.headers.append('Set-Cookie', buildSessionCookie(session.refreshedCookie));
      return response;
    }
  }

  // Existing markdown content-negotiation logic stays.
  // ... existing code ...

  return next();
});
```

**Pattern to follow**: the existing markdown branch in `src/middleware.ts` — keep its structure intact, add the auth branch above it.

**Key decisions**:
- Auth check fires on `/admin/*` AND `/agents/*` so the WebSocket upgrade is gated by the same logic.
- WebSocket upgrades return 401 with no body — browsers don't honor 302 on WS handshakes, so a redirect would just produce a confusing failure.
- Session refresh is opportunistic: if AuthKit issued a new sealed session during validation, attach the new cookie to the response.
- `Astro.locals.user` is populated for downstream pages to render "Logged in as <email>".

### 5. Auth routes

**File**: `src/pages/admin/login.astro`

```astro
---
import { getAuthorizationUrl } from '../../lib/workos';

export const prerender = false;

const env = (Astro.locals as { runtime?: { env?: any } }).runtime?.env;
const returnPath = Astro.url.searchParams.get('return') || '/admin/leads';
const url = getAuthorizationUrl(env, returnPath);
return Astro.redirect(url, 302);
---
```

**File**: `src/pages/admin/callback.ts`

```typescript
import type { APIRoute } from 'astro';
import { authenticateWithCode, buildSessionCookie } from '../../lib/workos';

export const prerender = false;

export const GET: APIRoute = async ({ request, locals, url }) => {
  const env = (locals as { runtime?: { env?: any } }).runtime?.env;
  if (!env) return new Response('runtime unavailable', { status: 500 });

  const code = url.searchParams.get('code');
  const state = url.searchParams.get('state') || '/admin/leads';

  if (!code) {
    return new Response('missing code', { status: 400 });
  }

  try {
    const { sealedSession } = await authenticateWithCode(env, code);
    return new Response(null, {
      status: 302,
      headers: {
        Location: state,
        'Set-Cookie': buildSessionCookie(sealedSession),
      },
    });
  } catch (err) {
    console.error('[admin/callback] auth failed:', err);
    return new Response('auth failed', { status: 401 });
  }
};
```

**File**: `src/pages/admin/logout.ts`

```typescript
import type { APIRoute } from 'astro';
import { buildClearedSessionCookie, getLogoutUrl, readSessionCookie } from '../../lib/workos';

export const prerender = false;

export const GET: APIRoute = async ({ request, locals }) => {
  const env = (locals as { runtime?: { env?: any } }).runtime?.env;
  if (!env) return new Response('runtime unavailable', { status: 500 });

  const cookie = readSessionCookie(request);
  let location = '/';
  if (cookie) {
    try {
      location = await getLogoutUrl(env, cookie);
    } catch {
      /* fall back to '/' */
    }
  }

  return new Response(null, {
    status: 302,
    headers: {
      Location: location,
      'Set-Cookie': buildClearedSessionCookie(),
    },
  });
};
```

**Failure modes**:
- Callback receives no `?code` → 400
- Callback's `authenticateWithCode` throws → 401 with logged error; user can retry login
- Logout when no cookie present → just redirects to `/`

### 6. Admin pages

**Pattern to follow**: existing site pages use the design system tokens from `global.css`. Admin pages reuse those tokens (no new color palette) and add `admin.css` only for admin-specific chrome (status pills, activity table).

**File**: `src/pages/admin/leads/index.astro`

```astro
---
import Base from '../../../layouts/Base.astro';

export const prerender = false;
const user = Astro.locals.user;
---

<Base title="Pending approvals — Birdcar admin">
  <div class="bc-page-prose">
    <header class="bc-page-head">
      <div class="bc-mono-eyebrow">Admin · pending approvals</div>
      <h1>Leads waiting for you</h1>
      <p class="bc-lede">
        New leads land here as the agent finishes triage. Approve, edit the
        draft, or discard.
      </p>
    </header>

    <ul class="bc-postlist" id="approval-list" aria-live="polite"></ul>
    <p class="muted" id="empty-state">No pending approvals.</p>

    <p class="bc-mono-eyebrow">
      Logged in as {user?.email} · <a href="/admin/logout">sign out</a>
    </p>
  </div>

  <link rel="stylesheet" href="/admin.css" />
  <script type="module" src="/scripts/admin-leads-list.js"></script>
</Base>
```

**File**: `src/pages/admin/leads/[id].astro`

Server-renders the static lead body from D1; the client script handles button interactions and looks up the workflow id from agent state.

```astro
---
import Base from '../../../layouts/Base.astro';
import { eq } from 'drizzle-orm';
import { getDb } from '../../../db/client';
import { leads } from '../../../db/schema';

export const prerender = false;
const env = (Astro.locals as { runtime?: { env?: any } }).runtime?.env;
const id = Astro.params.id;
const db = getDb(env.LEADS_DB);
const [lead] = await db.select().from(leads).where(eq(leads.id, id!)).limit(1);
if (!lead) {
  return Astro.redirect('/admin/leads', 302);
}
---

<Base title={`Lead from ${lead.name} — Birdcar admin`}>
  <div class="bc-page-prose">
    <header class="bc-page-head">
      <div class="bc-mono-eyebrow">Admin · lead</div>
      <h1>{lead.name} <span class="muted">&lt;{lead.email}&gt;</span></h1>
      <p class="bc-mono-eyebrow">
        {lead.category} · score {lead.score ?? '—'} · {lead.submittedAt}
      </p>
    </header>

    <section>
      <h2>Their message</h2>
      <p class="bc-body">{lead.message}</p>
    </section>

    <section>
      <h2>Drafted reply</h2>
      <textarea id="draft-body" class="bc-field-textarea" rows="14">{lead.draft ?? ''}</textarea>
    </section>

    <div class="bc-form-actions" id="lead-actions" data-lead-id={id}>
      <button class="bc-btn bc-btn-primary" data-action="approve">Approve as drafted</button>
      <button class="bc-btn bc-btn-secondary" data-action="edit-approve">Approve with edits</button>
      <button class="bc-btn bc-btn-link" data-action="discard">Discard</button>
    </div>

    <p class="bc-mono-eyebrow"><a href="/admin/leads">← all pending approvals</a></p>
  </div>

  <link rel="stylesheet" href="/admin.css" />
  <script type="module" src="/scripts/admin-lead-detail.js"></script>
</Base>
```

**File**: `src/pages/admin/activity/index.astro`

Server-renders the most recent activity from the agent (RPC call from page render). No client hydration in v1 — refresh-the-page is acceptable for activity views.

```astro
---
import Base from '../../../layouts/Base.astro';
import { getTriageAgent } from '../../../lib/agent-stub';

export const prerender = false;
const env = (Astro.locals as { runtime?: { env?: any } }).runtime?.env;
const agent = await getTriageAgent(env);
const rows = await agent.getRecentActivity(100);
---

<Base title="Agent activity — Birdcar admin">
  <div class="bc-page-prose">
    <header class="bc-page-head">
      <div class="bc-mono-eyebrow">Admin · activity</div>
      <h1>Recent workflow runs</h1>
    </header>

    <table class="bc-activity">
      <thead>
        <tr><th>Time</th><th>Lead</th><th>Step</th><th>Outcome</th><th>Duration</th></tr>
      </thead>
      <tbody>
        {rows.map(r => (
          <tr>
            <td class="bc-mono">{new Date(r.ts).toISOString().replace('T', ' ').slice(0, 19)}</td>
            <td><a href={`/admin/leads/${r.lead_id}`}>{r.lead_id.slice(0, 8)}</a></td>
            <td>{r.step}</td>
            <td><span class={`bc-pill bc-pill--${r.outcome}`}>{r.outcome}</span></td>
            <td class="bc-mono">{r.duration_ms ?? '—'}ms</td>
          </tr>
        ))}
      </tbody>
    </table>
  </div>

  <link rel="stylesheet" href="/admin.css" />
</Base>
```

### 7. Vanilla-JS clients

**Critical rule**: do NOT use `innerHTML` or template-string interpolation for any data that could come from a user (lead.name, lead.email, lead.message, draft body, etc.). Use `document.createElement` + `textContent` exclusively. The data is technically server-trusted (it came from D1, which we wrote ourselves), but the rendering pattern is the wrong one to repeat — it's an XSS waiting to happen the first time we add a field that a future feature lets the lead populate (like a "message subject" the lead types).

**File**: `src/scripts/admin-client.ts`

Shared module. Other scripts import this for the pre-built `AgentClient` connection.

```typescript
import { AgentClient } from 'agents/client';

export function connectAgent(): AgentClient {
  return new AgentClient({
    agent: 'lead-triage-agent',
    name: 'global',
    // The wos-session cookie is sent automatically with the WS upgrade.
  });
}
```

**File**: `src/scripts/admin-leads-list.ts`

Render via DOM construction, not template strings. Every interpolation point uses `textContent` or `setAttribute`.

```typescript
import { connectAgent } from './admin-client';

interface PendingApproval {
  workflowId: string;
  leadId: string;
  name: string;
  email: string;
  category: string;
  score: number | null;
  enqueuedAt: number;
}

const list = document.getElementById('approval-list') as HTMLUListElement;
const empty = document.getElementById('empty-state') as HTMLParagraphElement;

const agent = connectAgent();

agent.onStateUpdate((state: { pendingApprovals?: PendingApproval[] }) => {
  const approvals = state?.pendingApprovals ?? [];
  empty.hidden = approvals.length > 0;
  // Rebuild list using safe DOM construction.
  list.replaceChildren(...approvals.map(renderRow));
});

function renderRow(a: PendingApproval): HTMLLIElement {
  const li = document.createElement('li');

  const link = document.createElement('a');
  link.href = `/admin/leads/${encodeURIComponent(a.leadId)}`;
  link.className = 'bc-post-row';
  li.appendChild(link);

  const date = document.createElement('span');
  date.className = 'bc-post-date';
  date.textContent = new Date(a.enqueuedAt).toISOString().slice(0, 10);
  link.appendChild(date);

  const title = document.createElement('span');
  title.className = 'bc-post-title';
  title.textContent = `${a.name} — ${a.category}`;
  link.appendChild(title);

  const tag = document.createElement('span');
  tag.className = 'bc-post-tag';
  tag.textContent = `score ${a.score ?? '—'}`;
  link.appendChild(tag);

  return li;
}
```

**File**: `src/scripts/admin-lead-detail.ts`

```typescript
import { connectAgent } from './admin-client';

interface PendingApproval {
  workflowId: string;
  leadId: string;
}

const root = document.getElementById('lead-actions') as HTMLElement;
const draftEl = document.getElementById('draft-body') as HTMLTextAreaElement;
const leadId = root.dataset.leadId!;

const agent = connectAgent();

let workflowId: string | null = null;

agent.onStateUpdate((state: { pendingApprovals?: PendingApproval[] }) => {
  const approval = state?.pendingApprovals?.find((p) => p.leadId === leadId);
  workflowId = approval?.workflowId ?? null;
  if (!approval) {
    // Already resolved — disable buttons and show inline status notice.
    root.querySelectorAll('button').forEach((b) => ((b as HTMLButtonElement).disabled = true));
    showNotice('This lead is no longer pending approval. Refresh to see latest status.');
  }
});

function showNotice(text: string): void {
  if (document.getElementById('lead-status-notice')) return;
  const notice = document.createElement('p');
  notice.id = 'lead-status-notice';
  notice.className = 'bc-form-error';
  notice.textContent = text;
  root.parentElement?.insertBefore(notice, root);
}

root.addEventListener('click', async (e) => {
  const target = (e.target as HTMLElement).closest('button[data-action]') as HTMLButtonElement | null;
  if (!target || !workflowId) return;
  const action = target.dataset.action;

  root.querySelectorAll('button').forEach((b) => ((b as HTMLButtonElement).disabled = true));

  try {
    if (action === 'approve') {
      await agent.stub.approveLead(workflowId);
    } else if (action === 'edit-approve') {
      const edited = draftEl.value.trim();
      if (edited.length < 10) {
        alert('Edited draft is too short.');
        return;
      }
      await agent.stub.approveLead(workflowId, edited);
    } else if (action === 'discard') {
      if (!confirm('Discard this lead? No reply will be sent.')) return;
      await agent.stub.discardLead(workflowId);
    }
    window.location.href = '/admin/leads';
  } catch (err) {
    console.error('[admin/lead] action failed:', err);
    alert('Action failed — see console.');
  } finally {
    root.querySelectorAll('button').forEach((b) => ((b as HTMLButtonElement).disabled = false));
  }
});
```

**Failure modes**:
- WS connection drops → `AgentClient` reconnects automatically (SDK behavior); state updates resume
- Agent state update arrives during action mid-flight → buttons are disabled during the call so no double-fire
- Approve action fails (e.g., workflow already completed, race condition) → caught, alert, re-enable buttons
- User edits draft but workflow has timed out (7 days) → approve call rejects; alert tells the user

### 8. Admin styles

**File**: `src/styles/admin.css`

Keep small. Reuse design tokens from the existing global.css. Add only:

- `.bc-pill` — small status indicators for activity outcomes
- `.bc-activity` — activity table layout
- `.bc-field-textarea` — large editable draft area

```css
.bc-pill {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-family: var(--font-mono);
  font-size: 0.85rem;
  text-transform: lowercase;
}
.bc-pill--success { background: var(--ink-3); color: var(--paper); }
.bc-pill--failure { background: var(--clay); color: var(--paper); }
.bc-pill--retry { background: var(--ink-2); color: var(--paper); }

.bc-activity {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.95rem;
}
.bc-activity th { text-align: left; padding: 8px; border-bottom: 1px solid var(--rule); }
.bc-activity td { padding: 8px; border-bottom: 1px solid var(--rule); }
.bc-activity .bc-mono { font-family: var(--font-mono); color: var(--ink-2); }

.bc-field-textarea {
  width: 100%;
  font-family: var(--font-serif);
  font-size: 1rem;
  line-height: 1.5;
  padding: 12px;
  border: 1px solid var(--rule);
  border-radius: 4px;
  background: var(--paper);
  color: var(--ink);
}
.bc-field-textarea:focus {
  outline: 2px solid var(--clay);
  outline-offset: 2px;
}

.bc-form-error {
  color: var(--clay);
  background: var(--paper-2);
  padding: 12px;
  border-left: 4px solid var(--clay);
  margin: 16px 0;
}
```

### 9. Worker entrypoint — agent + WS routing

The Agents SDK requires the worker entry to call `routeAgentRequest(request, env)` for `/agents/*` paths. With the Astro Cloudflare adapter, this is handled by re-exporting agent classes from a wrapper entry. Validate during implementation; if the adapter doesn't expose a hook directly, the fallback is to write `src/worker.ts` that wraps the adapter's exported `default.fetch`, runs `routeAgentRequest` first, falls through to the adapter handler.

**Pattern to follow**: Cloudflare Agents SDK "add to existing project" guide — referenced in the conversation context, references `routeAgentRequest`. Confirm the exact pattern at execution time against the live docs.

## API Design

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `GET` | `/admin/login` | none (auth flow start) | Redirect to AuthKit |
| `GET` | `/admin/callback` | none (auth flow end) | Exchange code, set cookie, redirect to `state` |
| `GET` | `/admin/logout` | session | Clear cookie, redirect to AuthKit logout URL |
| `GET` | `/admin/` | session | Dashboard landing |
| `GET` | `/admin/leads` | session | Pending approvals list |
| `GET` | `/admin/leads/[id]` | session | Single lead detail |
| `GET` | `/admin/activity` | session | Recent workflow activity |
| `WS` | `/agents/lead-triage-agent/global` | session (cookie on upgrade) | AgentClient WebSocket connection |

## Failure Modes

| Component | Failure | Behavior |
|---|---|---|
| AuthKit redirect | `WORKOS_REDIRECT_URI` mismatch | AuthKit shows error page; user sees clear message |
| Callback | `?code` invalid or expired | 401 with logged error; user retries login |
| Middleware | Cookie sealed with old password | `validateSession` returns null; redirect to login |
| WebSocket upgrade | No cookie | 401 returned; browser console shows the WS connect failure |
| `agent.approveLead` | Workflow already completed | SDK throws; client catches, alerts, refreshes list |
| Concurrent approval | Two browser tabs both approve | First wins; second gets the "already approved" error |
| `agent.getRecentActivity` SQL error | Activity table corrupted (rare) | Page renders empty table; error logged |
| Draft edit > 8000 chars | User pastes huge text | Server-side validation in agent's `editAndApprove` rejects; client alerts |

## Validation Steps

1. `bun add @workos-inc/node`
2. Configure WorkOS application in dashboard (redirect URIs, passkey, get keys)
3. `bunx wrangler secret put WORKOS_API_KEY` (and the other three)
4. `bun run check` → 0 errors
5. `bunx wrangler dev --remote`
6. Visit `http://localhost:4321/admin/leads` → expect redirect to AuthKit
7. Complete passkey login → land on `/admin/leads` showing "No pending approvals" (assuming clean state)
8. In another tab, submit `/contact` form
9. Within ~30 seconds (Phase 1 workflow finishes), the dashboard list updates with the new lead (no page refresh needed)
10. Click into the lead → see full draft, edit if desired, click Approve
11. Workflow resumes; reply email lands in test inbox; redirected back to `/admin/leads`; lead no longer in list
12. Visit `/admin/activity` → see all step rows for the just-approved workflow
13. `bunx wrangler d1 execute birdcar-leads --command "SELECT id, status, outcome FROM leads WHERE id = '<id>'"` shows status=done, outcome=approved
14. Sign out → redirected to AuthKit logout → cookie cleared → next `/admin/*` hit redirects to login
15. `bun run build:ci` → 0 errors
16. Push, deploy, exercise on prod
