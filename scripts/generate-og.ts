/// <reference types="bun-types" />
/**
 * Generate per-post Open Graph images at build time.
 *
 * Reads src/content/blog/*.md, renders an OG card with Satori + Resvg, writes
 * PNGs to public/og/<slug>.png. The output is gitignored and produced on every
 * build. Runs before `astro build` so the PNGs ship as static assets.
 *
 * This used to be an Astro route at src/pages/og/[slug].png.ts, but the
 * @astrojs/cloudflare adapter can't bundle native deps (resvg) for prerender.
 * Pulling generation out of the route + into a Bun script bypasses that —
 * native deps run in plain Node, the route surface stays adapter-clean.
 */
import { readdir, readFile, writeFile, mkdir } from 'node:fs/promises';
import { join } from 'node:path';
import satori from 'satori';
import { Resvg } from '@resvg/resvg-js';

const ROOT = process.cwd();
const POSTS_DIR = join(ROOT, 'src', 'content', 'blog');
const FONTS_DIR = join(ROOT, 'src', 'assets', 'fonts');
const OUT_DIR = join(ROOT, 'public', 'og');

const colors = {
  paper: '#F7F3EC',
  ink: '#1A1814',
  ink3: '#6B645A',
  rule: '#D9D1BE',
  clay: '#B8442B',
} as const;

interface PostMeta {
  slug: string;
  title: string;
  tag?: string;
  draft: boolean;
  date: Date;
}

