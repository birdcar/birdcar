/**
 * High-level session helpers composing the low-level workos.ts primitives.
 * Wraps the cookie-read → validate → refresh-cookie chain into a single
 * `requireSession` call so middleware, the worker's agent gate, and admin
 * routes share one auth surface.
 */
import type { Env } from '../types';
import {
  buildClearedSessionCookie,
  buildSessionCookie,
  readSessionCookie,
  validateSession,
  type ValidatedSession,
} from './workos';

export interface SessionResult {
  session: ValidatedSession | null;
}

/**
 * Read the wos-session cookie from the request, validate it against WorkOS,
 * and return the result. `session` is null if no cookie was present, the
 * cookie is invalid, or refresh failed.
 */
export async function requireSession(
  request: Request,
  env: Env,
): Promise<SessionResult> {
  const cookie = readSessionCookie(request);
  const session = await validateSession(env, cookie);
  return { session };
}

/**
 * Append a refreshed sealed-session cookie to a response. Used by the
 * middleware to keep an expiring session alive across the round-trip.
 */
export function attachRefreshedSession(
  response: Response,
  refreshedCookie: string,
  url: URL,
): Response {
  response.headers.append('Set-Cookie', buildSessionCookie(refreshedCookie, url));
  return response;
}

/**
 * Build a Set-Cookie header value that clears the wos-session cookie. Used
 * by the logout route.
 */
export function clearSession(url: URL): string {
  return buildClearedSessionCookie(url);
}
