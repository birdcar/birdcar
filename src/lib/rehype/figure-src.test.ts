import { describe, test, expect, beforeAll, afterAll } from 'bun:test';
import type { Element, Root } from 'hast';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { rehypeBfmFigureSrc } from './figure-src';

let tmpRoot: string;

beforeAll(() => {
  tmpRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'bfm-figure-src-'));
  const contentDir = path.join(tmpRoot, 'src', 'content');
  fs.mkdirSync(contentDir, { recursive: true });
  fs.writeFileSync(
    path.join(contentDir, 'icon.svg'),
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" width="10" height="10"><rect x="0" y="0" width="10" height="10"/></svg>',
  );
  // raster file under public/figures/
  const figuresDir = path.join(tmpRoot, 'public', 'figures');
  fs.mkdirSync(figuresDir, { recursive: true });
  fs.writeFileSync(path.join(figuresDir, 'photo.png'), Buffer.from([0]));
  // raster also reachable as bare path under src/content
  fs.writeFileSync(path.join(contentDir, 'art.png'), Buffer.from([0]));
});

afterAll(() => {
  fs.rmSync(tmpRoot, { recursive: true, force: true });
});

function rootWith(node: Element): Root {
  return { type: 'root', children: [node] };
}

function figure(props: Record<string, unknown>, children: Element[] = []): Element {
  return {
    type: 'element',
    tagName: 'figure',
    properties: { class: 'bfm-figure', ...props },
    children,
  };
}

describe('rehypeBfmFigureSrc', () => {
  test('inlines an SVG and strips inline width/height', () => {
    const tree = rootWith(figure({ 'data-src': 'icon.svg' }));
    rehypeBfmFigureSrc({ basePath: tmpRoot })(tree, { path: undefined } as any);

    const fig = tree.children[0] as Element;
    const svg = fig.children[0] as Element;
    expect(svg.tagName).toBe('svg');
    expect(svg.properties?.width).toBeUndefined();
    expect(svg.properties?.height).toBeUndefined();
    expect(svg.properties?.role).toBe('img');
  });

  test('emits an <img> element for a raster figure under public/', () => {
    // Use a relative path with a synthetic markdown filePath so the resolver
    // walks up into the project's public/ tree (the production layout).
    const mdFile = path.join(tmpRoot, 'src', 'content', 'blog', 'post.md');
    const tree = rootWith(
      figure({ 'data-src': '../../../public/figures/photo.png', 'data-alt': 'a photo' }),
    );
    rehypeBfmFigureSrc({ basePath: tmpRoot })(tree, { path: mdFile } as any);

    const fig = tree.children[0] as Element;
    const img = fig.children[0] as Element;
    expect(img.tagName).toBe('img');
    expect(img.properties?.src).toBe('/figures/photo.png');
    expect(img.properties?.alt).toBe('a photo');
    expect(img.properties?.loading).toBe('lazy');
  });

  test('falls back to /figures/<basename> for raster outside public/', () => {
    const tree = rootWith(figure({ 'data-src': 'art.png' }));
    rehypeBfmFigureSrc({ basePath: tmpRoot })(tree, { path: undefined } as any);

    const fig = tree.children[0] as Element;
    const img = fig.children[0] as Element;
    expect(img.tagName).toBe('img');
    expect(img.properties?.src).toBe('/figures/art.png');
  });

  test('renders an error element when the source file is missing', () => {
    const tree = rootWith(figure({ 'data-src': 'gone.svg' }));
    rehypeBfmFigureSrc({ basePath: tmpRoot })(tree, { path: undefined } as any);

    const fig = tree.children[0] as Element;
    const err = fig.children[0] as Element;
    expect(err.properties?.class).toBe('bfm-figure-error');
    expect((err.children[0] as { value: string }).value).toBe(
      'Figure source not found: gone.svg',
    );
  });

  test('preserves an existing figcaption alongside the resolved source', () => {
    const tree = rootWith(
      figure({ 'data-src': 'icon.svg' }, [
        {
          type: 'element',
          tagName: 'figcaption',
          properties: {},
          children: [{ type: 'text', value: 'Caption text' }],
        },
      ]),
    );
    rehypeBfmFigureSrc({ basePath: tmpRoot })(tree, { path: undefined } as any);

    const fig = tree.children[0] as Element;
    const cap = fig.children.find(
      (c): c is Element => (c as Element).tagName === 'figcaption',
    );
    expect(cap).toBeDefined();
    expect((cap?.children[0] as { value: string }).value).toBe('Caption text');
  });

  test('skips chart figures (rendered by rehypeBfmChart instead)', () => {
    const tree = rootWith(
      figure({ 'data-src': 'icon.svg', 'data-kind': 'chart' }),
    );
    rehypeBfmFigureSrc({ basePath: tmpRoot })(tree, { path: undefined } as any);

    const fig = tree.children[0] as Element;
    // Children remain empty — figure-src declined to touch a chart figure
    expect(fig.children).toEqual([]);
  });
});
