import type { APIRoute } from 'astro';
import { getEnv } from '../../lib/leads';
import { getAuthorizationUrl, safeReturnPath } from '../../lib/workos';

export const prerender = false;

export const GET: APIRoute = async ({ locals, url }) => {
  const env = await getEnv(locals);
  if (!env) return new Response('runtime unavailable', { status: 500 });

  // Defense-in-depth: the callback also validates `state`, but sanitizing
  // here keeps the AuthKit URL itself clean of attacker-controlled
  // redirect targets.
  const returnPath = safeReturnPath(url.searchParams.get('return'));
  return new Response(null, {
    status: 302,
    headers: { Location: getAuthorizationUrl(env, returnPath) },
  });
};
