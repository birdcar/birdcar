import { defineConfig } from 'astro/config';
import remarkGfm from 'remark-gfm';
import { remarkBfm } from '@birdcar/markdown';
import { mention, hashtag } from './src/lib/bfm-handlers';

export default defineConfig({
  site: 'https://birdcar.dev',
  output: 'static',
  markdown: {
    remarkPlugins: [remarkGfm, remarkBfm as any],
    remarkRehype: {
      handlers: {
        mention,
        hashtag,
      } as any,
    },
    shikiConfig: {
      theme: 'github-dark',
    },
  },
});
