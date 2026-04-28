#!/usr/bin/env bun
/**
 * Pulls the latest 5 entries from the live RSS feed and rewrites the
 * BLOG-POST-LIST block in README.md. Replaces the gautamkrishnar action
 * that was collapsing every entry to a single line.
 */
import { readFile, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const FEED_URL = 'https://birdcar.dev/rss.xml';
const README = resolve(import.meta.dir, '..', 'README.md');
const MAX_ENTRIES = 5;
const START = '<!-- BLOG-POST-LIST:START -->';
const END = '<!-- BLOG-POST-LIST:END -->';

const ENTITIES: Record<string, string> = {
  '&apos;': "'",
  '&quot;': '"',
  '&amp;': '&',
  '&lt;': '<',
  '&gt;': '>',
};

function decodeEntities(s: string): string {
  return s.replace(/&(?:apos|quot|amp|lt|gt);/g, (m) => ENTITIES[m] ?? m);
}

function pick(item: string, tag: string): string {
  const m = item.match(new RegExp(`<${tag}[^>]*>([\\s\\S]*?)<\\/${tag}>`));
  if (!m) throw new Error(`<${tag}> missing in RSS item`);
  return decodeEntities(m[1].trim());
}

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
function formatDate(rfc822: string): string {
  const d = new Date(rfc822);
  if (Number.isNaN(d.getTime())) throw new Error(`Bad pubDate: ${rfc822}`);
  return `${MONTHS[d.getUTCMonth()]} ${d.getUTCDate()}, ${d.getUTCFullYear()}`;
}

const res = await fetch(FEED_URL, { headers: { 'user-agent': 'birdcar-readme-updater' } });
if (!res.ok) throw new Error(`RSS fetch failed: ${res.status}`);
const xml = await res.text();

const items = [...xml.matchAll(/<item>([\s\S]*?)<\/item>/g)]
  .slice(0, MAX_ENTRIES)
  .map((m) => ({
    title: pick(m[1], 'title'),
    link: pick(m[1], 'link'),
    date: formatDate(pick(m[1], 'pubDate')),
  }));

if (items.length === 0) throw new Error('No <item> elements parsed from feed');

const block = items.map((i) => `- [${i.title}](${i.link}) — ${i.date}`).join('\n');

const readme = await readFile(README, 'utf8');
const re = new RegExp(`${START}[\\s\\S]*?${END}`);
if (!re.test(readme)) throw new Error('BLOG-POST-LIST markers not found in README');

const next = readme.replace(re, `${START}\n${block}\n${END}`);

if (next === readme) {
  console.log('README unchanged.');
  process.exit(0);
}

await writeFile(README, next);
console.log(`Updated README with ${items.length} entries.`);
