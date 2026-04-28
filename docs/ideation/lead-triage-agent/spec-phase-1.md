# Implementation Spec: Lead Triage Agent — Phase 1

**Contract**: ./contract.md
**Estimated Effort**: L

## Technical Approach

Phase 1 ships the entire backend AI triage path with **no UI**. After this phase, submitting the contact form runs a complete classify → qualify → score → draft → email-notification → wait-for-approval → send-reply loop, but "approval" happens via wrangler CLI or D1 console (manually flipping a row, or calling the agent's RPC method through `wrangler dev`'s shell). Phase 2 layers the dashboard on top.

This decoupling matters because the agent + workflow + email backend is the load-bearing piece: get those primitives right and the dashboard is just a view. Shipping Phase 1 alone gives Nick a working pipeline he can manually drive while Phase 2 builds the UI.

Three concrete moves:

1. **Add the Cloudflare Agents SDK** + Workers AI SDK + AI provider. Define `LeadTriageAgent` extending `Agent<Env, AgentState>` with state for `pendingApprovals` and `metrics`, plus callable methods (`queueLead`, `approveLead`, `editAndApprove`, `discardLead`) and lifecycle hooks (`onWorkflowProgress/Complete/Error`). Bind it as a Durable Object in wrangler with the migrations block for the new SQLite-backed class.
2. **Define `LeadTriageWorkflow`** extending `AgentWorkflow<LeadTriageAgent, { leadId: string }>` with the seven durable steps. Each AI step uses `step.do()` with explicit retry/timeout config; structured outputs are parsed via Zod schemas in `src/lib/ai-types.ts`. After draft + persist, `step.do('notify-nick')` calls `env.EMAIL.send()`; then `await this.waitForApproval(step, { timeout: '7 days' })` pauses execution.
3. **Wire the form action to the agent.** One line added to `src/actions/index.ts`: after `insertLead`, fetch the agent stub via `getAgentByName(env.LEAD_TRIAGE, 'global')` and call `agent.queueLead(leadId)`. Action returns success to Megan immediately; the agent runs the workflow asynchronously in the DO.

Activity tracking is baked into every workflow step from day one — each `step.do(...)` wraps the AI call and writes a row to `agent_activity` SQLite (timestamp, lead_id, workflow_instance_id, step_name, duration_ms, outcome). This means Phase 2's `/admin/activity` view is just a query against existing data, not a separate plumbing job.

## Feedback Strategy

**Inner-loop command**: `bun run check`

**Playground**: `bunx wrangler dev --remote` (full Workers runtime including DOs, Workflows, Workers AI, send_email — local dev mode). For inspecting agent state during dev, `bunx wrangler tail` streams console.log + every workflow step transition. For inspecting agent SQLite, the agent exposes a debug RPC method `getActivity()` that the developer can call via `wrangler dev`'s debugger.

**Why this approach**: This is mostly serverless integration code — the fastest validation is "submit a form, watch the workflow execute, see the email arrive." Type-check catches the static surface (Zod schema mismatches, agent method signatures), `wrangler dev --remote` exercises the live integrations (DO, Workers AI, send_email), and `wrangler tail` is the runtime trace. No test framework added — the integration density makes unit tests low-value relative to end-to-end exercise via real form submits.

## File Changes

### New Files

| File Path | Purpose |
|---|---|
| `src/agents/lead-triage-agent.ts` | Agent class: state shape, callable methods, lifecycle hooks, scheduled sweep |
| `src/workflows/lead-triage-workflow.ts` | AgentWorkflow class: classify, qualify, score, draft, persist, notify, waitForApproval, send-reply, mark-done |
| `src/lib/prompts.ts` | Classify, qualify, draft prompt template functions (typed `(input: PromptInput) => string`) |
| `src/lib/ai-types.ts` | Zod schemas for ClassifyOutput, QualifyOutput, ScoreInput |
| `src/lib/triage-config.ts` | Constants: scoring rules, AI model choice, retry/timeout per step |
| `src/lib/agent-stub.ts` | Helper: `getTriageAgent(env)` returns the singleton agent stub |

### Modified Files

