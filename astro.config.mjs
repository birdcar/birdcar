import { defineConfig } from 'astro/config';
import mdx from '@astrojs/mdx';
import sitemap from '@astrojs/sitemap';
import tailwind from "@astrojs/tailwind";
import markdoc from "@astrojs/markdoc";
import netlify from "@astrojs/netlify/functions";
import prefetch from "@astrojs/prefetch";
import partytown from "@astrojs/partytown";

import alpinejs from "@astrojs/alpinejs";

// https://astro.build/config
export default defineConfig({
  site: 'https://example.com',
  integrations: [mdx(), sitemap(), tailwind(), markdoc(), prefetch(), partytown(), alpinejs()],
  output: "hybrid",
  adapter: netlify()
});
