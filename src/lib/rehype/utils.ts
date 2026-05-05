import type { Element, Node } from 'hast';

/**
 * Recursively collect text content from a hast node. Does NOT trim — call sites
 * that need trimming should `.trim()` the result. (Math expects raw text;
 * embeds want trimmed captions.)
 */
export function extractText(node: Node): string {
  if (node.type === 'text') return (node as unknown as { value: string }).value || '';
  if ('children' in node && Array.isArray((node as Element).children)) {
    return (node as Element).children.map(extractText).join('');
  }
  return '';
}

/**
 * Read a `data-X` attribute from a hast Element. Hast normalizes
 * `data-foo-bar` to `dataFooBar` in `properties`; some pipelines preserve the
 * kebab form. Read both, prefer kebab. Returns empty string when absent —
 * call sites that distinguish empty from missing should add their own guard.
 */
export function getDataAttr(node: Element, name: string): string {
  const props = node.properties ?? {};
  const camel =
    'data' +
    name
      .split('-')
      .map((s) => s[0].toUpperCase() + s.slice(1))
      .join('');
  const kebab = `data-${name}`;
  const value = props[kebab] ?? props[camel];
  return value != null ? String(value) : '';
}

/**
 * Build a hast Element representing a build-time error message. Used by the
 * rehype plugins when a referenced file is missing or malformed. The default
 * class matches what `chart.ts` and `figure-src.ts` emit; override for
 * include-style errors.
 */
export function buildErrorElement(
  message: string,
  className = 'bfm-figure-error',
): Element {
  return {
    type: 'element',
    tagName: 'div',
    properties: { class: className },
    children: [{ type: 'text', value: message }],
  };
}
