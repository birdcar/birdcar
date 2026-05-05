// Mock for the `ai` package's `generateText`. The lead-triage workflow calls
// `generateText({ model, system, prompt })` three times per lead (classify,
// qualify, draft). Dispatch on the `system` string — the user `prompt` field
// carries the lead message body, which doesn't reliably contain the verb.
//
// Anchors come from src/lib/prompts.ts:
//   classify: "You are sorting incoming inquiries"
//   qualify:  "You are profiling a consulting-fit inquiry"
//   draft:    "You are drafting Nick Cannariato's reply"
//
// Usage in a workflow test:
//   vi.mock('ai', () => ({
//     generateText: mockWorkersAI({ classify, qualify, draft }).generateText,
//   }));
import type { ClassifyOutput, QualifyOutput } from '../../src/lib/ai-types';

export interface MockAIPlan {
  classify?: ClassifyOutput;
  qualify?: QualifyOutput;
  draft?: string;
}

export function mockWorkersAI(plan: MockAIPlan) {
  return {
    generateText: async ({
      system,
      prompt,
    }: {
      system?: string;
      prompt: string;
    }) => {
      const sys = system ?? '';
      if (/sorting incoming inquiries/i.test(sys)) {
        if (plan.classify) return { text: JSON.stringify(plan.classify) };
        throw new Error('mockWorkersAI: classify call but plan.classify is missing');
      }
      if (/profiling.*inquiry/i.test(sys)) {
        if (plan.qualify) return { text: JSON.stringify(plan.qualify) };
        throw new Error('mockWorkersAI: qualify call but plan.qualify is missing');
      }
      if (/drafting.*reply/i.test(sys)) {
        if (plan.draft !== undefined) return { text: plan.draft };
        throw new Error('mockWorkersAI: draft call but plan.draft is missing');
      }
      throw new Error(
        `mockWorkersAI received an unrecognized system prefix; first 80 chars:\n${sys.slice(0, 80)}\nprompt first 40: ${prompt.slice(0, 40)}`,
      );
    },
  };
}
