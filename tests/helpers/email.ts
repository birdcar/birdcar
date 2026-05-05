import { vi } from 'vitest';
import { env } from 'cloudflare:workers';

export interface CapturedEmail {
  to: string;
  from: { email: string; name?: string };
  replyTo?: string;
  subject: string;
  text: string;
}

/**
 * Replace `env.EMAIL.send` with a recording spy. Returns the array tests
 * assert against. Restoration is handled by `vi.restoreAllMocks()` in the
 * caller's `afterEach`.
 */
export function captureEmail(): CapturedEmail[] {
  const captured: CapturedEmail[] = [];
  // The SendEmail binding's `send` is typed via @cloudflare/workers-types
  // with EmailMessage, not the plain object the workflow actually passes.
  // Cast through `unknown` to silence the structural mismatch.
  const target = env.EMAIL as unknown as {
    send: (msg: CapturedEmail) => Promise<void>;
  };
  vi.spyOn(target, 'send').mockImplementation(async (msg) => {
    captured.push(msg);
  });
  return captured;
}
