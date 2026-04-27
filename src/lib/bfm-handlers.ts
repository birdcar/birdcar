interface DirectiveBlockNode {
  type: 'directiveBlock';
  name: string;
  params: Record<string, string | boolean>;
  children: unknown[];
  data?: Record<string, unknown>;
  meta?: Record<string, unknown>;
}

const VALID_CALLOUT_INTENTS = ['note', 'key', 'warn', 'tip', 'tldr'] as const;
const CALLOUT_LABELS: Record<string, string> = {
  note: 'Note',
  key: 'Key takeaway',
  warn: 'Watch out',
  tip: 'Tip',
  tldr: 'TL;DR',
};

function paramString(node: DirectiveBlockNode, key: string): string | undefined {
  const v = node.params?.[key];
  if (typeof v === 'string') return v;
  return undefined;
}

export function directiveBlock(state: any, node: DirectiveBlockNode) {
  const childNodes = state.all(node);

  if (node.name === 'callout') {
    const rawType = String(node.params.type || node.params.intent || 'note').toLowerCase();
    const type = (VALID_CALLOUT_INTENTS as readonly string[]).includes(rawType) ? rawType : 'note';
    const customTitle = paramString(node, 'title');
    const eyebrow = customTitle || CALLOUT_LABELS[type];
    return {
      type: 'element' as const,
      tagName: 'aside',
      properties: {
        class: `bfm-callout bfm-callout--${type}`,
        role: 'note',
        'data-callout-type': type,
      },
      children: [
        {
          type: 'element' as const,
          tagName: 'span',
          properties: { class: 'bfm-callout-title' },
          children: [{ type: 'text' as const, value: eyebrow }],
        },
        ...childNodes,
      ],
    };
  }

  if (node.name === 'aside') {
    // The BFM library auto-injects a `<p class="aside__title">` when `title=` is
    // set; we don't add one ourselves to avoid duplication. CSS styles
    // `.aside__title` like the editorial eyebrow.
    return {
      type: 'element' as const,
      tagName: 'aside',
      properties: { class: 'bfm-aside' },
      children: childNodes,
    };
  }

  if (node.name === 'details') {
    const title = paramString(node, 'title') || paramString(node, 'summary') || 'Details';
    return {
      type: 'element' as const,
      tagName: 'details',
      properties: { class: 'bfm-details' },
      children: [
        {
          type: 'element' as const,
          tagName: 'summary',
          properties: {},
          children: [{ type: 'text' as const, value: title }],
        },
        ...childNodes,
      ],
    };
  }

  if (node.name === 'figure') {
    const caption = paramString(node, 'caption');
    const width = paramString(node, 'width') || 'prose';
    const kind = paramString(node, 'kind');
    const props: Record<string, unknown> = {
      class: 'bfm-figure',
      'data-width': width,
    };
    if (kind) props['data-kind'] = kind;
    const children: unknown[] = [...childNodes];
    if (caption) {
      children.push({
        type: 'element' as const,
        tagName: 'figcaption',
        properties: {},
        children: [{ type: 'text' as const, value: caption }],
      });
    }
    return {
      type: 'element' as const,
      tagName: 'figure',
      properties: props,
      children,
    };
  }

  // Note: there is no `@chart` directive — the BFM library only parses a fixed
  // set of directive names. Charts are authored as `@figure kind=chart` and
  // rendered by `rehypeBfmChart` via the `data-kind="chart"` attribute.

  if (node.name === 'embed') {
    const url = paramString(node, 'url') || '';
    const caption = (node.meta as any)?.caption || '';
    return {
      type: 'element' as const,
      tagName: 'div',
      properties: {
        class: 'bfm-embed',
        'data-url': url,
      },
      children: caption ? [{ type: 'text' as const, value: caption }] : [],
    };
  }

  if (node.name === 'tabs') {
    return {
      type: 'element' as const,
      tagName: 'div',
      properties: { class: 'bfm-tabs' },
      children: childNodes,
    };
  }

  if (node.name === 'tab') {
    const label = paramString(node, 'label') || paramString(node, 'title') || '';
    return {
      type: 'element' as const,
      tagName: 'div',
      properties: { class: 'bfm-tabs-panel', 'data-tab-label': label },
      children: childNodes,
    };
  }

  if (node.name === 'toc') {
    return {
      type: 'element' as const,
      tagName: 'nav',
      properties: { class: 'bfm-toc', 'aria-label': 'Table of contents' },
      children: [
        {
          type: 'element' as const,
          tagName: 'div',
          properties: { class: 'bfm-toc-heading' },
          children: [{ type: 'text' as const, value: 'In this post' }],
        },
        ...childNodes,
      ],
    };
  }

  if (node.name === 'endnotes') {
    return {
      type: 'element' as const,
      tagName: 'section',
      properties: { class: 'bfm-endnotes', 'aria-label': 'Endnotes' },
      children: childNodes,
    };
  }

  // Fall through to default handling for other directives
  const data = node.data || {};
  const tagName = (data.hName as string) || 'div';
  const properties = (data.hProperties as Record<string, unknown>) || {};
  const children = (data.hChildren as unknown[]) || childNodes;
  return {
    type: 'element' as const,
    tagName,
    properties,
    children,
  };
}

