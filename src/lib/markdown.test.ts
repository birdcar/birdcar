import { describe, test, expect } from 'bun:test';
import { getPostMetadata, renderPostToHtml } from './markdown';

describe('renderPostToHtml', () => {
  test('renders a simple paragraph as <p> HTML', async () => {
    const html = await renderPostToHtml('Just a sentence.');
    expect(html).toContain('<p>Just a sentence.</p>');
  });

  test('renders a heading as <h1>', async () => {
    const html = await renderPostToHtml('# Title\n\nbody');
    expect(html).toContain('<h1>Title</h1>');
    expect(html).toContain('<p>body</p>');
  });

  test('routes a hashtag through the BFM hashtag handler', async () => {
    const html = await renderPostToHtml('Tagged #ops here.');
    expect(html).toContain('class="bfm-hashtag"');
    expect(html).toContain('href="/tags/ops"');
  });

  test('routes a plain mention through the BFM mention handler', async () => {
    const html = await renderPostToHtml('See @nick for details.');
    expect(html).toContain('class="bfm-mention"');
    expect(html).toContain('href="/authors/nick"');
  });

  test('routes a callout directive through the directiveBlock handler', async () => {
    const html = await renderPostToHtml(
      ['@callout type=warn', 'be careful', '@endcallout'].join('\n'),
    );
    expect(html).toContain('<aside');
    expect(html).toContain('bfm-callout--warn');
    expect(html).toContain('Watch out');
  });
});

describe('getPostMetadata', () => {
  test('returns a metadata object for an empty document', async () => {
    const meta = await getPostMetadata('');
    expect(meta).toBeDefined();
    expect(typeof meta).toBe('object');
  });

  test('returns a metadata object for a document with a heading', async () => {
    const meta = await getPostMetadata('# Hello\n\nworld');
    expect(meta).toBeDefined();
    expect(typeof meta).toBe('object');
  });
});
