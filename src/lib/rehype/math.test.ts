import { describe, test, expect } from 'bun:test';
import type { Element, Root } from 'hast';
import { rehypeBfmMath } from './math';

function rootWithMath(children: Element[]): Root {
  return { type: 'root', children };
}

function mathDiv(latex: string, props: Record<string, unknown> = {}): Element {
  return {
    type: 'element',
    tagName: 'div',
    properties: { class: 'math', ...props },
    children: [{ type: 'text', value: latex }],
  };
}

describe('rehypeBfmMath', () => {
  test('replaces a math element with KaTeX-rendered HTML', () => {
    const tree = rootWithMath([mathDiv('a^2 + b^2 = c^2')]);
    rehypeBfmMath()(tree);

    const rendered = tree.children[0] as Element;
    expect(rendered.tagName).toBe('div');
    expect(rendered.properties?.class).toBe('math');
    expect(rendered.properties?.role).toBe('math');
    expect(rendered.properties?.['aria-label']).toBe('a^2 + b^2 = c^2');
    // KaTeX always emits a span with class containing 'katex'
    const text = JSON.stringify(rendered.children);
    expect(text).toContain('katex');
  });

  test('preserves the source id when present', () => {
    const tree = rootWithMath([mathDiv('x', { id: 'eq-1' })]);
    rehypeBfmMath()(tree);
    const rendered = tree.children[0] as Element;
    expect(rendered.properties?.id).toBe('eq-1');
  });

  test('skips when the math element is empty after trim', () => {
    const tree = rootWithMath([mathDiv('   \n  ')]);
    rehypeBfmMath()(tree);
    // No replacement; original text node still present
    const rendered = tree.children[0] as Element;
    const firstChild = rendered.children[0] as { type: string; value?: string };
    expect(firstChild.type).toBe('text');
    expect(firstChild.value?.trim()).toBe('');
  });

  test('ignores elements without the math class', () => {
    const tree = rootWithMath([
      {
        type: 'element',
        tagName: 'div',
        properties: { class: 'note' },
        children: [{ type: 'text', value: 'a^2' }],
      },
    ]);
    rehypeBfmMath()(tree);
    const rendered = tree.children[0] as Element;
    expect(rendered.properties?.class).toBe('note');
    const firstChild = rendered.children[0] as { value: string };
    expect(firstChild.value).toBe('a^2');
  });

  test('renders KaTeX error markup for invalid LaTeX (throwOnError: false)', () => {
    const tree = rootWithMath([mathDiv('\\notarealmacro{')]);
    rehypeBfmMath()(tree);
    const rendered = tree.children[0] as Element;
    // Output is still a math container — error stays in tree as parse-error span
    expect(rendered.properties?.role).toBe('math');
    const text = JSON.stringify(rendered.children);
    // KaTeX uses class "katex-error" or similar for parse failures
    expect(text.toLowerCase()).toContain('katex');
  });
});
