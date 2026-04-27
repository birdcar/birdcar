import type { APIRoute, GetStaticPaths } from 'astro';
import satori from 'satori';
import { Resvg } from '@resvg/resvg-js';
import { getCollection } from 'astro:content';
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';

const FONTS_DIR = join(process.cwd(), 'src', 'assets', 'fonts');

let fontCache: { serif: Buffer; serifBold: Buffer; mono: Buffer } | null = null;

async function loadFonts() {
  if (fontCache) return fontCache;
  const [serif, serifBold, mono] = await Promise.all([
    readFile(join(FONTS_DIR, 'SourceSerif4-Regular.ttf')),
    readFile(join(FONTS_DIR, 'SourceSerif4-Bold.ttf')),
    readFile(join(FONTS_DIR, 'JetBrainsMono-Regular.ttf')),
  ]);
  fontCache = { serif, serifBold, mono };
  return fontCache;
}

const colors = {
  paper: '#F7F3EC',
  ink: '#1A1814',
  ink2: '#4A453D',
  ink3: '#7A7368',
  rule: '#D9D1BE',
  clay: '#B8442B',
};

function ogTemplate(title: string, slug: string, tag?: string): any {
  const eyebrow = (tag || 'writing').toUpperCase();
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

export const getStaticPaths: GetStaticPaths = async () => {
  const posts = await getCollection('blog', ({ data }) => {
    if (!import.meta.env.PROD) return true;
    if (data.draft) return false;
    return data.date <= new Date();
  });
  return posts.map((post) => ({
    params: { slug: post.id },
    props: {
      title: post.data.title,
      slug: post.id,
      tag: post.data.tags?.[0],
    },
  }));
};

export const GET: APIRoute = async ({ props }) => {
  const { title, slug, tag } = props as { title: string; slug: string; tag?: string };
  const fonts = await loadFonts();

  const svg = await satori(ogTemplate(title, slug, tag), {
    width: 1200,
    height: 630,
    fonts: [
      { name: 'SourceSerif4', data: fonts.serif, weight: 400, style: 'normal' },
      { name: 'SourceSerif4', data: fonts.serifBold, weight: 700, style: 'normal' },
      { name: 'JetBrainsMono', data: fonts.mono, weight: 400, style: 'normal' },
    ],
  });

  const resvg = new Resvg(svg, { fitTo: { mode: 'width', value: 1200 } });
  const png = resvg.render().asPng();

  return new Response(png as unknown as BodyInit, {
    headers: { 'Content-Type': 'image/png' },
  });
};
