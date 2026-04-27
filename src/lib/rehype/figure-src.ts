/**
 * rehypeBfmFigureSrc — resolves `@figure src=path` to inline content.
 *
 * For `.svg` files, the SVG markup is inlined into the figure (so it inherits
 * design tokens like currentColor and can be styled by the post's CSS).
 * For raster images, an `<img>` element is inserted with the resolved URL.
 *
 * Path resolution:
 *   - Paths starting with `./` or `../` resolve relative to the markdown file.
 *   - Bare paths resolve from `src/content/`.
 */

import type { Root, Element } from 'hast';
import type { VFile } from 'vfile';
import { visit } from 'unist-util-visit';
import { fromHtml } from 'hast-util-from-html';
import fs from 'node:fs';
import path from 'node:path';

export interface FigureSrcOptions {
  /** Project root used to resolve bare paths under `src/content/`. */
  basePath?: string;
}

const RASTER_EXT = new Set(['.png', '.jpg', '.jpeg', '.gif', '.webp', '.avif']);

export function rehypeBfmFigureSrc(options: FigureSrcOptions = {}) {
  const basePath = options.basePath || process.cwd();

  return function (tree: Root, file: VFile) {
    visit(tree, 'element', (node: Element, _index, _parent) => {
      if (node.tagName !== 'figure') return;
      const cls = String(node.properties?.className || node.properties?.class || '');
      if (!cls.includes('bfm-figure')) return;

      // Chart figures are rendered by rehypeBfmChart, not here.
      const kind = String(node.properties?.['dataKind'] || node.properties?.['data-kind'] || '');
      if (kind === 'chart') return;

      const src = String(
        node.properties?.['dataSrc'] ||
        node.properties?.['data-src'] ||
        ''
      );
      if (!src) return;

      const resolved = resolvePath(src, basePath, file.path);
      if (!fs.existsSync(resolved)) {
        const errorNode: Element = {
          type: 'element',
          tagName: 'div',
          properties: { class: 'bfm-figure-error' },
          children: [{ type: 'text', value: `Figure source not found: ${src}` }],
        };
        node.children = [errorNode, ...keepCaption(node)];
        return;
      }

      const ext = path.extname(resolved).toLowerCase();

      if (ext === '.svg') {
        const svg = fs.readFileSync(resolved, 'utf-8');
        const fragment = fromHtml(svg, { fragment: true });
        const svgNode = (fragment.children as Element[]).find(c => c.tagName === 'svg');
        if (svgNode) {
          // Strip any inline width/height so CSS can size it.
          if (svgNode.properties) {
            delete (svgNode.properties as any).width;
            delete (svgNode.properties as any).height;
            svgNode.properties.role = 'img';
          }
          node.children = [svgNode, ...keepCaption(node)];
        }
        return;
      }

      if (RASTER_EXT.has(ext)) {
        // Resolve to a public URL: copy file from src/content into Astro's
        // expected path or reference via the existing /content static handler.
        // Simplest: assume content figures live under public/figures/ and
        // emit a public-relative URL.
        const publicUrl = toPublicUrl(resolved, basePath);
        const alt = String(
          node.properties?.['dataAlt'] ||
          node.properties?.['data-alt'] ||
          ''
        );
        const imgNode: Element = {
          type: 'element',
          tagName: 'img',
          properties: { src: publicUrl, alt, loading: 'lazy', decoding: 'async' },
          children: [],
        };
        node.children = [imgNode, ...keepCaption(node)];
      }
    });
  };
}

function keepCaption(node: Element): Element[] {
  return (node.children as Element[]).filter(
    c => c.type === 'element' && (c as Element).tagName === 'figcaption'
  );
}

function resolvePath(src: string, basePath: string, filePath?: string): string {
  if ((src.startsWith('./') || src.startsWith('../')) && filePath) {
    return path.resolve(path.dirname(filePath), src);
  }
  return path.resolve(basePath, 'src/content', src);
}

function toPublicUrl(absPath: string, basePath: string): string {
  // Allow figures to live under either `public/` (preferred for raster) or
  // `src/content/blog/figures/`. For the latter, rewrite to `/figures/<file>`
  // and trust a build step (or hand-copy) to expose them.
  const publicDir = path.resolve(basePath, 'public');
  if (absPath.startsWith(publicDir)) {
    return '/' + path.relative(publicDir, absPath).split(path.sep).join('/');
  }
  // Fallback: build a stable URL from the basename.
  return '/figures/' + path.basename(absPath);
}
