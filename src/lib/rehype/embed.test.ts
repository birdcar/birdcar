import { describe, test, expect } from 'bun:test';
import type { Element, Root } from 'hast';
import { rehypeBfmEmbed } from './embed';

function rootWith(node: Element): Root {
  return { type: 'root', children: [node] };
}

function embedDiv(props: Record<string, unknown>, caption?: string): Element {
  return {
    type: 'element',
    tagName: 'div',
    properties: { class: 'embed', ...props },
    children: caption ? [{ type: 'text', value: caption }] : [],
  };
}

function classOf(node: Element): string {
  // Plugin-built nodes use `class`; hast parser produces `className` (array).
  const props = node.properties ?? {};
  if (typeof props.class === 'string') return props.class;
  if (Array.isArray(props.className)) return props.className.join(' ');
  if (typeof props.className === 'string') return props.className;
  return '';
}

describe('rehypeBfmEmbed', () => {
  test('renders a fallback figure for an unrecognized provider hostname', async () => {
    const tree = rootWith(
      embedDiv({ 'data-url': 'https://example-unknown-host.test/post/1' }),
    );
    await rehypeBfmEmbed()(tree);

    const fig = tree.children[0] as Element;
    expect(fig.tagName).toBe('figure');
    expect(classOf(fig)).toContain('embed--fallback');

    const link = fig.children[0] as Element;
    expect(link.tagName).toBe('a');
    expect(link.properties?.href).toBe('https://example-unknown-host.test/post/1');
  });

  test('uses the caption text as the fallback link label', async () => {
    const tree = rootWith(
      embedDiv({ 'data-url': 'https://example-unknown-host.test/x' }, 'See here'),
    );
    await rehypeBfmEmbed()(tree);

    const fig = tree.children[0] as Element;
    const link = fig.children[0] as Element;
    expect((link.children[0] as { value: string }).value).toBe('See here');
  });

  test('falls back to the URL when no caption is provided', async () => {
    const tree = rootWith(
      embedDiv({ 'data-url': 'https://example-unknown-host.test/no-cap' }),
    );
    await rehypeBfmEmbed()(tree);

    const fig = tree.children[0] as Element;
    const link = fig.children[0] as Element;
    expect((link.children[0] as { value: string }).value).toBe(
      'https://example-unknown-host.test/no-cap',
    );
  });

  test('skips embeds that have no data-url attribute', async () => {
    const tree = rootWith(embedDiv({}));
    await rehypeBfmEmbed()(tree);

    const node = tree.children[0] as Element;
    expect(node.tagName).toBe('div');
    expect(classOf(node)).toBe('embed');
  });

  test('ignores elements without the embed class', async () => {
    const tree = rootWith({
      type: 'element',
      tagName: 'div',
      properties: { class: 'not-embedded' },
      children: [],
    });
    await rehypeBfmEmbed()(tree);

    const node = tree.children[0] as Element;
    expect(node.tagName).toBe('div');
    expect(classOf(node)).toBe('not-embedded');
  });
});
