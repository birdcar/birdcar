import { describe, test, expect } from 'bun:test';
import { classifyPrompt, draftPrompt, qualifyPrompt } from './prompts';

const lead = {
  name: 'Jordan Smith',
  email: 'jordan@example.com',
  message: 'We have 80 returns piling up in Drake every quarter.',
};

describe('classifyPrompt', () => {
  test('returns both system and user strings', () => {
    const { system, user } = classifyPrompt(lead);
    expect(typeof system).toBe('string');
    expect(typeof user).toBe('string');
    expect(system.length).toBeGreaterThan(0);
    expect(user.length).toBeGreaterThan(0);
  });

  test('user prompt contains the lead name and email verbatim', () => {
    const { user } = classifyPrompt(lead);
    expect(user).toContain('Jordan Smith');
    expect(user).toContain('jordan@example.com');
  });

  test('user prompt fences the message inside <message> tags', () => {
    const { user } = classifyPrompt(lead);
    expect(user).toContain('<message>');
    expect(user).toContain('</message>');
    expect(user).toContain(lead.message);
  });

  test('system prompt instructs the model to return JSON', () => {
    const { system } = classifyPrompt(lead);
    expect(system).toMatch(/JSON/);
    expect(system).toMatch(/Begin your response with `\{`/);
  });
});

describe('qualifyPrompt', () => {
  test('user prompt includes name, email, and fenced message', () => {
    const { user } = qualifyPrompt(lead);
    expect(user).toContain('Jordan Smith');
    expect(user).toContain('jordan@example.com');
    expect(user).toContain(`<message>\n${lead.message}\n</message>`);
  });

  test('system prompt enumerates the qualification fields', () => {
    const { system } = qualifyPrompt(lead);
    for (const field of [
      'industry',
      'geography',
      'size_signal',
      'problem_shape',
      'urgency_signal',
    ]) {
      expect(system).toContain(field);
    }
  });
});

describe('draftPrompt', () => {
  const draftInput = {
    ...lead,
    classification: {
      category: 'consulting-fit' as const,
      confidence: 0.91,
      reasoning: 'CPA with manual review queue.',
    },
    qualification: {
      industry: 'cpa' as const,
      geography: 'fort-worth' as const,
      size_signal: 'small-2-10' as const,
      problem_shape: 'review-queue' as const,
      urgency_signal: 'in-pain' as const,
    },
    score: 7,
  };

  test('user prompt embeds classification and score', () => {
    const { user } = draftPrompt(draftInput);
    expect(user).toContain('consulting-fit');
    expect(user).toContain('Score: 7');
    expect(user).toContain('Industry: cpa');
  });

  test('user prompt formats confidence to two decimals', () => {
    const { user } = draftPrompt(draftInput);
    expect(user).toContain('confidence 0.91');
  });

  test('omits qualification block when qualification is null', () => {
    const { user } = draftPrompt({ ...draftInput, qualification: null });
    expect(user).not.toContain('Industry:');
    expect(user).toContain('Qualification: n/a');
  });

  test('system prompt forbids hype words and em dashes', () => {
    const { system } = draftPrompt(draftInput);
    expect(system).toContain('No em dashes');
    expect(system.toLowerCase()).toContain('leverage');
  });
});