function parseFrontmatter(raw: string): Record<string, string | string[] | boolean | undefined> {
  const match = raw.match(/^---\n([\s\S]*?)\n---/);
  if (!match) return {};
  const out: Record<string, string | string[] | boolean | undefined> = {};
  for (const line of match[1].split('\n')) {
    const m = line.match(/^([A-Za-z0-9_]+):\s*(.*)$/);
    if (!m) continue;
    const [, key, rawValue] = m;
    const value = rawValue.trim();
    if (value === 'true') out[key] = true;
    else if (value === 'false') out[key] = false;
    else if (value.startsWith('[') && value.endsWith(']')) {
      out[key] = value
        .slice(1, -1)
        .split(',')
        .map((s) => s.trim().replace(/^['"]|['"]$/g, ''))
        .filter(Boolean);
    } else {
      out[key] = value.replace(/^['"]|['"]$/g, '');
    }
  }
  return out;
}

async function readPosts(): Promise<PostMeta[]> {
  const entries = await readdir(POSTS_DIR, { withFileTypes: true });
  const posts: PostMeta[] = [];
  for (const entry of entries) {
    if (!entry.isFile() || !entry.name.endsWith('.md')) continue;
    const slug = entry.name.replace(/\.md$/, '');
    const raw = await readFile(join(POSTS_DIR, entry.name), 'utf8');
    const fm = parseFrontmatter(raw);
    const title = typeof fm.title === 'string' ? fm.title : slug;
    const tagsArr = Array.isArray(fm.tags) ? fm.tags : [];
    const tag = typeof tagsArr[0] === 'string' ? tagsArr[0] : undefined;
    const draft = fm.draft === true;
    const date = typeof fm.date === 'string' ? new Date(fm.date) : new Date();
    posts.push({ slug, title, tag, draft, date });
  }
  return posts;
}

function ogTemplate(title: string, slug: string, tag?: string): unknown {
  const eyebrow = (tag ?? 'writing').toUpperCase();
  return {
    type: 'div',
    props: {
      style: {
        display: 'flex',
        flexDirection: 'column',
        width: 1200,
        height: 630,
        backgroundColor: colors.paper,
        position: 'relative',
        fontFamily: 'SourceSerif4',
      },
      children: [
        {
          type: 'div',
          props: {
            style: {
              display: 'flex',
              flexDirection: 'column',
              flex: 1,
              padding: '80px 80px 0 80px',
              justifyContent: 'center',
            },
            children: [
              {
                type: 'div',
                props: {
                  style: {
                    display: 'flex',
                    fontFamily: 'JetBrainsMono',
                    fontWeight: 400,
                    fontSize: 18,
                    color: colors.clay,
                    letterSpacing: 4,
                    marginBottom: 28,
                  },
                  children: eyebrow,
                },
              },
              {
                type: 'div',
                props: {
                  style: {
                    display: 'flex',
                    fontFamily: 'SourceSerif4',
                    fontWeight: 700,
                    fontSize: title.length > 60 ? 64 : 76,
                    lineHeight: 1.05,
                    letterSpacing: '-0.025em',
                    color: colors.ink,
                    maxWidth: 1040,
                  },
                  children: title,
                },
              },
            ],
          },
        },
        {
          type: 'div',
          props: {
            style: {
              display: 'flex',
              height: 1,
              backgroundColor: colors.rule,
              margin: '0 80px',
            },
            children: '',
          },
        },
        {
          type: 'div',
          props: {
            style: {
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              height: 80,
              padding: '0 80px',
            },
            children: [
              {
                type: 'div',
                props: {
                  style: {
                    display: 'flex',
                    fontFamily: 'SourceSerif4',
                    fontWeight: 700,
                    fontSize: 32,
                    color: colors.ink,
                    letterSpacing: '-0.015em',
                  },
                  children: 'Birdcar',
                },
              },
              {
                type: 'div',
                props: {
                  style: {
                    display: 'flex',
                    fontFamily: 'JetBrainsMono',
                    fontWeight: 400,
                    fontSize: 14,
                    color: colors.ink3,
                  },
                  children: `birdcar.dev/writing/${slug}`,
                },
              },
            ],
          },
        },
      ],
    },
  };
}

async function loadFonts() {
  const [serif, serifBold, mono] = await Promise.all([
    readFile(join(FONTS_DIR, 'SourceSerif4-Regular.ttf')),
    readFile(join(FONTS_DIR, 'SourceSerif4-Bold.ttf')),
    readFile(join(FONTS_DIR, 'JetBrainsMono-Regular.ttf')),
  ]);
  return { serif, serifBold, mono };
}

async function renderPng(
  title: string,
  slug: string,
  tag: string | undefined,
  fonts: Awaited<ReturnType<typeof loadFonts>>,
): Promise<Buffer> {
  const svg = await satori(ogTemplate(title, slug, tag) as Parameters<typeof satori>[0], {
    width: 1200,
    height: 630,
    fonts: [
      { name: 'SourceSerif4', data: fonts.serif, weight: 400, style: 'normal' },
      { name: 'SourceSerif4', data: fonts.serifBold, weight: 700, style: 'normal' },
      { name: 'JetBrainsMono', data: fonts.mono, weight: 400, style: 'normal' },
    ],
  });
  const resvg = new Resvg(svg, { fitTo: { mode: 'width', value: 1200 } });
  return Buffer.from(resvg.render().asPng());
}

async function main(): Promise<void> {
  const start = Date.now();
  await mkdir(OUT_DIR, { recursive: true });
  const isProd = process.env.NODE_ENV === 'production' || process.env.ASTRO_MODE === 'production';
  const posts = await readPosts();
  const visible = posts.filter((p) => (isProd ? !p.draft && p.date <= new Date() : true));
  const fonts = await loadFonts();
  let written = 0;
  for (const post of visible) {
    const png = await renderPng(post.title, post.slug, post.tag, fonts);
    await writeFile(join(OUT_DIR, `${post.slug}.png`), png);
    written++;
  }
  const elapsed = ((Date.now() - start) / 1000).toFixed(2);
  console.log(`generate-og: rendered ${written} OG image(s) in ${elapsed}s`);
}

main().catch((err) => {
  console.error('generate-og failed:', err);
  process.exit(1);
});
