/**
 * Cloudflare Pages Advanced-mode worker for birdcar.dev.
 *
 * Serves a markdown version of the requested page when the client sends
 * `Accept: text/markdown`, falling back to the HTML asset otherwise.
 * Markdown siblings (path/index.md) are emitted at build time by
 * scripts/post-build.ts.
 */

function estimateTokens(text) {
  return Math.max(1, Math.ceil(text.length / 4));
}

function mapToMarkdownPath(pathname) {
  if (pathname.endsWith('.md') || pathname.endsWith('.html')) return null;
  if (pathname.endsWith('/')) return `${pathname}index.md`;
  if (/\.[a-z0-9]{2,5}$/i.test(pathname)) return null;
  return `${pathname}/index.md`;
}

function acceptsMarkdown(request) {
  const header = request.headers.get('Accept') || '';
  return /\btext\/markdown\b/i.test(header);
}

export default {
  async fetch(request, env) {
    if (request.method !== 'GET' && request.method !== 'HEAD') {
      return env.ASSETS.fetch(request);
    }

    if (!acceptsMarkdown(request)) {
      return env.ASSETS.fetch(request);
    }

    const url = new URL(request.url);
    const mdPath = mapToMarkdownPath(url.pathname);
    if (!mdPath) return env.ASSETS.fetch(request);

    const mdUrl = new URL(mdPath, url.origin);
    const mdRequest = new Request(mdUrl.toString(), {
      method: request.method,
      headers: request.headers,
      redirect: 'manual',
    });

    const mdResponse = await env.ASSETS.fetch(mdRequest);
    if (mdResponse.status !== 200) {
      return env.ASSETS.fetch(request);
    }

    const body = await mdResponse.text();
    const headers = new Headers(mdResponse.headers);
    headers.set('Content-Type', 'text/markdown; charset=utf-8');
    headers.set('X-Markdown-Tokens', String(estimateTokens(body)));
    headers.set('Vary', 'Accept');

    if (request.method === 'HEAD') {
      return new Response(null, { status: 200, headers });
    }
    return new Response(body, { status: 200, headers });
  },
};
