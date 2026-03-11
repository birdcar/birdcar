import type { QueryParams, QueryResult } from './query.js';
import fs from 'node:fs';
import path from 'node:path';

export function createBlogQueryResolver(contentDir: string) {
  return async function resolveBlogQuery(params: QueryParams): Promise<QueryResult[]> {
    const blogDir = path.join(contentDir, 'blog');
    if (!fs.existsSync(blogDir)) return [];

    const files = fs.readdirSync(blogDir).filter((f: string) => f.endsWith('.md'));
    const results: QueryResult[] = [];

    for (const file of files) {
      const content = fs.readFileSync(path.join(blogDir, file), 'utf-8');
      const frontmatter = parseFrontmatter(content);
      if (!frontmatter) continue;

      // Skip drafts in production
      if (frontmatter.draft && process.env.NODE_ENV === 'production') continue;

      const slug = file.replace(/\.md$/, '');
      const entry: QueryResult = {
        title: frontmatter.title || slug,
        url: `/blog/${slug}/`,
        date: new Date(frontmatter.date || 0),
        tags: Array.isArray(frontmatter.tags) ? frontmatter.tags : [],
      };

      // Apply filters
      if (params.tag && !entry.tags.includes(params.tag)) continue;

      results.push(entry);
    }

    // Sort
    const sort = params.sort || 'date-desc';
    if (sort === 'date-desc' || sort === 'newest') {
      results.sort((a, b) => b.date.getTime() - a.date.getTime());
    } else if (sort === 'date-asc' || sort === 'oldest') {
      results.sort((a, b) => a.date.getTime() - b.date.getTime());
    } else if (sort === 'title') {
      results.sort((a, b) => a.title.localeCompare(b.title));
    }

    // Limit
    if (params.limit && params.limit > 0) {
      return results.slice(0, params.limit);
    }

    return results;
  };
}

function parseFrontmatter(content: string): Record<string, any> | null {
  const match = content.match(/^---\n([\s\S]*?)\n---/);
  if (!match) return null;

  const yaml = match[1];
  const result: Record<string, any> = {};

  for (const line of yaml.split('\n')) {
    const kvMatch = line.match(/^(\w+):\s*(.+)/);
    if (kvMatch) {
      const [, key, value] = kvMatch;
      if (value === 'true') result[key] = true;
      else if (value === 'false') result[key] = false;
      else result[key] = value;
    }
    // Handle arrays (tags)
    if (line.match(/^\w+:$/)) {
      const key = line.replace(':', '').trim();
      result[key] = [];
    }
    const arrayItemMatch = line.match(/^\s+-\s+(.+)/);
    if (arrayItemMatch) {
      const lastArrayKey = Object.keys(result).reverse().find(k => Array.isArray(result[k]));
      if (lastArrayKey) result[lastArrayKey].push(arrayItemMatch[1]);
    }
  }

  return result;
}
