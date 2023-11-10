import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import tailwind from "@astrojs/tailwind";
import markdoc from "@astrojs/markdoc";
import netlify from "@astrojs/netlify/functions";
import prefetch from "@astrojs/prefetch";
import partytown from "@astrojs/partytown";
import alpinejs from "@astrojs/alpinejs";

import lit from "@astrojs/lit";

// https://astro.build/config
export default defineConfig({
  site: 'https://birdcar.dev',
  integrations: [sitemap(), tailwind(), prefetch(), partytown(), alpinejs(), lit(), markdoc()],
  output: "hybrid",
  adapter: netlify(),
});
