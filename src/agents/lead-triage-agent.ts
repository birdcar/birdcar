import { Agent, callable } from 'agents';
import { and, eq, lt } from 'drizzle-orm';
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
  draftPreview: string;
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

interface ActivityRow {
  id: number;
  ts: number;
  lead_id: string;
  workflow_id: string;
  step: string;
  outcome: string;
  duration_ms: number | null;
  retry_count: number;
  error_message: string | null;
}

interface RecordActivityInput {
  leadId: string;
  workflowId: string;
  step: string;
  outcome: 'success' | 'failure' | 'retry';
  durationMs?: number;
  retryCount?: number;
  errorMessage?: string;
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
    // Activity log table — observability surface for the workflow.
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

    // Cron-scheduled sweep that recovers leads stuck in `processing`.
    await this.schedule(SWEEP_CRON, 'sweepStuckRows', {});
  }

  /**
   * Called by the contact form action right after the lead is inserted into D1.
   * Returns the workflow instance ID so callers can correlate later.
   */
  @callable()
  async queueLead(leadId: string): Promise<{ workflowId: string }> {
    console.log(`[agent.queueLead] starting workflow for lead ${leadId}`);
    try {
      const workflowId = await this.runWorkflow('LEAD_TRIAGE_WORKFLOW', { leadId });
      console.log(`[agent.queueLead] workflow ${workflowId} started for lead ${leadId}`);
      this.setState({
        ...this.state,
        metrics: {
          ...this.state.metrics,
          leadsProcessed: this.state.metrics.leadsProcessed + 1,
        },
      });
      return { workflowId };
    } catch (err) {
      console.error(`[agent.queueLead] runWorkflow failed for lead ${leadId}:`, err);
      throw err;
    }
  }

  /**
   * Approve a pending lead. Phase 1 only supports approve-as-drafted; the
   * `editedBody` parameter is reserved for Phase 2 once the dashboard exists
   * (the dashboard will pass it through `metadata.editedBody`).
   */
  @callable()
  async approveLead(workflowId: string, editedBody?: string): Promise<void> {
    await this.approveWorkflow(workflowId, {
      reason: editedBody ? 'Approved with edit' : 'Approved as drafted',
      metadata: {
        editedBody: editedBody ?? null,
        approvedAt: Date.now(),
      },
    });
    this.clearPending(workflowId);
  }

  @callable()
  async discardLead(workflowId: string, reason?: string): Promise<void> {
    await this.rejectWorkflow(workflowId, { reason: reason ?? 'Discarded' });
    this.clearPending(workflowId);
  }

  /**
   * Workflow lifecycle hook. Triggered by `this.reportProgress(...)` in the
   * workflow when it surfaces a draft for approval.
   */
  async onWorkflowProgress(
    _workflowName: string,
    workflowId: string,
    progress: unknown,
  ): Promise<void> {
    const p = progress as { step?: string; lead?: PendingApproval };
    if (p.step === 'approval' && p.lead) {
      const next = this.state.pendingApprovals.filter((a) => a.workflowId !== workflowId);
      next.push({ ...p.lead, workflowId });
      this.setState({ ...this.state, pendingApprovals: next });
    }
  }

  async onWorkflowComplete(
    _workflowName: string,
    _workflowId: string,
    _result?: unknown,
  ): Promise<void> {
    this.setState({
      ...this.state,
      metrics: {
        ...this.state.metrics,
        workflowsCompleted: this.state.metrics.workflowsCompleted + 1,
      },
    });
  }

  async onWorkflowError(
    _workflowName: string,
    _workflowId: string,
    _error: string,
  ): Promise<void> {
    this.setState({
      ...this.state,
      metrics: {
        ...this.state.metrics,
        workflowsFailed: this.state.metrics.workflowsFailed + 1,
      },
    });
  }

  /**
   * RPC method called from the workflow on every step. Best-effort logging:
   * we never want activity-logging failures to fail a workflow step, so this
   * swallows errors and reports to console instead.
   */
  async recordActivity(input: RecordActivityInput): Promise<void> {
    try {
      this.sql`
        INSERT INTO agent_activity
          (ts, lead_id, workflow_id, step, outcome, duration_ms, retry_count, error_message)
        VALUES
          (${Date.now()}, ${input.leadId}, ${input.workflowId}, ${input.step},
           ${input.outcome}, ${input.durationMs ?? null}, ${input.retryCount ?? 0},
           ${input.errorMessage ?? null})
      `;
    } catch (err) {
      console.warn('[agent.recordActivity] insert failed:', err);
    }
  }

  @callable()
  async getRecentActivity(limit = 100): Promise<ActivityRow[]> {
    const rows = this.sql<ActivityRow>`
      SELECT * FROM agent_activity ORDER BY ts DESC LIMIT ${limit}
    `;
    return Array.from(rows);
  }

  /**
   * Cron sweep — the safety net for two distinct failure modes:
   *
   *   1. Workflow stalled mid-flight: lead is `processing`, `updatedAt`
   *      hasn't moved in >threshold minutes. Reset to `pending` so the
   *      second pass picks it up.
   *   2. Workflow never started: lead is `pending`, `submittedAt` is
   *      >threshold old, no row activity. Either the queue.send call
   *      failed silently in the action or the queue consumer never fired.
   *      Re-trigger the workflow directly via the agent so recovery
   *      doesn't depend on the queue path being healthy.
   *
   * Idempotent. Goes through `runWorkflow` directly (not the queue) so
   * the sweep is a true secondary trigger, not an echo of the primary.
   */
  async sweepStuckRows(): Promise<void> {
    const cutoff = new Date(Date.now() - STUCK_ROW_THRESHOLD_MINUTES * 60 * 1000).toISOString();
    const db = getDb(this.env.LEADS_DB);

    // Step 1: reset stalled `processing` rows back to `pending`.
    const reset = await db
      .update(leads)
      .set({ status: 'pending' })
      .where(and(eq(leads.status, 'processing'), lt(leads.updatedAt, cutoff)))
      .returning({ id: leads.id });
    if (reset.length > 0) {
      console.log(`[sweep] reset ${reset.length} stalled processing row(s) to pending`);
    }

    // Step 2: re-trigger any `pending` rows older than the threshold.
    // Catches both the freshly-reset rows from step 1 and rows where the
    // queue path never delivered the message in the first place.
    const stale = await db
      .select({ id: leads.id })
      .from(leads)
      .where(and(eq(leads.status, 'pending'), lt(leads.submittedAt, cutoff)));
    if (stale.length === 0) return;
    console.log(`[sweep] re-triggering ${stale.length} stuck pending row(s)`);
    for (const row of stale) {
      try {
        const workflowId = await this.runWorkflow('LEAD_TRIAGE_WORKFLOW', { leadId: row.id });
        console.log(`[sweep] re-triggered lead ${row.id} as workflow ${workflowId}`);
      } catch (err) {
        console.error(`[sweep] runWorkflow failed for lead ${row.id}:`, err);
      }
    }
  }

  private clearPending(workflowId: string): void {
    this.setState({
      ...this.state,
      pendingApprovals: this.state.pendingApprovals.filter((p) => p.workflowId !== workflowId),
    });
  }
}