| File Path | Changes |
|---|---|
| `src/actions/index.ts` | After `insertLead`, fetch agent stub and call `agent.queueLead(leadId)` |
| `src/lib/leads.ts` | Add `getLeadById(d1, id)` Drizzle helper used by the workflow's `persist` step |
| `src/db/schema.ts` | No changes — existing leads table is sufficient |
| `wrangler.jsonc` | Add `ai` binding, `durable_objects.bindings` for `LEAD_TRIAGE`, `workflows[]` for `LEAD_TRIAGE_WORKFLOW`, `send_email[]` for `EMAIL`, `migrations` block declaring the SQLite-backed DO class |
| `package.json` | Add `agents`, `ai`, `workers-ai-provider` deps; remove `WORKER_PULL_TOKEN`-related notes |
| `.env.example` | Drop `WORKER_PULL_TOKEN` (no longer used by anything in Phase 1); no new vars in Phase 1 |

### Deleted Files

| File Path | Reason |
|---|---|
| `src/pages/api/leads/pending.ts` | Was an n8n contract; nothing consumes it now. The `claimPendingLeads` helper in `src/lib/leads.ts` also gets removed. |

## Implementation Details

### 1. Add deps

```sh
bun add agents ai workers-ai-provider
```

Versions to pin: latest stable for each as of v1 — Agents SDK is on rapid iteration so floating-major is risky.

### 2. AI types (Zod schemas)

**File**: `src/lib/ai-types.ts`

```typescript
import { z } from 'astro:schema';

export const ClassifyOutput = z.object({
  category: z.enum([
    'consulting-fit',
    'consulting-offfit',
    'support-question',
    'vendor-pitch',
    'recruiting',
    'spam',
  ]),
  confidence: z.number().min(0).max(1),
  reasoning: z.string().max(500),
});
export type ClassifyOutput = z.infer<typeof ClassifyOutput>;

export const QualifyOutput = z.object({
  industry: z.enum([
    'cpa',
    'mortgage',
    'realtor',
    'property-mgmt',
    'local-agency',
    'other',
  ]),
  geography: z.enum([
    'fort-worth',
    'dfw',
    'texas',
    'other-us',
    'international',
    'unknown',
  ]),
  size_signal: z.enum([
    'solo',
    'small (2-10)',
    'medium (10-50)',
    'larger',
    'unknown',
  ]),
  problem_shape: z.enum([
    'review-queue',
    'system-glue',
    'internal-tool',
    'reporting',
    'other',
  ]),
  urgency_signal: z.enum(['scope-clear', 'exploring', 'in-pain', 'unknown']),
});
export type QualifyOutput = z.infer<typeof QualifyOutput>;
```

**Key decisions**:
- Use `astro:schema`'s re-exported Zod (already in the project; same Zod the actions use).
- Enum-strict outputs let downstream code (the scorer) be exhaustive without runtime type-narrowing.
- `confidence` and `reasoning` are recorded but not used for scoring — they go straight into activity tracking for later prompt-tuning audits.

### 3. Triage config (constants)

**File**: `src/lib/triage-config.ts`

```typescript
export const MODELS = {
  classify: '@cf/meta/llama-3.1-8b-instruct',
  qualify: '@cf/meta/llama-3.1-8b-instruct',
  draft: '@cf/meta/llama-3.1-8b-instruct',
  // If draft voice quality slips, swap to '@cf/meta/llama-3.3-70b-instruct-fp8-fast'
} as const;

export const STEP_RETRY = {
  classify: { limit: 3, delay: '5 seconds', backoff: 'exponential', timeout: '30 seconds' },
  qualify:  { limit: 3, delay: '5 seconds', backoff: 'exponential', timeout: '30 seconds' },
  score:    { limit: 1, timeout: '5 seconds' },                                    // deterministic; one shot
  draft:    { limit: 2, delay: '10 seconds', backoff: 'exponential', timeout: '2 minutes' },
  persist:  { limit: 3, delay: '2 seconds', backoff: 'exponential', timeout: '10 seconds' },
  notify:   { limit: 3, delay: '5 seconds', backoff: 'exponential', timeout: '15 seconds' },
} as const;

export const APPROVAL_TIMEOUT = '7 days' as const;

export const SCORE_RULES = {
  industryFit: ['cpa', 'mortgage', 'realtor', 'property-mgmt', 'local-agency'] as const,
  geoStrong: ['fort-worth', 'dfw'] as const,
  geoOk: ['texas'] as const,
  sizeFit: ['small (2-10)', 'medium (10-50)'] as const,
  problemRecognized: ['review-queue', 'system-glue', 'internal-tool', 'reporting'] as const,
};

export const STUCK_ROW_THRESHOLD_MINUTES = 10;
export const SWEEP_CRON = '*/5 * * * *';

export const NOTIFY_TO = 'hi@birdcar.dev';
export const NOTIFY_FROM = { email: 'noreply@birdcar.dev', name: 'Birdcar leads' };
export const REPLY_FROM = { email: 'hi@birdcar.dev', name: 'Nick Cannariato' };
```

