import type { APIRoute } from 'astro';
import { getEnv } from '../../lib/leads';
import {
  buildClearedSessionCookie,
  getLogoutUrl,
  readSessionCookie,
} from '../../lib/workos';

export const prerender = false;

export const GET: APIRoute = async ({ request, locals }) => {
  const env = await getEnv(locals);
  if (!env) return new Response('runtime unavailable', { status: 500 });

  const cookie = readSessionCookie(request);
  let location = '/';
  if (cookie) {
    try {
      location = await getLogoutUrl(env, cookie);
    } catch {
      // Sealed cookie may be unparseable (different password, expired,
      // tampered). Fall back to a plain home redirect — the cookie is
      // cleared either way below.
    }
  }

  return new Response(null, {
    status: 302,
    headers: {
      Location: location,
      'Set-Cookie': buildClearedSessionCookie(new URL(request.url)),
    },
  });
};
