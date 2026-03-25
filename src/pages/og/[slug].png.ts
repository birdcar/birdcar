import type { APIRoute, GetStaticPaths } from 'astro';
import satori from 'satori';
import { Resvg } from '@resvg/resvg-js';
import { getCollection } from 'astro:content';
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { homedir } from 'node:os';

const FONT_CACHE_DIR = join(homedir(), '.cache', 'birdcar-fonts');
const FONT_CDN = 'https://fonts.googleapis.com/css2';

let fontCache: { archivo: Buffer; spaceGrotesk: Buffer } | null = null;

async function downloadFont(family: string, weight: number, filename: string): Promise<Buffer> {
  const cssUrl = `${FONT_CDN}?family=${encodeURIComponent(family)}:wght@${weight}&display=swap`;
  const cssRes = await fetch(cssUrl, {
    headers: { 'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36' },
  });
  const css = await cssRes.text();
  const match = css.match(/src:\s*url\(([^)]+)\)/);
  if (!match) throw new Error(`Could not extract font URL for ${family} ${weight}`);
  const fontRes = await fetch(match[1]);
  const buf = Buffer.from(await fontRes.arrayBuffer());
  const { mkdir, writeFile } = await import('node:fs/promises');
  await mkdir(FONT_CACHE_DIR, { recursive: true });
  await writeFile(join(FONT_CACHE_DIR, filename), buf);
  return buf;
}

async function loadFonts() {
  if (fontCache) return fontCache;
  let archivo: Buffer;
  let spaceGrotesk: Buffer;
  try {
    archivo = await readFile(join(FONT_CACHE_DIR, 'Archivo-Bold.ttf'));
    spaceGrotesk = await readFile(join(FONT_CACHE_DIR, 'SpaceGrotesk-Regular.ttf'));
  } catch {
    archivo = await downloadFont('Archivo', 700, 'Archivo-Bold.ttf');
    spaceGrotesk = await downloadFont('Space Grotesk', 400, 'SpaceGrotesk-Regular.ttf');
  }
  fontCache = { archivo, spaceGrotesk };
  return fontCache;
}

// Catppuccin Mocha palette
const colors = {
  base: '#1e1e2e',
  mantle: '#181825',
  surface0: '#313244',
  surface1: '#45475a',
  surface2: '#585b70',
  text: '#cdd6f4',
  subtext1: '#bac2de',
  overlay1: '#7f849c',
  sapphire: '#74c7ec',
  blue: '#89b4fa',
  lavender: '#b4befe',
  mauve: '#cba6f7',
  pink: '#f5c2e7',
  flamingo: '#f2cdcd',
  teal: '#94e2d5',
  green: '#a6e3a1',
  peach: '#fab387',
  yellow: '#f9e2af',
};

function dot(x: number, y: number, r: number, color: string, opacity: number): any {
  return {
    type: 'div',
    props: {
      style: {
        position: 'absolute',
        left: x - r,
        top: y - r,
        width: r * 2,
        height: r * 2,
        borderRadius: '50%',
        backgroundColor: color,
        opacity,
      },
      children: '',
    },
  };
}

function line(x: number, y: number, width: number, height: number, opacity: number): any {
  return {
    type: 'div',
    props: {
      style: {
        position: 'absolute',
        left: x,
        top: y,
        width,
        height,
        backgroundColor: colors.surface2,
        opacity,
      },
      children: '',
    },
  };
}

function cross(x: number, y: number, size: number, opacity: number): any[] {
  return [
    {
      type: 'div',
      props: {
        style: {
          position: 'absolute',
          left: x - size / 2,
          top: y,
          width: size,
          height: 1,
          backgroundColor: colors.overlay1,
          opacity,
          transform: 'rotate(45deg)',
        },
        children: '',
      },
    },
    {
      type: 'div',
      props: {
        style: {
          position: 'absolute',
          left: x - size / 2,
          top: y,
          width: size,
          height: 1,
          backgroundColor: colors.overlay1,
          opacity,
          transform: 'rotate(-45deg)',
        },
        children: '',
      },
    },
  ];
}

function dotGrid(): any[] {
  const dots: any[] = [];
  const spacing = 32;
  for (let x = 0; x < 1200; x += spacing) {
    for (let y = 0; y < 630; y += spacing) {
      dots.push({
        type: 'div',
        props: {
          style: {
            position: 'absolute',
            left: x,
            top: y,
            width: 2,
            height: 2,
            borderRadius: '50%',
            backgroundColor: colors.surface1,
            opacity: 0.3,
          },
          children: '',
        },
      });
    }
  }
  return dots;
}

