import type { Root, Element } from 'hast';
import { visit } from 'unist-util-visit';
import { fromHtml } from 'hast-util-from-html';

interface OEmbedResponse {
  type: string;
  html?: string;
  title?: string;
  thumbnail_url?: string;
  provider_name?: string;
}

const OEMBED_PROVIDERS: Record<string, string> = {
  'youtube.com': 'https://www.youtube.com/oembed',
  'youtu.be': 'https://www.youtube.com/oembed',
  'vimeo.com': 'https://vimeo.com/api/oembed.json',
  'twitter.com': 'https://publish.twitter.com/oembed',
  'x.com': 'https://publish.twitter.com/oembed',
  'codepen.io': 'https://codepen.io/api/oembed',
  'open.spotify.com': 'https://open.spotify.com/oembed',
};

export function rehypeBfmEmbed() {
  return async function (tree: Root) {
    const embeds: Array<{ node: Element; index: number; parent: any }> = [];

    visit(tree, 'element', (node: Element, index, parent) => {
      if (!isEmbedElement(node) || index == null || !parent) return;
      embeds.push({ node, index, parent });
    });

    for (const { node, index, parent } of embeds.reverse()) {
      const url = String(node.properties?.['dataUrl'] || node.properties?.['data-url'] || '');
      const caption = extractText(node);

      if (!url) continue;

      let rendered: Element;
      try {
        const oembed = await fetchOEmbed(url);
        if (oembed?.html) {
          const fragment = fromHtml(oembed.html, { fragment: true });
          rendered = {
            type: 'element',
            tagName: 'figure',
            properties: { class: 'embed', 'data-provider': oembed.provider_name || '' },
            children: [
              ...(fragment.children as Element[]),
              ...(caption ? [{
                type: 'element' as const,
                tagName: 'figcaption' as const,
                properties: {},
                children: [{ type: 'text' as const, value: caption }],
              }] : []),
            ],
          };
        } else {
          rendered = buildFallbackEmbed(url, oembed?.title || caption);
        }
      } catch {
        rendered = buildFallbackEmbed(url, caption);
      }

      (parent.children as Element[])[index] = rendered;
    }
  };
}

async function fetchOEmbed(url: string): Promise<OEmbedResponse | null> {
  const hostname = new URL(url).hostname.replace(/^www\./, '');
  const endpoint = OEMBED_PROVIDERS[hostname];
  if (!endpoint) return null;

  const oembedUrl = `${endpoint}?url=${encodeURIComponent(url)}&format=json&maxwidth=800`;
  const response = await fetch(oembedUrl, { signal: AbortSignal.timeout(5000) });
  if (!response.ok) return null;
  return response.json() as Promise<OEmbedResponse>;
}

function buildFallbackEmbed(url: string, caption?: string): Element {
  return {
    type: 'element',
    tagName: 'figure',
    properties: { class: 'embed embed--fallback' },
    children: [
      {
        type: 'element',
        tagName: 'a',
        properties: { href: url, class: 'embed__link', rel: 'noopener noreferrer' },
        children: [{ type: 'text', value: caption || url }],
      },
    ],
  };
}

function isEmbedElement(node: Element): boolean {
  const cls = String(node.properties?.class || '');
  return cls === 'embed' || cls.startsWith('embed ');
}

function extractText(node: any): string {
  if (node.type === 'text') return node.value || '';
  if (node.children) return node.children.map(extractText).join('').trim();
  return '';
}