interface MentionNode {
  type: 'mention';
  identifier: string;
  platform?: string;
  data?: Record<string, unknown>;
}

interface HashtagNode {
  type: 'hashtag';
  identifier: string;
  data?: Record<string, unknown>;
}

export function mention(_state: unknown, node: MentionNode) {
  const identifier = node.identifier;
  const platform = node.platform;

  if (platform) {
    const url = platformUrl(platform, identifier);
    if (url) {
      return {
        type: 'element' as const,
        tagName: 'a',
        properties: {
          href: url,
          class: `bfm-mention bfm-mention--${platform}`,
          title: `${platformLabel(platform)}: ${identifier}`,
          rel: 'noopener noreferrer',
        },
        children: [
          { type: 'element' as const, tagName: 'span', properties: { class: 'at' }, children: [{ type: 'text' as const, value: '@' }] },
          { type: 'text' as const, value: `${platform}:${identifier}` },
        ],
      };
    }
    return {
      type: 'element' as const,
      tagName: 'span',
      properties: { class: 'bfm-mention' },
      children: [{ type: 'text' as const, value: `@${platform}:${identifier}` }],
    };
  }

  return {
    type: 'element' as const,
    tagName: 'a',
    properties: {
      href: `/authors/${identifier}`,
      class: 'bfm-mention',
    },
    children: [
      { type: 'element' as const, tagName: 'span', properties: { class: 'at' }, children: [{ type: 'text' as const, value: '@' }] },
      { type: 'text' as const, value: identifier },
    ],
  };
}

export function hashtag(_state: unknown, node: HashtagNode) {
  const tag = node.identifier.toLowerCase();
  return {
    type: 'element' as const,
    tagName: 'a',
    properties: {
      href: `/tags/${tag}`,
      class: 'bfm-hashtag',
    },
    children: [{ type: 'text' as const, value: node.identifier }],
  };
}

function platformUrl(platform: string, identifier: string): string | null {
  switch (platform) {
    case 'github':
      return `https://github.com/${identifier}`;
    case 'twitter':
    case 'x':
      return `https://twitter.com/${identifier}`;
    case 'bluesky':
      return `https://bsky.app/profile/${identifier}`;
    case 'mastodon':
      if (identifier.includes('@')) {
        const [user, instance] = identifier.split('@', 2);
        return `https://${instance}/@${user}`;
      }
      return `https://mastodon.social/@${identifier}`;
    case 'npm':
      return `https://www.npmjs.com/package/${identifier}`;
    case 'linkedin':
      return `https://www.linkedin.com/in/${identifier}`;
    default:
      return null;
  }
}

function platformLabel(platform: string): string {
  switch (platform) {
    case 'github': return 'GitHub';
    case 'twitter': case 'x': return 'Twitter/X';
    case 'bluesky': return 'Bluesky';
    case 'mastodon': return 'Mastodon';
    case 'npm': return 'npm';
    case 'linkedin': return 'LinkedIn';
    default: return platform;
  }
}
