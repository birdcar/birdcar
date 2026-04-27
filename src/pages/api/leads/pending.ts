import type { APIRoute } from 'astro';
import { claimPendingLeads, getEnv } from '../../../lib/leads';

export const prerender = false;

function unauthorized(): Response {
  return new Response(JSON.stringify({ error: 'unauthorized' }), {
    status: 401,
    headers: { 'content-type': 'application/json' },
  });
}

function isAuthorized(request: Request, expected: string | undefined): boolean {
  if (!expected) return false;
  const header = request.headers.get('authorization');
  if (!header?.startsWith('Bearer ')) return false;
  return header.slice('Bearer '.length).trim() === expected;
}

export const GET: APIRoute = async ({ request, locals }) => {
  const env = getEnv(locals);
  if (!env || !isAuthorized(request, env.WORKER_PULL_TOKEN)) {
    return unauthorized();
  }
  const url = new URL(request.url);
  const limitRaw = url.searchParams.get('limit');
  const limit = Math.min(
    Math.max(Number.parseInt(limitRaw ?? '50', 10) || 50, 1),
    200,
  );
  const claimed = await claimPendingLeads(env.LEADS_DB, limit);
  return new Response(JSON.stringify({ leads: claimed }), {
    status: 200,
    headers: {
      'content-type': 'application/json',
      'cache-control': 'no-store',
    },
  });
};
