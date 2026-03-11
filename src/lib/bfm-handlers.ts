interface DirectiveBlockNode {
  type: 'directiveBlock';
  name: string;
  params: Record<string, string | boolean>;
  children: unknown[];
  data?: Record<string, unknown>;
}

export function directiveBlock(state: any, node: DirectiveBlockNode) {
  if (node.name === 'callout') {
    const type = String(node.params.type || 'note');
    const children = state.all(node);
    return {
      type: 'element' as const,
      tagName: 'aside',
      properties: {
        class: `callout callout--${type}`,
        role: 'note',
        'data-callout-type': type,
      },
      children,
    };
  }

  if (node.name === 'embed') {
    const url = String(node.params.url || '');
    const caption = (node as any).meta?.caption || '';
    return {
      type: 'element' as const,
      tagName: 'div',
      properties: {
        class: 'embed',
        'data-url': url,
      },
      children: caption
        ? [{ type: 'text' as const, value: caption }]
        : [],
    };
  }

  // Fall through to default handling for other directives
  const data = node.data || {};
  const tagName = (data.hName as string) || 'div';
  const properties = (data.hProperties as Record<string, unknown>) || {};
  // Use hChildren if the library set them (e.g. math), otherwise process mdast children
  const children = (data.hChildren as unknown[]) || state.all(node);
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
          class: `mention mention--${platform}`,
          title: `${platformLabel(platform)}: ${identifier}`,
          rel: 'noopener noreferrer',
        },
        children: [{ type: 'text' as const, value: `@${platform}:${identifier}` }],
      };
    }

    // Unrecognized platform — render as span, no link
    return {
      type: 'element' as const,
      tagName: 'span',
      properties: { class: 'mention' },
      children: [{ type: 'text' as const, value: `@${platform}:${identifier}` }],
    };
  }

  // Default mentions link to authors collection
  return {
    type: 'element' as const,
    tagName: 'a',
    properties: {
      href: `/authors/${identifier}`,
      class: 'mention',
    },
    children: [{ type: 'text' as const, value: `@${identifier}` }],
  };
}

export function hashtag(_state: unknown, node: HashtagNode) {
  const tag = node.identifier.toLowerCase();
  return {
    type: 'element' as const,
    tagName: 'a',
    properties: {
      href: `/tags/${tag}`,
      class: 'hashtag',
    },
    children: [{ type: 'text' as const, value: `#${node.identifier}` }],
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
      // Mastodon handles are user@instance — split to derive the URL
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
