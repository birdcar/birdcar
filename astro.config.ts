import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
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
  site: 'https://birdcar.dev',
  output: 'static',
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
    server: {
      fs: {
        strict: false,
      },
    },
  },
});