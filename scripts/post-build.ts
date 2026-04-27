/// <reference types="bun-types" />
import { readdir, readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import TurndownService from 'turndown';
import { parse } from 'node-html-parser';

const DIST_DIR = join(process.cwd(), 'dist');

async function walkHtml(dir: string): Promise<string[]> {
  const entries = await readdir(dir, { withFileTypes: true });
  const files: string[] = [];
  for (const entry of entries) {
    const fullPath = join(dir, entry.name);
    if (entry.isDirectory()) {
      files.push(...await walkHtml(fullPath));
    } else if (entry.name.endsWith('.html')) {
      files.push(fullPath);
    }
  }
  return files;
}

function createTurndown(): TurndownService {
  const td = new TurndownService({
    headingStyle: 'atx',
    codeBlockStyle: 'fenced',
    bulletListMarker: '-',
    emDelimiter: '_',
  });
  td.remove((node) => {
    const tag = node.nodeName?.toLowerCase();
    return tag === 'script' || tag === 'style' || tag === 'noscript' || tag === 'svg';
  });
  return td;
}

async function htmlToMarkdown(html: string, turndown: TurndownService): Promise<string | null> {
  const root = parse(html);
  const main = root.querySelector('main');
  if (!main) return null;
  main.querySelectorAll('.hero-decoration, script, style, noscript').forEach((el) => el.remove());
  const title = root.querySelector('title')?.text?.trim() ?? '';
  const descMeta = root.querySelector('meta[name="description"]');
  const description = descMeta?.getAttribute('content')?.trim() ?? '';
  const body = turndown.turndown(main.innerHTML);
  const frontmatter: string[] = [];
  if (title) frontmatter.push(`title: ${JSON.stringify(title)}`);
  if (description) frontmatter.push(`description: ${JSON.stringify(description)}`);
  const header = frontmatter.length ? `---\n${frontmatter.join('\n')}\n---\n\n` : '';
  return `${header}${body.trim()}\n`;
}

async function generateMarkdown(): Promise<{ written: number; skipped: number }> {
  const htmlFiles = await walkHtml(DIST_DIR);
  const turndown = createTurndown();
  let written = 0;
  let skipped = 0;
  for (const htmlPath of htmlFiles) {
    const html = await readFile(htmlPath, 'utf8');
    const md = await htmlToMarkdown(html, turndown);
    if (!md) {
      skipped++;
      continue;
    }
    const mdPath = htmlPath.replace(/\.html$/, '.md');
    await writeFile(mdPath, md, 'utf8');
    written++;
  }
  return { written, skipped };
}

async function main(): Promise<void> {
  const start = Date.now();
  const { written, skipped } = await generateMarkdown();
  const elapsed = ((Date.now() - start) / 1000).toFixed(2);
  console.log(
    `post-build: wrote ${written} .md files (skipped ${skipped}) in ${elapsed}s`,
  );
}

main().catch((err) => {
  console.error('post-build failed:', err);
  process.exit(1);
});
