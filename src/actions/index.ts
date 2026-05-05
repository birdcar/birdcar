import { ActionError, defineAction } from 'astro:actions';
// Astro 6 deprecates `z` from astro:schema; the canonical replacement is
// `astro/zod` (a re-export of zod/v4 the rest of the framework uses too).
import { z } from 'astro/zod';
import { LeadsRepo } from '../lib/leads-repo';
import { getCloudflareEnv } from '../lib/env';
import { logEvent, logWarn } from '../lib/event-log';
import { wrapAction } from '../lib/with-action-error';

export const server = {
  contact: {
    send: defineAction({
      accept: 'form',
      input: z.object({
        name: z
          .string()
          .trim()
          .min(1, 'Tell me your name.')
          .max(120, 'That name is unusually long.'),
        email: z
          .email('Give me an email I can reply to.')
          .trim()
          .max(254, 'That email is too long.'),
        message: z
          .string()
          .trim()
          .min(10, 'A sentence or two helps me reply usefully.')
          .max(5000, 'Tighten that up — under 5000 characters.'),
      }),
      handler: wrapAction(
        {
          // Historical event name kept so existing Workers Logs queries
          // continue to work. Pre-Phase-3 only the persist failure path
          // emitted this; under wrapAction, any unanticipated handler throw
          // does. The persist throw is by far the dominant case, so the
          // name still describes the most likely failure correctly.
          event: 'lead.persist.failed',
          userMessage:
            "I couldn't save that. Email hi@birdcar.dev directly and I'll see it.",
        },
        async (input, ctx) => {
          const env = await getCloudflareEnv();
          if (!env?.LEADS_DB) {
            throw new ActionError({
              code: 'INTERNAL_SERVER_ERROR',
              message:
                'The contact form is not configured. Email me directly at hi@birdcar.dev.',
            });
          }

          const id = crypto.randomUUID();
          const submittedAt = new Date().toISOString();

          logEvent('lead.received', { leadId: id, email: input.email });

          const repo = new LeadsRepo(env.LEADS_DB);
          await repo.insert({
            id,
            submittedAt,
            name: input.name,
            email: input.email,
            message: input.message,
            userAgent: ctx.request.headers.get('user-agent'),
            source: 'birdcar.dev/contact',
          });
          logEvent('lead.persisted', { leadId: id });

          // Publish to LEAD_TRIAGE_QUEUE; the worker entry's queue handler
          // dispatches to the agent. Non-blocking: a transient queue.send
          // failure doesn't break the form submit. The agent's cron sweep
          // recovers any rows that miss the queue path entirely.
          try {
            await env.LEAD_TRIAGE_QUEUE.send({ leadId: id });
            logEvent('lead.enqueued', { leadId: id });
          } catch (err) {
            logWarn('lead.enqueue.failed', { leadId: id }, err);
          }

          return { delivered: true, id };
        },
      ),
    }),
  },
};
