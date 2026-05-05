import type { APIRoute } from 'astro';
import { getCloudflareEnv } from '../../lib/env';
import { clearSession } from '../../lib/session';
import { getLogoutUrl, readSessionCookie } from '../../lib/workos';

export const prerender = false;

export const GET: APIRoute = async ({ request }) => {
  const env = await getCloudflareEnv();
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
      'Set-Cookie': clearSession(new URL(request.url)),
    },
  });
};
