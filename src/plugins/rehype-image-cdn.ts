import type { Root, Element } from 'hast';
import { visit } from 'unist-util-visit';

export interface ImageCdnOptions {
  cdnBaseUrl: string;
}

export function rehypeImageCdn(options: ImageCdnOptions) {
  const { cdnBaseUrl } = options;

  return (tree: Root) => {
    visit(tree, 'element', (node: Element) => {
      if (node.tagName === 'img' && typeof node.properties?.src === 'string') {
        const src = node.properties.src;
        if (src.startsWith('./images/') || src.startsWith('images/')) {
          const cleanPath = src.replace(/^\.\//, '');
          node.properties.src = `${cdnBaseUrl}/${cleanPath}`;
        }
      }
    });
  };
}
