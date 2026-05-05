import type { APIRoute } from 'astro';
import { getCloudflareEnv } from '../../lib/env';
import { logError } from '../../lib/event-log';
import {
  authenticateWithCode,
  buildSessionCookie,
  safeReturnPath,
} from '../../lib/workos';

export const prerender = false;

export const GET: APIRoute = async ({ url }) => {
  const env = await getCloudflareEnv();
  if (!env) return new Response('runtime unavailable', { status: 500 });

  const code = url.searchParams.get('code');
  // `state` is attacker-controllable via `/admin/login?return=...`; sanitize
  // before reflecting into Location to avoid an open redirect.
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
    logError('admin.callback.failed', {}, err);
    return new Response('auth failed', { status: 401 });
  }
};
