import type { Root, Element } from 'hast';
import { visit } from 'unist-util-visit';
import fs from 'node:fs';
import path from 'node:path';

export interface IncludeOptions {
  basePath?: string;
}

export function rehypeBfmInclude(options: IncludeOptions = {}) {
  const basePath = options.basePath || process.cwd();

  return function (tree: Root) {
    visit(tree, 'element', (node: Element, index, parent) => {
      if (!isIncludeElement(node) || index == null || !parent) return;

      const dataPath = String(node.properties?.['dataPath'] || node.properties?.['data-path'] || '');
      if (!dataPath) return;

      const resolved = path.resolve(basePath, 'src/content', dataPath);

      if (!fs.existsSync(resolved)) {
        const errorNode: Element = {
          type: 'element',
          tagName: 'div',
          properties: { class: 'include include--error' },
          children: [{ type: 'text', value: `Include not found: ${dataPath}` }],
        };
        (parent.children as Element[])[index] = errorNode;
        return;
      }

      const content = fs.readFileSync(resolved, 'utf-8');
      const heading = String(node.properties?.['dataHeading'] || node.properties?.['data-heading'] || '');

      // Extract section if heading is specified
      const body = heading ? extractSection(content, heading) : content;

      const includeNode: Element = {
        type: 'element',
        tagName: 'div',
        properties: {
          class: 'include',
          'data-source': dataPath,
        },
        // Inject raw markdown as pre-formatted text — Astro's pipeline
        // will have already converted to hast at this point, so we render
        // the included content as a blockquote-style embed
        children: [{ type: 'raw', value: `<div class="include__content">${escapeHtml(body)}</div>` } as any],
      };

      (parent.children as Element[])[index] = includeNode;
    });
  };
}

function isIncludeElement(node: Element): boolean {
  const classes = node.properties?.className;
  if (Array.isArray(classes)) return classes.includes('include');
  const cls = String(node.properties?.class || '');
  return cls.includes('include') && !cls.includes('include--error');
}

function extractSection(content: string, heading: string): string {
  const lines = content.split('\n');
  const slug = heading.toLowerCase().replace(/\s+/g, '-').replace(/[^\w-]/g, '');
  let capturing = false;
  let capturedLevel = 0;
  const result: string[] = [];

  for (const line of lines) {
    const match = line.match(/^(#{1,6})\s+(.+)/);
    if (match) {
      const level = match[1].length;
      const text = match[2].trim();
      const lineSlug = text.toLowerCase().replace(/\s+/g, '-').replace(/[^\w-]/g, '');

      if (lineSlug === slug) {
        capturing = true;
        capturedLevel = level;
        result.push(line);
        continue;
      }

      if (capturing && level <= capturedLevel) break;
    }

    if (capturing) result.push(line);
  }

  return result.join('\n');
}

function escapeHtml(str: string): string {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}
