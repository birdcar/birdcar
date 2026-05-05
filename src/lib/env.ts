import type { Env } from '../types';

interface AssetsBinding {
  fetch: (request: Request) => Promise<Response>;
}

export type EnvWithAssets = Env & { ASSETS?: AssetsBinding };

/**
 * Resolve the Cloudflare runtime env. Astro 6 removed `Astro.locals.runtime.env`;
 * the supported pattern is `import { env } from 'cloudflare:workers'`.
 *
 * Imported lazily so this module can be transitively pulled into the
 * Node-prerender bundle (via Astro's action manifest) without crashing
 * the build — `cloudflare:workers` is workerd-only and Node's loader
 * rejects the scheme.
 *
 * Not memoized: pool-workers test isolation can leave a stale `null`
 * cached across test boundaries, and the dynamic-import cost is negligible
 * once the module has loaded once in the worker isolate.
 */
export async function getCloudflareEnv(): Promise<EnvWithAssets | null> {
  try {
    const { env } = await import('cloudflare:workers');
    return (env as EnvWithAssets) ?? null;
  } catch {
    return null;
  }
}
