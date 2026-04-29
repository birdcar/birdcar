import type { APIRoute } from 'astro';
import { z } from 'astro:schema';
import { getEnv, patchLead, type LeadPatch } from '../../../lib/leads';

export const prerender = false;

const PatchSchema = z.object({
  status: z.enum(['pending', 'processing', 'done', 'discarded']).optional(),
  category: z
    .enum([
      'consulting-fit',
      'consulting-offfit',
      'support-question',
      'vendor-pitch',
      'recruiting',
      'spam',
    ])
    .nullable()
    .optional(),
  qualification: z.string().max(8000).nullable().optional(),
  score: z.number().int().min(0).max(10).nullable().optional(),
  draft: z.string().max(10000).nullable().optional(),
  outcome: z.enum(['approved', 'edited', 'discarded']).nullable().optional(),
  respondedAt: z.string().datetime().nullable().optional(),
});

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json', 'cache-control': 'no-store' },
  });
}

function isAuthorized(request: Request, expected: string | undefined): boolean {
  if (!expected) return false;
  const header = request.headers.get('authorization');
  return Boolean(
    header?.startsWith('Bearer ') && header.slice(7).trim() === expected,
  );
}

export const PATCH: APIRoute = async ({ request, params, locals }) => {
  const env = await getEnv(locals);
  if (!env || !isAuthorized(request, env.WORKER_PULL_TOKEN)) {
    return jsonResponse(401, { error: 'unauthorized' });
  }
  const id = params.id;
  if (!id || typeof id !== 'string') {
    return jsonResponse(400, { error: 'missing id' });
  }
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return jsonResponse(400, { error: 'invalid json' });
  }
  const parsed = PatchSchema.safeParse(body);
  if (!parsed.success) {
    return jsonResponse(400, { error: 'invalid body', issues: parsed.error.issues });
  }
  const updated = await patchLead(env.LEADS_DB, id, parsed.data as LeadPatch);
  if (!updated) {
    return jsonResponse(404, { error: 'lead not found' });
  }
  return jsonResponse(200, { lead: updated });
};