**Pattern to follow**: existing constants pattern in `src/lib/triage-config.ts` doesn't exist yet, but follows the shape of `src/lib/leads.ts` (named exports, no default export).

### 4. Prompts

**File**: `src/lib/prompts.ts`

Full prompt templates as exported functions. System prompts include the voice rules from `.impeccable.md`. User prompts inject lead fields.

**Pattern to follow**: the gist's `N8N_AI_FLOW.md` document captures the exact prompts that were going to live in n8n — those are the source. Translate verbatim into TS template functions.

```typescript
import type { ClassifyOutput, QualifyOutput } from './ai-types';

interface LeadInput {
  name: string;
  email: string;
  message: string;
}

interface DraftInput extends LeadInput {
  classification: ClassifyOutput;
  qualification: QualifyOutput | null;
  score: number;
}

export function classifyPrompt(lead: LeadInput): { system: string; user: string } {
  return {
    system: `You are sorting incoming inquiries to Nick Cannariato's consulting practice.
Nick builds internal tools and automations for small businesses ($1–20M revenue) in
and around Fort Worth — usually CPAs, mortgage brokers, realtors, property management
firms, and local agencies.

Read the inquiry below and return STRICT JSON with this exact shape:
{
  "category": "consulting-fit" | "consulting-offfit" | "support-question" | "vendor-pitch" | "recruiting" | "spam",
  "confidence": 0.0-1.0,
  "reasoning": "one short sentence"
}

Categories:
- consulting-fit: SMB owner with a workflow/automation/internal-tool problem
- consulting-offfit: consulting-shaped but wrong (enterprise, wrong industry, wrong geography, wrong scope)
- support-question: asking about Nick's open-source projects
- vendor-pitch: trying to sell something to Nick
- recruiting: pitching a full-time job
- spam: automated, off-topic, or unintelligible

Return JSON only. No prose. No code fences.`,
    user: `Name: ${lead.name}
Email: ${lead.email}
Domain: ${lead.email.split('@')[1] ?? 'unknown'}
Message: ${lead.message}`,
  };
}

export function qualifyPrompt(lead: LeadInput): { system: string; user: string } { /* … */ }

export function draftPrompt(input: DraftInput): { system: string; user: string } {
  // System prompt includes voice rules from .impeccable.md — banned hype words list,
  // sentence-case, first-person-singular, etc. User prompt branches by category × score band.
  // Full bodies live in the gist's N8N_AI_FLOW.md.
}
```

### 5. Agent class

**File**: `src/agents/lead-triage-agent.ts`

