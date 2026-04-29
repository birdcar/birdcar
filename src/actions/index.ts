import { ActionError, defineAction } from 'astro:actions';
import { z } from 'astro:schema';
import { getEnv, insertLead } from '../lib/leads';

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
          .string()
          .trim()
          .email('Give me an email I can reply to.')
          .max(254, 'That email is too long.'),
        message: z
          .string()
          .trim()
          .min(10, 'A sentence or two helps me reply usefully.')
          .max(5000, 'Tighten that up — under 5000 characters.'),
      }),
      handler: async (input, ctx) => {
        const env = await getEnv(ctx.locals);
        if (!env?.LEADS_DB) {
          throw new ActionError({
            code: 'INTERNAL_SERVER_ERROR',
            message: 'The contact form is not configured. Email me directly at hi@birdcar.dev.',
          });
        }

        const id = crypto.randomUUID();
        const submittedAt = new Date().toISOString();

        try {
          await insertLead(env.LEADS_DB, {
            id,
            submittedAt,
            name: input.name,
            email: input.email,
            message: input.message,
            userAgent: ctx.request.headers.get('user-agent'),
            source: 'birdcar.dev/contact',
          });
        } catch {
          throw new ActionError({
            code: 'INTERNAL_SERVER_ERROR',
            message: "I couldn't save that. Email hi@birdcar.dev directly and I'll see it.",
          });
        }

        // Publish to LEAD_TRIAGE_QUEUE; the worker entry's queue handler
        // dispatches to the agent. Non-blocking: a transient queue.send
        // failure doesn't break the form submit. The agent's cron sweep
        // recovers any rows that miss the queue path entirely.
        try {
          await env.LEAD_TRIAGE_QUEUE.send({ leadId: id });
        } catch (err) {
          console.warn(`[contact.send] triage enqueue failed for ${id}:`, err);
        }

        return { delivered: true, id } as const;
      },
    }),
  },
};
