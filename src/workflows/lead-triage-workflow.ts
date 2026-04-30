import { AgentWorkflow, WorkflowRejectedError } from 'agents/workflows';
import type { AgentWorkflowEvent, AgentWorkflowStep } from 'agents/workflows';
import { and, eq, isNull, sql } from 'drizzle-orm';
import { generateText } from 'ai';
import { createWorkersAI } from 'workers-ai-provider';
import { getDb } from '../db/client';
import { leads } from '../db/schema';
import { ClassifyOutput, QualifyOutput } from '../lib/ai-types';
import type {
  ClassifyOutput as ClassifyOutputT,
  QualifyOutput as QualifyOutputT,
} from '../lib/ai-types';
import { classifyPrompt, qualifyPrompt, draftPrompt } from '../lib/prompts';
import {
  MODELS,
  STEP_RETRY,
  APPROVAL_TIMEOUT,
  SCORE_RULES,
  NOTIFY_TO,
  NOTIFY_FROM,
  REPLY_FROM,
} from '../lib/triage-config';
import type { LeadTriageAgent } from '../agents/lead-triage-agent';

interface TriageParams {
  leadId: string;
}

interface ApprovalMetadata {
  editedBody: string | null;
  approvedAt: number;
}

export class LeadTriageWorkflow extends AgentWorkflow<LeadTriageAgent, TriageParams> {
  async run(event: AgentWorkflowEvent<TriageParams>, step: AgentWorkflowStep) {
    const { leadId } = event.payload;
    const workflowId = this.workflowId;

    // 1. Load lead from D1, mark as processing so the sweep ignores it.
    const lead = await step.do('load-lead', STEP_RETRY.persist, async () => {
      const start = Date.now();
      const db = getDb(this.env.LEADS_DB);
      const [row] = await db.select().from(leads).where(eq(leads.id, leadId)).limit(1);
      if (!row) throw new Error(`Lead ${leadId} not found`);
      await db.update(leads).set({ status: 'processing' }).where(eq(leads.id, leadId));
      await this.agent.recordActivity({
        leadId,
        workflowId,
        step: 'load-lead',
        outcome: 'success',
        durationMs: Date.now() - start,
      });
      return row;
    });

    const workersai = createWorkersAI({ binding: this.env.AI });

    // 2. Classify.
    const classification: ClassifyOutputT = await step.do(
      'classify',
      STEP_RETRY.classify,
      async () => {
        const start = Date.now();
        const { system, user } = classifyPrompt({
          name: lead.name,
          email: lead.email,
          message: lead.message,
        });
        const { text } = await generateText({
          model: workersai(MODELS.classify),
          system,
          prompt: user,
        });
        const parsed = ClassifyOutput.parse(JSON.parse(stripCodeFences(text)));
        await this.agent.recordActivity({
          leadId,
          workflowId,
          step: 'classify',
          outcome: 'success',
          durationMs: Date.now() - start,
        });
        return parsed;
      },
    );

    // 3. Qualify (only consulting-fit leads).
    const qualification: QualifyOutputT | null =
      classification.category !== 'consulting-fit'
        ? null
        : await step.do('qualify', STEP_RETRY.qualify, async () => {
            const start = Date.now();
            const { system, user } = qualifyPrompt({
              name: lead.name,
              email: lead.email,
              message: lead.message,
            });
            const { text } = await generateText({
              model: workersai(MODELS.qualify),
              system,
              prompt: user,
            });
            const parsed = QualifyOutput.parse(JSON.parse(stripCodeFences(text)));
            await this.agent.recordActivity({
              leadId,
              workflowId,
              step: 'qualify',
              outcome: 'success',
              durationMs: Date.now() - start,
            });
            return parsed;
          });

    // 4. Score (deterministic).
    const score = await step.do('score', STEP_RETRY.score, async () => {
      let s = 0;
      if (qualification) {
        if (SCORE_RULES.industryFit.includes(qualification.industry as never)) s += 2;
        if (SCORE_RULES.geoStrong.includes(qualification.geography as never)) s += 2;
        else if (SCORE_RULES.geoOk.includes(qualification.geography as never)) s += 1;
        if (SCORE_RULES.sizeFit.includes(qualification.size_signal as never)) s += 2;
        if (SCORE_RULES.problemRecognized.includes(qualification.problem_shape as never)) s += 1;
        if (qualification.urgency_signal === 'in-pain') s += 1;
      }
      return s;
    });

    // 5. Draft.
    const draft = await step.do('draft', STEP_RETRY.draft, async () => {
      const start = Date.now();
      const { system, user } = draftPrompt({
        name: lead.name,
        email: lead.email,
        message: lead.message,
        classification,
        qualification,
        score,
      });
      const { text } = await generateText({
        model: workersai(MODELS.draft),
        system,
        prompt: user,
      });
      await this.agent.recordActivity({
        leadId,
        workflowId,
        step: 'draft',
        outcome: 'success',
        durationMs: Date.now() - start,
      });
      return text.trim();
    });

    // 6. Persist triage results to D1.
    await step.do('persist', STEP_RETRY.persist, async () => {
      const db = getDb(this.env.LEADS_DB);
      await db
        .update(leads)
        .set({
          category: classification.category,
          qualification: qualification ? JSON.stringify(qualification) : null,
          score,
          draft,
        })
        .where(eq(leads.id, leadId));
      await this.agent.recordActivity({
        leadId,
        workflowId,
        step: 'persist',
        outcome: 'success',
      });
    });

    // Spam path: short-circuit. No notification, no approval.
    if (classification.category === 'spam') {
      await step.do('mark-done-spam', STEP_RETRY.persist, async () => {
        const db = getDb(this.env.LEADS_DB);
        await db
          .update(leads)
          .set({ status: 'done', outcome: 'discarded' })
          .where(eq(leads.id, leadId));
      });
      await step.reportComplete({ outcome: 'spam-auto-discarded' });
      return;
    }

    // 7. Notify Nick by email — lead-scoped idempotency via `notified_at`.
    //    Atomic CAS: claim the slot only if it's null, send only if we
    //    won the claim, roll back on send failure so retries can try
    //    again. Survives workflow respawns: a second workflow run for
    //    the same lead won't double-send because notified_at is set.
    await step.do('notify-nick', STEP_RETRY.notify, async () => {
      const start = Date.now();
      const db = getDb(this.env.LEADS_DB);
      const claim = await db
        .update(leads)
        .set({ notifiedAt: sql`(datetime('now'))` })
        .where(and(eq(leads.id, leadId), isNull(leads.notifiedAt)))
        .returning({ id: leads.id });
      if (claim.length === 0) {
        console.log({ event: 'notify-nick.skipped', leadId, workflowId, reason: 'already-notified' });
        return;
      }
      try {
        await this.env.EMAIL.send({
          to: NOTIFY_TO,
          from: NOTIFY_FROM,
          replyTo: NOTIFY_FROM.email,
          subject: `New lead: ${lead.name}`,
          text: notifyEmailBody({
            lead: { name: lead.name, email: lead.email, message: lead.message },
            classification,
            qualification,
            score,
            leadId,
          }),
        });
      } catch (err) {
        await db
          .update(leads)
          .set({ notifiedAt: null })
          .where(eq(leads.id, leadId));
        throw err;
      }
      await this.agent.recordActivity({
        leadId,
        workflowId,
        step: 'notify-nick',
        outcome: 'success',
        durationMs: Date.now() - start,
      });
    });

    // 8. Flip the row to `awaiting-approval` so the sweep stops treating
    //    it as a stalled `processing` row. This is a deliberate dwell state:
    //    the workflow is healthy, the lead is just waiting on a human.
    await step.do('mark-awaiting-approval', STEP_RETRY.persist, async () => {
      const db = getDb(this.env.LEADS_DB);
      await db
        .update(leads)
        .set({ status: 'awaiting-approval' })
        .where(eq(leads.id, leadId));
    });

    // 9. Surface as a pending approval. The agent's onWorkflowProgress hook
    //    pushes this into agent.state.pendingApprovals so the dashboard can render.
    await this.reportProgress({
      step: 'approval',
      lead: {
        workflowId,
        leadId,
        name: lead.name,
        email: lead.email,
        category: classification.category,
        score,
        draftPreview: draft.slice(0, 240),
        enqueuedAt: Date.now(),
      },
    });

    // 10. Wait for human. waitForApproval returns approval metadata, throws
    //     WorkflowRejectedError on reject, throws on timeout.
    let approvalMetadata: ApprovalMetadata | undefined;
    try {
      approvalMetadata = await this.waitForApproval<ApprovalMetadata>(step, {
        timeout: APPROVAL_TIMEOUT,
      });
    } catch (err) {
      if (err instanceof WorkflowRejectedError) {
        await step.do('mark-discarded', STEP_RETRY.persist, async () => {
          const db = getDb(this.env.LEADS_DB);
          await db
            .update(leads)
            .set({ status: 'done', outcome: 'discarded' })
            .where(eq(leads.id, leadId));
        });
        await step.reportComplete({ outcome: 'discarded' });
        return;
      }
      // Timeout or other unexpected error — mark stale.
      await step.do('mark-stale', STEP_RETRY.persist, async () => {
        const db = getDb(this.env.LEADS_DB);
        await db
          .update(leads)
          .set({ status: 'done', outcome: 'discarded' })
          .where(eq(leads.id, leadId));
      });
      await step.reportError(
        err instanceof Error ? err : new Error('Approval wait failed'),
      );
      return;
    }

    const finalBody = approvalMetadata?.editedBody?.trim() || draft;

    // 10. Send reply email — same claim-first idempotency via `responded_at`.
    //     Pre-Phase 2 the workflow can't actually reach this without a
    //     dashboard sending approve, but defense in depth: if Phase 2 ever
    //     surfaces a "retry approval" path, this step won't double-send.
    await step.do('send-reply', STEP_RETRY.notify, async () => {
      const db = getDb(this.env.LEADS_DB);
      const claim = await db
        .update(leads)
        .set({ respondedAt: sql`(datetime('now'))` })
        .where(and(eq(leads.id, leadId), isNull(leads.respondedAt)))
        .returning({ id: leads.id });
      if (claim.length === 0) {
        console.log({ event: 'send-reply.skipped', leadId, workflowId, reason: 'already-responded' });
        return;
      }
      try {
        await this.env.EMAIL.send({
          to: lead.email,
          from: REPLY_FROM,
          replyTo: REPLY_FROM.email,
          subject: 'Re: your message via birdcar.dev',
          text: `${finalBody}\n\n- Nick`,
        });
      } catch (err) {
        await db
          .update(leads)
          .set({ respondedAt: null })
          .where(eq(leads.id, leadId));
        throw err;
      }
      await this.agent.recordActivity({
        leadId,
        workflowId,
        step: 'send-reply',
        outcome: 'success',
      });
    });

    // 11. Mark done. `respondedAt` was already claimed in send-reply
    //     (lead-scoped idempotency); re-setting it here would overwrite
    //     the actual send timestamp with the mark-done timestamp.
    await step.do('mark-done', STEP_RETRY.persist, async () => {
      const outcome = finalBody === draft ? 'approved' : 'edited';
      const db = getDb(this.env.LEADS_DB);
      await db
        .update(leads)
        .set({
          status: 'done',
          outcome,
          draft: finalBody,
        })
        .where(eq(leads.id, leadId));
    });

    await step.reportComplete({ outcome: 'replied' });
  }
}

function stripCodeFences(s: string): string {
  return s
    .replace(/^```(?:json)?\s*/i, '')
    .replace(/```\s*$/, '')
    .trim();
}

function notifyEmailBody(input: {
  lead: { name: string; email: string; message: string };
  classification: ClassifyOutputT;
  qualification: QualifyOutputT | null;
  score: number;
  leadId: string;
}): string {
  const qualLine = input.qualification
    ? `Industry: ${input.qualification.industry} · Geo: ${input.qualification.geography} · Size: ${input.qualification.size_signal}\n`
    : '';
  return `New lead from ${input.lead.name} <${input.lead.email}>

Category: ${input.classification.category} (confidence ${input.classification.confidence.toFixed(2)})
Score: ${input.score}
${qualLine}
Message:
${input.lead.message}

Review: https://www.birdcar.dev/admin/leads/${input.leadId}
`;
}
