import type { Root, Element } from 'hast';
import { visit } from 'unist-util-visit';
import { fromHtml } from 'hast-util-from-html';
import katex from 'katex';

export function rehypeBfmMath() {
  return function (tree: Root) {
    visit(tree, 'element', (node: Element, index, parent) => {
      if (!isMathElement(node) || index == null || !parent) return;

      const latex = extractText(node);
      if (!latex.trim()) return;

      try {
        const html = katex.renderToString(latex, {
          displayMode: true,
          throwOnError: false,
          output: 'htmlAndMathml',
        });

        const fragment = fromHtml(html, { fragment: true });

        const rendered: Element = {
          type: 'element',
          tagName: 'div',
          properties: {
            class: 'math',
            role: 'math',
            'aria-label': latex,
            ...(node.properties?.id ? { id: node.properties.id } : {}),
          },
          children: fragment.children as Element[],
        };

        (parent.children as Element[])[index] = rendered;
      } catch {
        // Leave the raw LaTeX in place on error
      }
    });
  };
}

function isMathElement(node: Element): boolean {
  const classes = node.properties?.className;
  if (Array.isArray(classes)) return classes.includes('math');
  if (typeof classes === 'string') return classes.includes('math');
  const cls = node.properties?.class;
  if (typeof cls === 'string') return cls.includes('math');
  return false;
}

function extractText(node: any): string {
  if (node.type === 'text') return node.value || '';
  if (node.children) return node.children.map(extractText).join('');
  return '';
}