```typescript
import { Agent, callable, getAgentByName } from 'agents';
import { eq } from 'drizzle-orm';
import { getDb } from '../db/client';
import { leads } from '../db/schema';
import { SWEEP_CRON, STUCK_ROW_THRESHOLD_MINUTES } from '../lib/triage-config';
import type { Env } from '../types';

export interface PendingApproval {
  workflowId: string;
  leadId: string;
  name: string;
  email: string;
  category: string;
  score: number | null;
  draftPreview: string; // first 240 chars
  enqueuedAt: number;
}

export interface AgentMetrics {
  leadsProcessed: number;
  workflowsCompleted: number;
  workflowsFailed: number;
  averageDraftLatencyMs: number | null;
}

export interface AgentState {
  pendingApprovals: PendingApproval[];
  metrics: AgentMetrics;
}

export class LeadTriageAgent extends Agent<Env, AgentState> {
  initialState: AgentState = {
    pendingApprovals: [],
    metrics: {
      leadsProcessed: 0,
      workflowsCompleted: 0,
      workflowsFailed: 0,
      averageDraftLatencyMs: null,
    },
  };

  static options = {
    hibernate: true,
  };

  async onStart() {
    // Bootstrap activity log table if missing.
    this.sql`
      CREATE TABLE IF NOT EXISTS agent_activity (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ts INTEGER NOT NULL,
        lead_id TEXT NOT NULL,
        workflow_id TEXT NOT NULL,
        step TEXT NOT NULL,
        outcome TEXT NOT NULL,
        duration_ms INTEGER,
        retry_count INTEGER DEFAULT 0,
        error_message TEXT
      )
    `;
    this.sql`CREATE INDEX IF NOT EXISTS idx_agent_activity_ts ON agent_activity(ts DESC)`;
    this.sql`CREATE INDEX IF NOT EXISTS idx_agent_activity_lead ON agent_activity(lead_id)`;

    // Schedule the stuck-row sweep (idempotent — schedule() with same cron+callback dedupes).
    await this.schedule(SWEEP_CRON, 'sweepStuckRows', {});
  }

  // Called by the form action right after the lead is inserted into D1.
  @callable()
  async queueLead(leadId: string): Promise<{ workflowId: string }> {
    const workflowId = await this.runWorkflow('LEAD_TRIAGE_WORKFLOW', { leadId });
    return { workflowId };
  }

  // Called by Phase 2 dashboard when Nick clicks Approve.
  @callable()
  async approveLead(workflowId: string, editedBody?: string) {
    await this.approveWorkflow(workflowId, {
      reason: editedBody ? 'Approved with edit' : 'Approved as drafted',
      metadata: { editedBody: editedBody ?? null, approvedAt: Date.now() },
    });
    // Remove from pendingApprovals — workflow's onComplete also clears, but we double-up here
    // for snappy UI feedback before the workflow has fully ticked.
    this.setState({
      ...this.state,
      pendingApprovals: this.state.pendingApprovals.filter(
        (p) => p.workflowId !== workflowId,
      ),
    });
  }

  @callable()
  async discardLead(workflowId: string, reason?: string) {
    await this.rejectWorkflow(workflowId, { reason: reason ?? 'Discarded' });
    this.setState({
      ...this.state,
      pendingApprovals: this.state.pendingApprovals.filter(
        (p) => p.workflowId !== workflowId,
      ),
    });
  }

  // Workflow lifecycle hooks
  async onWorkflowProgress(_workflowName: string, workflowId: string, progress: unknown) {
    const p = progress as { step: string; lead?: PendingApproval };
    if (p.step === 'approval' && p.lead) {
      this.setState({
        ...this.state,
        pendingApprovals: [
          ...this.state.pendingApprovals.filter((a) => a.workflowId !== workflowId),
          { ...p.lead, workflowId },
        ],
      });
    }
  }

  async onWorkflowComplete(_workflowName: string, _workflowId: string, _result?: unknown) {
    this.setState({
      ...this.state,
      metrics: {
        ...this.state.metrics,
        workflowsCompleted: this.state.metrics.workflowsCompleted + 1,
      },
    });
  }

  async onWorkflowError(_workflowName: string, _workflowId: string, _error: string) {
    this.setState({
      ...this.state,
      metrics: {
        ...this.state.metrics,
        workflowsFailed: this.state.metrics.workflowsFailed + 1,
      },
    });
  }

  // Helpers the workflow calls back into via `this.agent.X` RPC.
  async recordActivity(input: {
    leadId: string;
    workflowId: string;
    step: string;
    outcome: 'success' | 'failure' | 'retry';
    durationMs?: number;
    retryCount?: number;
    errorMessage?: string;
  }) {
    this.sql`
      INSERT INTO agent_activity (ts, lead_id, workflow_id, step, outcome, duration_ms, retry_count, error_message)
      VALUES (${Date.now()}, ${input.leadId}, ${input.workflowId}, ${input.step},
              ${input.outcome}, ${input.durationMs ?? null}, ${input.retryCount ?? 0}, ${input.errorMessage ?? null})
    `;
  }

  // Used by Phase 2 admin/activity dashboard.
  @callable()
  async getRecentActivity(limit = 100) {
    return this.sql<{
      id: number;
      ts: number;
      lead_id: string;
      workflow_id: string;
      step: string;
      outcome: string;
      duration_ms: number | null;
      retry_count: number;
      error_message: string | null;
    }>`SELECT * FROM agent_activity ORDER BY ts DESC LIMIT ${limit}`;
  }

  // Cron-scheduled sweep
  async sweepStuckRows() {
    const cutoff = new Date(Date.now() - STUCK_ROW_THRESHOLD_MINUTES * 60 * 1000).toISOString();
    const db = getDb(this.env.LEADS_DB);
    const stuck = await db
      .update(leads)
      .set({ status: 'pending' })
      .where(eq(leads.status, 'processing'))
      // Drizzle: AND updated_at < cutoff — see implementation note
      .returning({ id: leads.id });
    if (stuck.length > 0) {
      console.log(`[sweep] reset ${stuck.length} stuck rows`);
    }
  }
}

export function getTriageAgent(env: Env) {
  return getAgentByName(env.LEAD_TRIAGE, 'global');
}
```

