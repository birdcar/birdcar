import { getCollection } from 'astro:content';
import { getPostMetadata } from './markdown';

export async function getPublishedPosts() {
  const posts = await getCollection('blog', ({ data }) => {
    return import.meta.env.PROD ? !data.draft : true;
  });

  const postsWithMeta = await Promise.all(
    posts.map(async (post) => {
      const metadata = await getPostMetadata(post.body!);
      const allTags = [...new Set([
        ...post.data.tags,
        ...(metadata.computed?.tags ?? []),
      ])];
      return { ...post, metadata, allTags };
    })
  );

  return postsWithMeta.sort(
    (a, b) => b.data.date.getTime() - a.data.date.getTime()
  );
}

export async function getAllTags() {
  const posts = await getPublishedPosts();
  const tagCounts = new Map<string, number>();
  for (const post of posts) {
    for (const tag of post.allTags) {
      tagCounts.set(tag, (tagCounts.get(tag) ?? 0) + 1);
    }
  }
  return tagCounts;
}
