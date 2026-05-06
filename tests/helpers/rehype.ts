import { fromHtml } from 'hast-util-from-html';
import { toHtml } from 'hast-util-to-html';
import type { Root } from 'hast';

/**
 * Build a hast Root from an HTML fragment, run a rehype plugin against it, and
 * return both the mutated tree and a stringified HTML form. Plugins may be
 * sync or async; the helper awaits either kind.
 *
 * Mirrors the production pipeline closely enough that data-* attribute
 * normalization, class parsing, and child element shapes match what the
 * real plugins see when invoked through `unified()`.
 */
export async function applyPlugin<O = unknown>(
  plugin: (options?: O) => (tree: Root) => unknown | Promise<unknown>,
  html: string,
  options?: O,
): Promise<{ tree: Root; html: string }> {
  const tree = fromHtml(html, { fragment: true }) as Root;
  const transform = plugin(options);
  await Promise.resolve(transform(tree));
  const out = toHtml(tree, { allowDangerousHtml: true });
  return { tree, html: out };
}
