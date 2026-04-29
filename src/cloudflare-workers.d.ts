/**
 * Minimal ambient declaration for `cloudflare:workers`. The full module
 * lives in `@cloudflare/workers-types/index.d.ts`, but loading that file
 * via triple-slash reference duplicates Cloudflare types already pulled
 * in by Astro's auto-generated `.astro/types.d.ts` and breaks the
 * AgentWorkflow / Ai signatures with cross-package brand mismatches.
 *
 * We only access `env` from this module at runtime (in `src/lib/leads.ts`),
 * so a narrow declaration is enough to satisfy the type checker without
 * disturbing the rest of the type graph.
 *
 * Lives in a standalone `.d.ts` because TypeScript treats `declare module`
 * inside a module file (one with imports/exports) as augmentation, which
 * fails when no other declaration of the module is in scope.
 */
declare module 'cloudflare:workers' {
  export const env: Cloudflare.Env;
}