function ogTemplate(title: string, description?: string) {
  const decorations: any[] = [
    ...dotGrid(),

    // Scattered colored dots
    dot(75, 42, 5, colors.mauve, 0.3),
    dot(1050, 63, 6, colors.teal, 0.25),
    dot(300, 21, 4, colors.lavender, 0.3),
    dot(720, 37, 5, colors.pink, 0.25),
    dot(930, 16, 4, colors.peach, 0.2),
    dot(150, 147, 6, colors.blue, 0.2),
    dot(525, 126, 6, colors.green, 0.2),
    dot(825, 168, 5, colors.mauve, 0.2),
    dot(1125, 137, 4, colors.teal, 0.15),
    dot(120, 294, 8, colors.blue, 0.15),
    dot(960, 315, 5, colors.pink, 0.15),
    dot(450, 357, 6, colors.peach, 0.12),
    dot(1110, 368, 5, colors.lavender, 0.1),
    dot(225, 441, 4, colors.green, 0.1),
    dot(750, 473, 5, colors.mauve, 0.08),

    // Geometric lines
    line(45, 105, 75, 1, 0.3),
    line(1020, 126, 120, 1, 0.2),
    line(90, 231, 1, 50, 0.2),
    line(600, 84, 75, 1, 0.2),
    line(375, 210, 60, 1, 0.15),
    line(900, 263, 1, 50, 0.12),

    // Cross marks
    ...cross(1090, 35, 14, 0.2),
    ...cross(50, 178, 14, 0.15),
    ...cross(680, 275, 14, 0.12),
    ...cross(300, 400, 14, 0.1),
  ];

  const textContent: any[] = [
    {
      type: 'div',
      props: {
        style: {
          display: 'flex',
          fontFamily: 'Space Grotesk',
          fontWeight: 400,
          fontSize: 16,
          color: colors.sapphire,
          letterSpacing: '0.1em',
          textTransform: 'uppercase' as const,
        },
        children: 'birdcar.dev',
      },
    },
    {
      type: 'div',
      props: {
        style: {
          display: 'flex',
          fontFamily: 'Archivo',
          fontWeight: 700,
          fontSize: 64,
          color: colors.text,
          lineHeight: 1.05,
          letterSpacing: '-0.03em',
          marginTop: 16,
        },
        children: title,
      },
    },
  ];

  if (description) {
    textContent.push({
      type: 'div',
      props: {
        style: {
          display: 'flex',
          fontFamily: 'Space Grotesk',
          fontWeight: 400,
          fontSize: 26,
          color: colors.subtext1,
          marginTop: 24,
          lineHeight: 1.5,
        },
        children: description,
      },
    });
  }

  return {
    type: 'div',
    props: {
      style: {
        display: 'flex',
        flexDirection: 'column',
        position: 'relative',
        width: 1200,
        height: 630,
        backgroundColor: colors.base,
        overflow: 'hidden',
      },
      children: [
        {
          type: 'div',
          props: {
            style: {
              display: 'flex',
              width: '100%',
              height: 6,
              background: `linear-gradient(135deg, ${colors.sapphire}, ${colors.mauve}, ${colors.pink})`,
            },
            children: '',
          },
        },
        ...decorations,
        {
          type: 'div',
          props: {
            style: {
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'center',
              flex: 1,
              padding: '60px 80px 60px 80px',
              position: 'relative',
              zIndex: 1,
            },
            children: textContent,
          },
        },
      ],
    },
  };
}

export const getStaticPaths: GetStaticPaths = async () => {
  const posts = await getCollection('blog', ({ data }) => {
    return import.meta.env.PROD ? !data.draft : true;
  });
  return posts.map((post) => ({
    params: { slug: post.id },
    props: { title: post.data.title, description: post.data.description },
  }));
};

export const GET: APIRoute = async ({ props }) => {
  const { title, description } = props as { title: string; description?: string };
  const fonts = await loadFonts();

  const svg = await satori(ogTemplate(title, description), {
    width: 1200,
    height: 630,
    fonts: [
      { name: 'Archivo', data: fonts.archivo, weight: 700, style: 'normal' },
      { name: 'Space Grotesk', data: fonts.spaceGrotesk, weight: 400, style: 'normal' },
    ],
  });

  const resvg = new Resvg(svg, { fitTo: { mode: 'width', value: 1200 } });
  const png = resvg.render().asPng();

  return new Response(png, {
    headers: { 'Content-Type': 'image/png' },
  });
};
