import { defineConfig } from 'astro/config';
import type { Plugin } from 'vite';
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

/**
 * The Astro Cloudflare adapter generates a worker entry that only exports a
 * default `fetch` handler. Cloudflare's vite plugin requires Durable Object
 * and Workflow classes to be re-exported from that same entry chunk so
 * miniflare can wrap them. This plugin appends explicit named exports for
 * `LeadTriageAgent` and `LeadTriageWorkflow` to the SSR entry chunk during
 * `generateBundle` — the classes are bundled inline (vite.ssr.noExternal is
 * set by the adapter) so their identifiers are reachable at top level.
 */
function exportAgentsForCloudflare(): Plugin {
  const classNames = ['LeadTriageAgent', 'LeadTriageWorkflow'];
  return {
    name: 'birdcar:export-agents-for-cloudflare',
    enforce: 'post',
    generateBundle(_options, bundle) {
      for (const fileName of Object.keys(bundle)) {
        const chunk = bundle[fileName];
        if (!chunk || chunk.type !== 'chunk' || !chunk.isEntry) continue;
        const present = classNames.filter((name) =>
          new RegExp(`(?:^|[\\s;])class\\s+${name}\\b`).test(chunk.code),
        );
        if (present.length === 0) continue;
        const already = classNames.some((name) =>
          new RegExp(`export\\s*\\{[^}]*\\b${name}\\b[^}]*\\}`).test(chunk.code),
        );
        if (already) continue;
        chunk.code += `\nexport { ${present.join(', ')} };\n`;
      }
    },
  };
}

// Compose BFM plugins individually — skip frontmatter (Astro handles it)
// and footnotes (Astro's built-in remark-gfm handles them).
export default defineConfig({
  site: 'https://birdcar.dev',
  output: 'static',
  adapter: cloudflare({
    imageService: 'compile',
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
    plugins: [exportAgentsForCloudflare()],
    server: {
      fs: {
        strict: false,
      },
    },
    ssr: {
      // Native bindings + Node-only deps used solely by prerendered routes
      // (the OG image generator). Keep them out of the worker bundle.
      external: ['@resvg/resvg-js', 'satori', '@aws-sdk/client-s3'],
      optimizeDeps: {
        // Keep the adapter's worker entry out of esbuild pre-bundling so the
        // exportAgentsForCloudflare plugin's transform hook can rewrite it.
        exclude: ['@astrojs/cloudflare/entrypoints/server'],
      },
    },
    optimizeDeps: {
      exclude: ['@astrojs/cloudflare/entrypoints/server'],
    },
  },
});