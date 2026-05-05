// Phase 1 skeleton — Phase 2 will populate with deterministic AI responses
// keyed by prompt prefix (matches the `extractJson` pattern in
// src/workflows/lead-triage-workflow.ts:357).
//
// Usage from a workflow test:
//   vi.mock('ai', () => mockWorkersAI({
//     classify: { category: 'consulting-fit', confidence: 0.9, reason: 'fits' },
//     qualify:  { industry: 'b2b-saas', geography: 'usa', size_signal: 'mid-market', problem_shape: 'support-ops', urgency_signal: 'in-pain' },
//     draft: 'Hey — happy to chat about this.',
//   }));
import type { ClassifyOutput, QualifyOutput } from '../../src/lib/ai-types';

export interface MockAIPlan {
  classify?: ClassifyOutput;
  qualify?: QualifyOutput;
  draft?: string;
}

export function mockWorkersAI(plan: MockAIPlan) {
  return {
    generateText: async ({ prompt }: { prompt: string }) => {
      if (/classify/i.test(prompt)) {
        if (plan.classify) return { text: JSON.stringify(plan.classify) };
        throw new Error('mockWorkersAI: prompt looks like classify but plan.classify is missing');
      }
      if (/qualify/i.test(prompt)) {
        if (plan.qualify) return { text: JSON.stringify(plan.qualify) };
        throw new Error('mockWorkersAI: prompt looks like qualify but plan.qualify is missing');
      }
      if (plan.draft !== undefined) return { text: plan.draft };
      throw new Error(
        `mockWorkersAI received an unrecognized prompt prefix; first 80 chars:\n${prompt.slice(0, 80)}`,
      );
    },
  };
}
