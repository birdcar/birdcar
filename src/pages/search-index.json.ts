import type { APIRoute } from 'astro';
import { getPublishedPosts } from '../lib/posts';

export const GET: APIRoute = async () => {
  const posts = await getPublishedPosts();
  const entries = posts.map((post) => ({
    id: post.id,
    title: post.data.title,
    description: post.data.description ?? '',
    tags: post.allTags,
    date: post.data.date.toISOString(),
  }));
  return new Response(JSON.stringify(entries), {
    headers: { 'Content-Type': 'application/json; charset=utf-8' },
  });
};
