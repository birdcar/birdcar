import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import type { APIContext } from 'astro';

// astro:middleware's defineMiddleware is a type-only helper that returns its
// argument at runtime. Mock it so the import resolves in the test env.
vi.mock('astro:middleware', () => ({
  defineMiddleware: <T>(fn: T) => fn,
}));

const { workosState } = vi.hoisted(() => ({
  workosState: { authenticated: true, refreshable: true },
}));

vi.mock('@workos-inc/node', () => ({
  WorkOS: class {
    userManagement = {
      loadSealedSession: () => ({
        authenticate: async () =>
          workosState.authenticated
            ? {
                authenticated: true,
                user: { id: 'user_test', email: 'tester@example.com' },
              }
            : { authenticated: false },
        refresh: async () =>
          workosState.refreshable
            ? {
                authenticated: true,
                user: { id: 'user_test', email: 'tester@example.com' },
                sealedSession: 'sealed.refreshed.session',
              }
            : { authenticated: false },
        getLogoutUrl: async () => 'https://api.workos.com/logout',
      }),
      getAuthorizationUrl: () => 'https://api.workos.com/auth',
      authenticateWithCode: async () => ({
        user: { id: 'user_test', email: 'tester@example.com' },
        sealedSession: 'sealed.session',
      }),
    };
  },
}));

import { onRequest } from '../../src/middleware';

beforeEach(() => {
  workosState.authenticated = true;
  workosState.refreshable = true;
});

afterEach(() => {
  vi.restoreAllMocks();
});

function buildContext(opts: {
  pathname: string;
  cookie?: string;
  upgrade?: string;
  isPrerendered?: boolean;
  method?: string;
  accept?: string;
}): APIContext {
  const url = new URL(`http://localhost:4321${opts.pathname}`);
  const headers = new Headers();
  if (opts.cookie) headers.set('cookie', opts.cookie);
  if (opts.upgrade) headers.set('upgrade', opts.upgrade);
  if (opts.accept) headers.set('Accept', opts.accept);
  const request = new Request(url, { method: opts.method ?? 'GET', headers });
  return {
    request,
    url,
    locals: {} as App.Locals,
    isPrerendered: opts.isPrerendered ?? false,
  } as unknown as APIContext;
}

const NEXT_OK = async () => new Response('ok', { status: 200 });

// Astro middleware's onRequest returns `Promise<void | Response>` because
// middleware can fall through. Our test paths always produce a response.
async function call(ctx: APIContext): Promise<Response> {
  const res = await onRequest(ctx, NEXT_OK);
  if (!res) throw new Error('middleware returned undefined — test expected a Response');
  return res;
}

describe('middleware: prerender skip', () => {
  it('returns next() without inspecting headers when isPrerendered is true', async () => {
    const ctx = buildContext({ pathname: '/about', isPrerendered: true });
    const res = await call(ctx);
    expect(res.status).toBe(200);
    expect(await res.text()).toBe('ok');
  });
});

describe('middleware: /admin/* auth gate', () => {
  it('redirects to /admin/login with return path when no cookie is present', async () => {
    const ctx = buildContext({ pathname: '/admin/leads' });
    const res = await call(ctx);
    expect(res.status).toBe(302);
    const location = res.headers.get('Location');
    expect(location).toContain('/admin/login');
    expect(location).toContain('return=%2Fadmin%2Fleads');
  });

  it('preserves query string in the return path', async () => {
    const ctx = buildContext({ pathname: '/admin/leads?status=pending' });
    const res = await call(ctx);
    const location = res.headers.get('Location');
    // The full path including the search string is round-tripped, encoded.
    expect(location).toContain('return=%2Fadmin%2Fleads%3Fstatus%3Dpending');
  });

  it('returns 401 (not 302) on a WebSocket upgrade without a cookie', async () => {
    const ctx = buildContext({ pathname: '/admin/leads', upgrade: 'websocket' });
    const res = await call(ctx);
    expect(res.status).toBe(401);
  });

  it('passes through with a valid session cookie and sets locals.user', async () => {
    const ctx = buildContext({
      pathname: '/admin/leads',
      cookie: 'wos-session=sealed.session.valid',
    });
    const res = await call(ctx);
    expect(res.status).toBe(200);
    expect((ctx.locals as App.Locals).user?.email).toBe('tester@example.com');
  });

  it('appends Set-Cookie when the session was refreshed', async () => {
    workosState.authenticated = false;
    workosState.refreshable = true;
    const ctx = buildContext({
      pathname: '/admin/leads',
      cookie: 'wos-session=sealed.session.expired',
    });
    const res = await call(ctx);
    expect(res.status).toBe(200);
    const setCookie = res.headers.get('set-cookie');
    expect(setCookie).toContain('wos-session=sealed.refreshed.session');
  });

  it('redirects when refresh fails too', async () => {
    workosState.authenticated = false;
    workosState.refreshable = false;
    const ctx = buildContext({
      pathname: '/admin/leads',
      cookie: 'wos-session=sealed.session.dead',
    });
    const res = await call(ctx);
    expect(res.status).toBe(302);
  });
});

describe('middleware: auth-flow allowlist', () => {
  it('lets /admin/login through without auth', async () => {
    const ctx = buildContext({ pathname: '/admin/login' });
    const res = await call(ctx);
    expect(res.status).toBe(200);
  });

  it('lets /admin/callback through without auth', async () => {
    const ctx = buildContext({ pathname: '/admin/callback' });
    const res = await call(ctx);
    expect(res.status).toBe(200);
  });
});

describe('middleware: /agents/* auth gate', () => {
  it('redirects /agents/lead-triage-agent/global without a cookie', async () => {
    const ctx = buildContext({ pathname: '/agents/lead-triage-agent/global' });
    const res = await call(ctx);
    expect(res.status).toBe(302);
  });

  it('returns 401 on a WS upgrade to /agents/* without a cookie', async () => {
    const ctx = buildContext({
      pathname: '/agents/lead-triage-agent/global',
      upgrade: 'websocket',
    });
    const res = await call(ctx);
    expect(res.status).toBe(401);
  });
});

describe('middleware: markdown negotiation', () => {
  it('passes through non-GET requests without negotiation', async () => {
    const ctx = buildContext({
      pathname: '/writing/joy-matters',
      method: 'POST',
      accept: 'text/markdown',
    });
    const res = await call(ctx);
    expect(res.status).toBe(200);
    expect(res.headers.get('Content-Type')).not.toContain('markdown');
  });

  it('passes through when Accept does not request markdown', async () => {
    const ctx = buildContext({ pathname: '/writing/joy-matters', accept: 'text/html' });
    const res = await call(ctx);
    expect(res.status).toBe(200);
    expect(res.headers.get('Content-Type')).not.toContain('markdown');
  });

  // The full markdown branch requires an ASSETS binding that the test env
  // does not declare. Document the gap; covered transitively via build output.
  it.todo('serves the .md sibling when Accept includes text/markdown and ASSETS is configured');
});