**Key decisions**:
- Singleton agent: `getAgentByName(env.LEAD_TRIAGE, 'global')`. There's only one logical agent for the whole lead pipeline.
- `hibernate: true` in `static options` keeps DO billing minimal during idle — when the agent has no active connections or pending work, it hibernates.
- State has `pendingApprovals` (real-time UI) and `metrics` (rolled-up counts). Detailed per-event activity goes to `agent_activity` SQLite, not state — keeps state broadcast payload small.
- Activity SQL bootstrap in `onStart()` — `CREATE TABLE IF NOT EXISTS` is idempotent.
- The sweep query needs Drizzle's `and(eq(...), lt(...))` for `status='processing' AND updated_at < cutoff`. Implementation note in the agent file but trivially adjusted.

**Failure modes**:
- DO instance fails to load state → fall back to `initialState` (Agents SDK handles this).
- Activity table insert fails → log to console, don't bubble up; activity tracking is observability, not load-bearing.
- Sweep query fails → exception propagates; cron retries on next tick (5min later).
- `approveWorkflow` called for a workflow that's already completed/failed → SDK throws; the dashboard catches and shows "this approval was already processed."

### 6. Workflow class

**File**: `src/workflows/lead-triage-workflow.ts`

**Pattern to follow**: the structure mirrors the gist's `N8N_AI_FLOW.md` step-by-step except `step.do(...)` wraps each step instead of n8n nodes.

