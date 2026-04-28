import { z } from 'zod';

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
