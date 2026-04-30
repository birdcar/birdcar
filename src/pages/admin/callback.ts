import type { APIRoute } from 'astro';
import { getEnv } from '../../lib/leads';
import {
  authenticateWithCode,
  buildSessionCookie,
  safeReturnPath,
} from '../../lib/workos';

export const prerender = false;

export const GET: APIRoute = async ({ locals, url }) => {
  const env = await getEnv(locals);
  if (!env) return new Response('runtime unavailable', { status: 500 });

  const code = url.searchParams.get('code');
  // `state` round-trips through the AuthKit redirect from `/admin/login`,
  // so any path an attacker could lure a victim to via that URL also lands
  // here. Validate it as a same-origin path before reflecting it into the
  // `Location` header — otherwise this would be an open redirect.
  const state = safeReturnPath(url.searchParams.get('state'));

  if (!code) {
    return new Response('missing code', { status: 400 });
  }

  try {
    const { sealedSession } = await authenticateWithCode(env, code);
    return new Response(null, {
      status: 302,
      headers: {
        Location: state,
        'Set-Cookie': buildSessionCookie(sealedSession, url),
      },
    });
  } catch (err) {
    console.error({ event: 'admin.callback.failed', error: err });
    return new Response('auth failed', { status: 401 });
  }
};
