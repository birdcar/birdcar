import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import cloudflare from '@astrojs/cloudflare';
import { remarkBfmDirectives } from '@birdcar/markdown/directives';
import { remarkBfmTasks } from '@birdcar/markdown/tasks';
import { remarkBfmModifiers } from '@birdcar/markdown/modifiers';
import { remarkBfmMentions } from '@birdcar/markdown/mentions';
import { remarkBfmHashtags } from '@birdcar/markdown/hashtags';
import { mention, hashtag, directiveBlock } from './src/lib/bfm-handlers';
import { rehypeBfmMath } from './src/lib/rehype/math';
import { rehypeBfmInclude } from './src/lib/rehype/include';
import { rehypeBfmQuery } from './src/lib/rehype/query';
import { rehypeBfmEmbed } from './src/lib/rehype/embed';
import { rehypeBfmFigureSrc } from './src/lib/rehype/figure-src';
import { rehypeBfmChart } from './src/lib/rehype/chart';
import { createBlogQueryResolver } from './src/lib/rehype/query-resolver';
import { rehypeImageCdn } from './src/plugins/rehype-image-cdn';

// Compose BFM plugins individually — skip frontmatter (Astro handles it)
// and footnotes (Astro's built-in remark-gfm handles them).
export default defineConfig({
  site: 'https://www.birdcar.dev',
  output: 'static',
  adapter: cloudflare({
    imageService: 'compile',
    // Disable remote-proxy sessions in CI only. The AI binding is implicitly
    // remote, so any environment without a valid `wrangler login` session
    // (e.g. GitHub Actions) fails the build when it tries to start the proxy.
    // Locally we leave it on so `astro dev` can hit Workers AI and the email
    // proxy for real. CI sets `CI=true` automatically; see `process.env.CI`.
    remoteBindings: process.env.CI ? false : undefined,
    // Prerender static routes through Node instead of workerd. The default
    // workerd prerenderer spins up a separate cf-vite-plugin instance whose
    // config doesn't inherit `remoteBindings: false`, so it tries to start
    // a remote proxy and fails the build when wrangler isn't authenticated.
    // Our static routes are content-only (markdown, OG images via satori +
    // resvg) — none of them rely on workerd-specific runtime — so Node is
    // the correct environment regardless.
    prerenderEnvironment: 'node',
  }),
  integrations: [sitemap()],
  markdown: {
    remarkPlugins: [
      remarkBfmDirectives as any,
      remarkBfmTasks as any,
      remarkBfmModifiers as any,
      remarkBfmMentions as any,
      remarkBfmHashtags as any,
    ],
    rehypePlugins: [
      rehypeBfmMath,
      [rehypeBfmInclude, { basePath: process.cwd() }],
      [rehypeBfmQuery, { resolver: createBlogQueryResolver(process.cwd() + '/src/content') }],
      [rehypeBfmFigureSrc, { basePath: process.cwd() }],
      [rehypeBfmChart, { basePath: process.cwd() }],
      rehypeBfmEmbed,
      ...(process.env.CDN_BASE_URL
        ? [[rehypeImageCdn, { cdnBaseUrl: process.env.CDN_BASE_URL }]]
        : []),
    ] as any,
    remarkRehype: {
      handlers: {
        mention,
        hashtag,
        directiveBlock,
      } as any,
    },
    shikiConfig: {
      theme: 'catppuccin-mocha',
    },
  },
  vite: {
    // Force esbuild to transpile TC39 stage 3 decorators. The Cloudflare
    // Agents SDK uses `@callable()` on agent methods, and workerd's parser
    // doesn't accept decorator syntax at sync/runtime — without this, the
    // module runner throws "Invalid or unexpected token" when it pulls in
    // `LeadTriageAgent`. Telling esbuild the target doesn't support
    // decorators forces it to lower them to plain function calls.
    esbuild: {
      supported: {
        decorators: false,
      },
    },
    server: {
      fs: {
        strict: false,
      },
    },
    ssr: {
      // Native bindings + Node-only deps used solely by prerendered routes
      // (the OG image generator). Keep them out of the worker bundle.
      external: ['@resvg/resvg-js', 'satori', '@aws-sdk/client-s3'],
    },
  },
});