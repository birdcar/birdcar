import { defineMiddleware } from 'astro:middleware';

interface AssetsBinding {
  fetch: (request: Request) => Promise<Response>;
}

interface CloudflareRuntime {
  env?: {
    ASSETS?: AssetsBinding;
  };
}

function estimateTokens(text: string): number {
  return Math.max(1, Math.ceil(text.length / 4));
}

function mapToMarkdownPath(pathname: string): string | null {
  if (pathname.endsWith('.md') || pathname.endsWith('.html')) return null;
  if (pathname.endsWith('/')) return `${pathname}index.md`;
  if (/\.[a-z0-9]{2,5}$/i.test(pathname)) return null;
  return `${pathname}/index.md`;
}

function acceptsMarkdown(request: Request): boolean {
  const header = request.headers.get('Accept') ?? '';
  return /\btext\/markdown\b/i.test(header);
}

export const onRequest = defineMiddleware(async (context, next) => {
  // Skip during static prerender — there's no real request, and reading
  // `request.headers` triggers Astro's "headers not available on
  // prerendered pages" warning on every static route. The markdown
  // negotiation is purely a runtime concern; the deployed worker still
  // runs this middleware on real requests.
  if (context.isPrerendered) {
    return next();
  }

  const { request } = context;

  if (request.method !== 'GET' && request.method !== 'HEAD') {
    return next();
  }

  if (!acceptsMarkdown(request)) {
    return next();
  }

  const url = new URL(request.url);
  const mdPath = mapToMarkdownPath(url.pathname);
  if (!mdPath) return next();

  const runtime = (context.locals as { runtime?: CloudflareRuntime }).runtime;
  const assets = runtime?.env?.ASSETS;
  if (!assets) return next();

  const mdUrl = new URL(mdPath, url.origin);
  const mdRequest = new Request(mdUrl.toString(), {
    method: request.method,
    headers: request.headers,
    redirect: 'manual',
  });

  const mdResponse = await assets.fetch(mdRequest);
  if (mdResponse.status !== 200) return next();

  const body = await mdResponse.text();
  const headers = new Headers(mdResponse.headers);
  headers.set('Content-Type', 'text/markdown; charset=utf-8');
  headers.set('X-Markdown-Tokens', String(estimateTokens(body)));
  headers.set('Vary', 'Accept');

  if (request.method === 'HEAD') {
    return new Response(null, { status: 200, headers });
  }
  return new Response(body, { status: 200, headers });
});
