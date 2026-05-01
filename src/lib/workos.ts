// Thin wrapper around `@workos-inc/node`'s userManagement surface so the
// admin auth path has one place to evolve. Keep this file free of Astro
// imports — it's also imported from the worker-level middleware which
// runs before any Astro module boundary.
import { WorkOS } from '@workos-inc/node';
import type { Env } from '../types';

const COOKIE_NAME = 'wos-session';
// 30 days. Refresh happens opportunistically; this is just the absolute
// upper bound the browser will retain a stale cookie.
const COOKIE_MAX_AGE = 60 * 60 * 24 * 30;

export interface SessionUser {
  id: string;
  email: string;
  firstName?: string;
  lastName?: string;
}

export interface ValidatedSession {
  user: SessionUser;
  // If WorkOS issued a refreshed sealed session during validation, the
  // caller should append it to the response as a Set-Cookie. When absent,
  // the existing cookie is still good.
  refreshedCookie?: string;
}

function getWorkOS(env: Env): WorkOS {
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

export async function authenticateWithCode(
  env: Env,
  code: string,
): Promise<{ sealedSession: string; user: SessionUser }> {
  const workos = getWorkOS(env);
  const response = await workos.userManagement.authenticateWithCode({
    code,
    clientId: env.WORKOS_CLIENT_ID,
    session: {
      sealSession: true,
      cookiePassword: env.WORKOS_COOKIE_PASSWORD,
    },
  });
  if (!response.sealedSession) {
    throw new Error('authenticateWithCode returned no sealedSession');
  }
  return {
    sealedSession: response.sealedSession,
    user: toSessionUser(response.user),
  };
}

export async function validateSession(
  env: Env,
  cookieValue: string | undefined,
): Promise<ValidatedSession | null> {
  if (!cookieValue) return null;
  const workos = getWorkOS(env);
  const session = workos.userManagement.loadSealedSession({
    sessionData: cookieValue,
    cookiePassword: env.WORKOS_COOKIE_PASSWORD,
  });

  const auth = await session.authenticate();
  if (auth.authenticated) {
    return { user: toSessionUser(auth.user) };
  }

  // Access token expired — try refreshing. The new sealed cookie rides
  // back on the response so the browser stays signed in.
  try {
    const refreshed = await session.refresh();
    if (refreshed.authenticated) {
      return {
        user: toSessionUser(refreshed.user),
        refreshedCookie: refreshed.sealedSession,
      };
    }
  } catch {
    /* refresh failed — treat as logged out */
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

// Browsers reject `Secure` cookies over plain http on most origins; in
// `wrangler dev` the loopback host is http://localhost so the cookie would
// never round-trip and login would loop. Skip `Secure` when the request's
// own URL is http on localhost/127.0.0.1; otherwise keep it on (prod is
// always https on birdcar.dev).
function shouldUseSecure(url: URL): boolean {
  if (url.protocol === 'https:') return true;
  const host = url.hostname;
  return host !== 'localhost' && host !== '127.0.0.1';
}

export function buildSessionCookie(value: string, url: URL): string {
  const secure = shouldUseSecure(url) ? '; Secure' : '';
  return `${COOKIE_NAME}=${value}; Path=/; HttpOnly${secure}; SameSite=Lax; Max-Age=${COOKIE_MAX_AGE}`;
}

export function buildClearedSessionCookie(url: URL): string {
  const secure = shouldUseSecure(url) ? '; Secure' : '';
  return `${COOKIE_NAME}=; Path=/; HttpOnly${secure}; SameSite=Lax; Max-Age=0`;
}

/**
 * Validate a `return`/`state` path for the auth flow. Only same-origin
 * absolute paths are allowed — anything else (full URLs, protocol-relative
 * paths, backslash variants) is replaced with the safe default.
 */
export function safeReturnPath(value: string | null | undefined): string {
  const fallback = '/admin/leads';
  if (!value) return fallback;
  // Must start with `/`, must not start with `//` (protocol-relative) or
  // `/\` (Windows-style path that some browsers normalize to `//`), and
  // must not contain a newline / control char that could fold into a
  // header on its way to `Location:`.
  if (!value.startsWith('/')) return fallback;
  if (value.startsWith('//') || value.startsWith('/\\')) return fallback;
  if (/[\r\n]/.test(value)) return fallback;
  return value;
}

export function readSessionCookie(request: Request): string | undefined {
  const cookieHeader = request.headers.get('cookie');
  if (!cookieHeader) return undefined;
  // Match wos-session= up to the next semicolon. Cookie values are URL-safe
  // base64-ish (set by the SDK) so no need to decode.
  const match = cookieHeader.match(/(?:^|;\s*)wos-session=([^;]+)/);
  return match?.[1];
}

interface WorkOSUserShape {
  id: string;
  email: string;
  firstName?: string | null;
  lastName?: string | null;
}

function toSessionUser(user: WorkOSUserShape): SessionUser {
  return {
    id: user.id,
    email: user.email,
    firstName: user.firstName ?? undefined,
    lastName: user.lastName ?? undefined,
  };
}
