import { defineMiddleware } from 'astro:middleware';
import type { Env } from './types';
import {
  buildSessionCookie,
  readSessionCookie,
  validateSession,
  type SessionUser,
} from './lib/workos';

interface AssetsBinding {
  fetch: (request: Request) => Promise<Response>;
}

interface CloudflareRuntime {
  env?: Env & { ASSETS?: AssetsBinding };
}

declare global {
  namespace App {
    interface Locals {
      user?: SessionUser;
    }
  }
}

function estimateTokens(text: string): number {
  return Math.max(1, Math.ceil(text.length / 4));
}

function mapToMarkdownPath(pathname: string): string | null {
  if (pathname.endsWith('.md') || pathname.endsWith('.html')) return null;
  if (pathname.endsWith('/')) return `${pathname}index.md`;
  if (/\.[a-z0-9]{2,5}$/i.test(pathname)) return null;
  return `${pathname}/index.md`;
}

function acceptsMarkdown(request: Request): boolean {
  const header = request.headers.get('Accept') ?? '';
  return /\btext\/markdown\b/i.test(header);
}

function isWebSocketUpgrade(request: Request): boolean {
  return request.headers.get('upgrade')?.toLowerCase() === 'websocket';
}

export const onRequest = defineMiddleware(async (context, next) => {
  // Skip during static prerender — there's no real request, and reading
  // `request.headers` triggers Astro's "headers not available on
  // prerendered pages" warning on every static route. The markdown
  // negotiation is purely a runtime concern; the deployed worker still
  // runs this middleware on real requests.
  if (context.isPrerendered) {
    return next();
  }

  const { request, url } = context;
  const runtime = (context.locals as { runtime?: CloudflareRuntime }).runtime;

  // -------------- Auth gate for /admin/* and /agents/* --------------
  // Runs ahead of the markdown branch so the auth requirement is honored
  // even for `Accept: text/markdown` requests against gated paths.
  const isAdminPath = url.pathname.startsWith('/admin');
  const isAgentPath = url.pathname.startsWith('/agents');
  // Exact-match the auth-flow allowlist so a future page added under
  // `/admin/loginX` or `/admin/callbackX` doesn't silently bypass auth.
  const isAuthFlowPath =
    url.pathname === '/admin/login' || url.pathname === '/admin/callback';

  if ((isAdminPath || isAgentPath) && !isAuthFlowPath) {
    const env = runtime?.env;
    if (!env) {
      return new Response('runtime unavailable', { status: 500 });
    }
    const cookie = readSessionCookie(request);
    const session = await validateSession(env, cookie);
    if (!session) {
      // Browsers drop 302 on a WebSocket handshake — only HTTP fetches
      // can be redirected to the login flow. WS upgrades get a plain
      // 401 so the client can surface a connection error.
      if (isWebSocketUpgrade(request)) {
        return new Response('unauthorized', { status: 401 });
      }
      const returnPath = url.pathname + url.search;
      const loginUrl = new URL('/admin/login', url);
      loginUrl.searchParams.set('return', returnPath);
      return Response.redirect(loginUrl.toString(), 302);
    }
    context.locals.user = session.user;
    if (session.refreshedCookie) {
      // Wrap the downstream response so the new sealed cookie rides
      // along with whatever the page or route returns.
      const response = await next();
      response.headers.append(
        'Set-Cookie',
        buildSessionCookie(session.refreshedCookie, url),
      );
      return response;
    }
    // Authenticated, no refresh needed. Skip the markdown branch — gated
    // paths shouldn't ever serve their `.md` siblings.
    return next();
  }

  // ----------- Markdown content negotiation (existing) -------------
  if (request.method !== 'GET' && request.method !== 'HEAD') {
    return next();
  }

  if (!acceptsMarkdown(request)) {
    return next();
  }

  const mdPath = mapToMarkdownPath(url.pathname);
  if (!mdPath) return next();

  const assets = runtime?.env?.ASSETS;
  if (!assets) return next();

  const mdUrl = new URL(mdPath, url.origin);
  const mdRequest = new Request(mdUrl.toString(), {
    method: request.method,
    headers: request.headers,
    redirect: 'manual',
  });

  const mdResponse = await assets.fetch(mdRequest);
  if (mdResponse.status !== 200) return next();

  const body = await mdResponse.text();
  const headers = new Headers(mdResponse.headers);
  headers.set('Content-Type', 'text/markdown; charset=utf-8');
  headers.set('X-Markdown-Tokens', String(estimateTokens(body)));
  headers.set('Vary', 'Accept');

  if (request.method === 'HEAD') {
    return new Response(null, { status: 200, headers });
  }
  return new Response(body, { status: 200, headers });
});