```typescript
import { AgentWorkflow, type AgentWorkflowEvent, type AgentWorkflowStep } from 'agents/workflows';
import { eq } from 'drizzle-orm';
import { generateText } from 'ai';
import { createWorkersAI } from 'workers-ai-provider';
import { getDb } from '../db/client';
import { leads } from '../db/schema';
import { ClassifyOutput, QualifyOutput } from '../lib/ai-types';
import { classifyPrompt, qualifyPrompt, draftPrompt } from '../lib/prompts';
import { MODELS, STEP_RETRY, APPROVAL_TIMEOUT, SCORE_RULES, NOTIFY_TO, NOTIFY_FROM, REPLY_FROM } from '../lib/triage-config';
import type { LeadTriageAgent, PendingApproval } from '../agents/lead-triage-agent';
import type { Env } from '../types';

interface TriageParams {
  leadId: string;
}

export class LeadTriageWorkflow extends AgentWorkflow<LeadTriageAgent, TriageParams> {
  async run(event: AgentWorkflowEvent<TriageParams>, step: AgentWorkflowStep) {
    const { leadId } = event.payload;

    // ── 1. Load lead from D1 ────────────────────────────────
    const lead = await step.do('load-lead', STEP_RETRY.persist, async () => {
      const start = Date.now();
      const db = getDb(this.env.LEADS_DB);
      const [row] = await db.select().from(leads).where(eq(leads.id, leadId)).limit(1);
      if (!row) throw new Error(`Lead ${leadId} not found`);
      // Mark processing so the sweep doesn't pick it up.
      await db
        .update(leads)
        .set({ status: 'processing' })
        .where(eq(leads.id, leadId));
      await this.agent.recordActivity({
        leadId, workflowId: this.id, step: 'load-lead',
        outcome: 'success', durationMs: Date.now() - start,
      });
      return row;
    });

    const workersai = createWorkersAI({ binding: this.env.AI });

    // ── 2. Classify ─────────────────────────────────────────
    const classification = await step.do('classify', STEP_RETRY.classify, async () => {
      const start = Date.now();
      const { system, user } = classifyPrompt(lead);
      const { text } = await generateText({
        model: workersai(MODELS.classify),
        system,
        prompt: user,
      });
      const parsed = ClassifyOutput.parse(JSON.parse(stripCodeFences(text)));
      await this.agent.recordActivity({
        leadId, workflowId: this.id, step: 'classify',
        outcome: 'success', durationMs: Date.now() - start,
      });
      return parsed;
    });

    // ── 3. Qualify (only if consulting-fit) ─────────────────
    const qualification = classification.category !== 'consulting-fit'
      ? null
      : await step.do('qualify', STEP_RETRY.qualify, async () => {
          const start = Date.now();
          const { system, user } = qualifyPrompt(lead);
          const { text } = await generateText({
            model: workersai(MODELS.qualify),
            system,
            prompt: user,
          });
          const parsed = QualifyOutput.parse(JSON.parse(stripCodeFences(text)));
          await this.agent.recordActivity({
            leadId, workflowId: this.id, step: 'qualify',
            outcome: 'success', durationMs: Date.now() - start,
          });
          return parsed;
        });

    // ── 4. Score (deterministic) ────────────────────────────
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

    // ── 5. Draft ─────────────────────────────────────────────
    const draft = await step.do('draft', STEP_RETRY.draft, async () => {
      const start = Date.now();
      const { system, user } = draftPrompt({ ...lead, classification, qualification, score });
      const { text } = await generateText({
        model: workersai(MODELS.draft),
        system,
        prompt: user,
      });
      await this.agent.recordActivity({
        leadId, workflowId: this.id, step: 'draft',
        outcome: 'success', durationMs: Date.now() - start,
      });
      return text.trim();
    });

    // ── 6. Persist triage results back to D1 ────────────────
    await step.do('persist', STEP_RETRY.persist, async () => {
      const db = getDb(this.env.LEADS_DB);
      await db.update(leads).set({
        category: classification.category,
        qualification: qualification ? JSON.stringify(qualification) : null,
        score,
        draft,
      }).where(eq(leads.id, leadId));
      await this.agent.recordActivity({
        leadId, workflowId: this.id, step: 'persist',
        outcome: 'success',
      });
    });

    // Spam path: skip notification + approval, mark done immediately.
    if (classification.category === 'spam') {
      await step.do('mark-done-spam', STEP_RETRY.persist, async () => {
        const db = getDb(this.env.LEADS_DB);
        await db.update(leads).set({ status: 'done', outcome: 'discarded' }).where(eq(leads.id, leadId));
      });
      await step.reportComplete({ outcome: 'spam-auto-discarded' });
      return;
    }

    // ── 7. Notify Nick by email ─────────────────────────────
    await step.do('notify-nick', STEP_RETRY.notify, async () => {
      const start = Date.now();
      await this.env.EMAIL.send({
        to: NOTIFY_TO,
        from: NOTIFY_FROM,
        replyTo: NOTIFY_FROM.email,
        subject: `New lead: ${lead.name}`,
        text: notifyEmailBody({ lead, classification, qualification, score, leadId }),
      });
      await this.agent.recordActivity({
        leadId, workflowId: this.id, step: 'notify-nick',
        outcome: 'success', durationMs: Date.now() - start,
      });
    });

    // ── 8. Surface as pending approval ──────────────────────
    const pending: PendingApproval = {
      workflowId: this.id,
      leadId,
      name: lead.name,
      email: lead.email,
      category: classification.category,
      score,
      draftPreview: draft.slice(0, 240),
      enqueuedAt: Date.now(),
    };
    await this.reportProgress({ step: 'approval', lead: pending });

    // ── 9. Wait for human ───────────────────────────────────
    const approval = await this.waitForApproval(step, { timeout: APPROVAL_TIMEOUT });
    if (!approval) {
      // Timeout: mark stale, no email sent.
      await step.do('mark-stale', STEP_RETRY.persist, async () => {
        const db = getDb(this.env.LEADS_DB);
        await db.update(leads).set({ status: 'done', outcome: 'discarded' }).where(eq(leads.id, leadId));
      });
      await step.reportError('Approval timeout — lead marked stale');
      return;
    }
    if (approval.rejected) {
      await step.do('mark-discarded', STEP_RETRY.persist, async () => {
        const db = getDb(this.env.LEADS_DB);
        await db.update(leads).set({ status: 'done', outcome: 'discarded' }).where(eq(leads.id, leadId));
      });
      await step.reportComplete({ outcome: 'discarded' });
      return;
    }

    const finalBody = (approval.metadata?.editedBody as string | undefined)?.trim() || draft;

    // ── 10. Send reply email ────────────────────────────────
    await step.do('send-reply', STEP_RETRY.notify, async () => {
      await this.env.EMAIL.send({
        to: lead.email,
        from: REPLY_FROM,
        replyTo: REPLY_FROM.email,
        subject: 'Re: your message via birdcar.dev',
        text: finalBody + '\n\n— Nick',
      });
      await this.agent.recordActivity({
        leadId, workflowId: this.id, step: 'send-reply',
        outcome: 'success',
      });
    });

    // ── 11. Mark done ───────────────────────────────────────
    await step.do('mark-done', STEP_RETRY.persist, async () => {
      const outcome = finalBody === draft ? 'approved' : 'edited';
      const db = getDb(this.env.LEADS_DB);
      await db.update(leads).set({
        status: 'done',
        outcome,
        responded_at: new Date().toISOString(),
        draft: finalBody,
      }).where(eq(leads.id, leadId));
    });

    await step.reportComplete({ outcome: 'replied' });
  }
}

function stripCodeFences(s: string): string {
  return s.replace(/^```(?:json)?\s*/i, '').replace(/```\s*$/, '').trim();
}

function notifyEmailBody(input: {
  lead: { name: string; email: string; message: string };
  classification: ClassifyOutput;
  qualification: QualifyOutput | null;
  score: number;
  leadId: string;
}): string {
  return `New lead from ${input.lead.name} <${input.lead.email}>

Category: ${input.classification.category} (confidence ${input.classification.confidence.toFixed(2)})
Score: ${input.score}
${input.qualification ? `Industry: ${input.qualification.industry} · Geo: ${input.qualification.geography} · Size: ${input.qualification.size_signal}` : ''}

Message:
${input.lead.message}

Review: https://birdcar.dev/admin/leads/${input.leadId}
`;
}
```

**Key decisions**:
- Each step records activity via `this.agent.recordActivity(...)` — this is the typed RPC the AgentWorkflow exposes back to the originating Agent.
- `step.do(name, retryConfig, fn)` is the pattern — the SDK auto-checkpoints, so a draft step that succeeded won't re-run if persist fails.
- Spam category short-circuits the workflow (no notification, auto-discarded). This is intentional — we don't want spam to wake up an approval request.
- `notifyEmailBody` is a plain text fn for now; HTML body could be added later (CF send_email supports both).
- The dashboard link in the notification email points to `/admin/leads/[id]` which Phase 2 builds.
- `waitForApproval` returns `null` on timeout, an object with `rejected: true` on `rejectWorkflow`, and metadata-bearing object on `approveWorkflow`. Matches the SDK contract from the human-in-the-loop docs.

**Failure modes**:
- Workers AI returns malformed JSON → Zod parse fails → `step.do` throws → step retries with exponential backoff. After 3 failures, the workflow itself fails, `onWorkflowError` increments the metric, lead row stays in `processing` (sweep eventually resets to pending).
- `env.EMAIL.send()` rejects (e.g., domain not configured) → step retries up to 3x. After failure, workflow fails; lead stays in pending state for next attempt.
- Approval times out at 7 days → workflow's `waitForApproval` returns null → workflow marks lead stale (outcome=discarded). No reply sent.
- Workflow crashes mid-step → CF Workflows resume from last checkpoint on retry. Steps already completed don't re-run.
- D1 write fails on persist → step retries 3x then bubbles up. Lead is in inconsistent state (categorized in agent.activity but not in D1) — sweep doesn't help here. Acceptable risk at v1 volume.

### 7. Form action hook

**File**: `src/actions/index.ts`

Add three lines after `await insertLead(...)`:

```typescript
import { getTriageAgent } from '../lib/agent-stub';

// ...inside the action handler, after insertLead:
try {
  const agent = await getTriageAgent(env);
  await agent.queueLead(id);
} catch (err) {
  // Triage failure is non-blocking. The sweep cron picks up unprocessed leads
  // (status='pending' rows older than 1 hour with no activity).
  console.warn(`[contact.send] triage queue failed for ${id}:`, err);
}
```

**Key decisions**:
- Wrap in try/catch so a transient agent failure doesn't break the form submit. The sweep handles eventual consistency.
- `agent.queueLead(id)` returns `{ workflowId }` but the action doesn't need it.

### 8. Wrangler config

**File**: `wrangler.jsonc` — additions to the existing config.

```jsonc
{
  // ... existing fields (name, compatibility_date, kv_namespaces, d1_databases) ...
  "ai": { "binding": "AI" },
  "durable_objects": {
    "bindings": [
      { "name": "LEAD_TRIAGE", "class_name": "LeadTriageAgent" }
    ]
  },
  "workflows": [
    {
      "name": "lead-triage-workflow",
      "binding": "LEAD_TRIAGE_WORKFLOW",
      "class_name": "LeadTriageWorkflow"
    }
  ],
  "send_email": [
    { "name": "EMAIL", "remote": true }
  ],
  "migrations": [
    { "tag": "v1", "new_sqlite_classes": ["LeadTriageAgent"] }
  ]
}
```

**Key decisions**:
- `new_sqlite_classes` (not `new_classes`) — this is the modern SQLite-backed DO syntax which is required for `this.sql` to work.
- `send_email[].remote: true` lets dev mode hit the real Email Service API (otherwise emails are simulated locally).
- `ai` binding is the only AI config needed — the SDK reads `env.AI` directly.

### 9. Env types

**File**: `src/types.ts` (new) — exported runtime env interface used by agent + workflow.

```typescript
import type { D1Database, KVNamespace, DurableObjectNamespace } from '@cloudflare/workers-types';
import type { LeadTriageAgent } from './agents/lead-triage-agent';
import type { LeadTriageWorkflow } from './workflows/lead-triage-workflow';

export interface Env {
  LEADS_DB: D1Database;
  SESSION?: KVNamespace;
  AI: Ai;
  LEAD_TRIAGE: DurableObjectNamespace<LeadTriageAgent>;
  LEAD_TRIAGE_WORKFLOW: Workflow<LeadTriageWorkflow>;
  EMAIL: SendEmail;
  // Phase 2 will add WORKOS_* fields
}
```

Reference the existing `src/lib/leads.ts` `CloudflareEnv` interface — consolidate so both files use this shared type.

### 10. Worker entrypoint registration

The Astro Cloudflare adapter generates the worker entry, but it needs to know about the agent + workflow exports. Per the Agents SDK docs, this is done by re-exporting the classes from a configured entry. With `@astrojs/cloudflare`, the adapter looks for a top-level `_worker.js` re-export pattern.

**Pattern to follow**: existing adapter setup in `astro.config.ts`. Verify against the Agents SDK guide for "add to existing project" — there's a specific re-export shape needed.

If the adapter doesn't expose a hook for this, fallback option: write `src/worker-entry.ts` that re-exports `LeadTriageAgent` and `LeadTriageWorkflow` and configure Astro to include it in the bundle. Validate during implementation.

## API Design

No new HTTP endpoints in Phase 1. The agent exposes RPC methods invoked via the agent stub from within the worker (the form action). Phase 2 adds the routes that Nick interacts with.

The deleted endpoint:
- ❌ `GET /api/leads/pending` — was an n8n contract; no consumer remains

The kept endpoint:
- ✓ `PATCH /api/leads/[id]` — kept as a debug surface; rarely called in normal operation

## Validation Steps

1. `bun add agents ai workers-ai-provider` (and supporting types if needed)
2. `bun run check` → 0 errors
3. Update `wrangler.jsonc` with all new bindings
4. `bunx wrangler dev --remote` → starts dev server with full bindings
5. Submit `/contact` form via browser. In `wrangler tail` output, observe:
   - `[contact.send] inserted lead ...`
   - workflow steps firing in order: `load-lead → classify → qualify (if consulting-fit) → score → draft → persist → notify-nick`
   - email arrives at hi@birdcar.dev within 30s with summary + dashboard link
   - workflow logs `[approval] waiting (timeout 7 days)`
6. From `wrangler dev`'s shell or via D1 console, manually run `agent.approveLead(workflowId)` to advance the workflow
7. Observe send-reply step firing → email lands in your test inbox (use a personal address as the test lead)
8. `bunx wrangler d1 execute birdcar-leads --command "SELECT id, status, outcome, responded_at FROM leads ORDER BY created_at DESC LIMIT 1"` shows status=done, outcome=approved, responded_at populated
9. `bun run build:ci` → 0 errors, worker bundle includes new DO + workflow classes
10. Push commits, watch CF deploy build, exercise on prod

## Failure Modes

Per-component failure modes are documented inline in each implementation section above. Cross-cutting:

| Component | Failure | Behavior |
|---|---|---|
| Form action | Agent stub fetch fails | Logged, swallowed; sweep picks up the orphaned `status='pending'` row |
| LeadTriageAgent state load | DO instance corrupted (rare) | `initialState` resets state; agent_activity SQLite still has historical record |
| LeadTriageWorkflow | Step crash mid-step | CF Workflows checkpoint replay — already-completed steps don't re-run |
| LeadTriageWorkflow | Hits step retry limit | `onWorkflowError` increments metric, lead stays in `status='processing'`; sweep eventually resets to `status='pending'` for retry |
| send_email | Domain misconfigured | Workflow's notify step retries 3x then fails; lead goes back to pending; you'll see the failure in `wrangler tail` |
| send_email | Recipient bounce | Out of scope to detect at v1; manual follow-up |
| Workers AI | Rate-limited | Step retries with exponential backoff |
| Workers AI | Returns garbled JSON | Zod parse fails → step retries; persistent malformed output indicates a prompt regression worth fixing |
