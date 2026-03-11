import { describe, test, expect } from 'bun:test';
import { rehypeImageCdn } from './rehype-image-cdn';
import type { Root, Element } from 'hast';

function makeTree(imgSrc: string): Root {
  return {
    type: 'root',
    children: [
      {
        type: 'element',
        tagName: 'img',
        properties: { src: imgSrc },
        children: [],
      } satisfies Element,
    ],
  };
}

const CDN = 'https://images.example.com';

describe('rehypeImageCdn', () => {
  test('rewrites ./images/ paths to CDN URL', () => {
    const tree = makeTree('./images/post/hero.png');
    rehypeImageCdn({ cdnBaseUrl: CDN })(tree);
    const img = tree.children[0] as Element;
    expect(img.properties?.src).toBe(`${CDN}/images/post/hero.png`);
  });

  test('rewrites images/ paths without ./ prefix', () => {
    const tree = makeTree('images/post/hero.png');
    rehypeImageCdn({ cdnBaseUrl: CDN })(tree);
    const img = tree.children[0] as Element;
    expect(img.properties?.src).toBe(`${CDN}/images/post/hero.png`);
  });

  test('leaves external URLs unchanged', () => {
    const tree = makeTree('https://example.com/photo.png');
    rehypeImageCdn({ cdnBaseUrl: CDN })(tree);
    const img = tree.children[0] as Element;
    expect(img.properties?.src).toBe('https://example.com/photo.png');
  });

  test('leaves absolute paths unchanged', () => {
    const tree = makeTree('/assets/logo.png');
    rehypeImageCdn({ cdnBaseUrl: CDN })(tree);
    const img = tree.children[0] as Element;
    expect(img.properties?.src).toBe('/assets/logo.png');
  });

  test('rewrites img nested inside figure element', () => {
    const tree: Root = {
      type: 'root',
      children: [
        {
          type: 'element',
          tagName: 'figure',
          properties: {},
          children: [
            {
              type: 'element',
              tagName: 'img',
              properties: { src: './images/post/figure.png' },
              children: [],
            } satisfies Element,
            {
              type: 'element',
              tagName: 'figcaption',
              properties: {},
              children: [{ type: 'text', value: 'A caption' }],
            } as Element,
          ],
        } as Element,
      ],
    };
    rehypeImageCdn({ cdnBaseUrl: CDN })(tree);
    const figure = tree.children[0] as Element;
    const img = figure.children[0] as Element;
    expect(img.properties?.src).toBe(`${CDN}/images/post/figure.png`);
  });

  test('handles tree with no images', () => {
    const tree: Root = {
      type: 'root',
      children: [
        { type: 'element', tagName: 'p', properties: {}, children: [{ type: 'text', value: 'Hello' }] } as Element,
      ],
    };
    rehypeImageCdn({ cdnBaseUrl: CDN })(tree);
    const p = tree.children[0] as Element;
    expect(p.tagName).toBe('p');
  });
});
