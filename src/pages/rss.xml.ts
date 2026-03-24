import rss from '@astrojs/rss';
import type { APIContext } from 'astro';
import { getPublishedPosts } from '../lib/posts';
import { renderPostToHtml } from '../lib/markdown';

export async function GET(context: APIContext) {
  const posts = await getPublishedPosts();

  const items = await Promise.all(
    posts.map(async (post) => {
      const html = await renderPostToHtml(post.body!);
      return {
        title: post.data.title,
        pubDate: post.data.date,
        description: post.data.description ?? '',
        link: `/blog/${post.id}/`,
        content: html,
      };
    })
  );

  return rss({
    title: 'birdcar',
    description: 'Developer blog by Nick Cannariato. Writing about software, systems, and the craft of building things.',
    site: context.site!,
    items,
    xmlns: {
      content: 'http://purl.org/rss/1.0/modules/content/',
      atom: 'http://www.w3.org/2005/Atom',
    },
    customData: [
      '<language>en-us</language>',
      `<atom:link href="${context.site}rss.xml" rel="self" type="application/rss+xml" />`,
    ].join('\n'),
    stylesheet: '/rss-styles.xsl',
  });
}
