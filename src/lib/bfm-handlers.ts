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
    return {
      type: 'element' as const,
      tagName: 'a',
      properties: {
        href: platformUrl(platform, identifier),
        class: `mention mention--${platform}`,
        rel: 'noopener noreferrer',
      },
      children: [{ type: 'text' as const, value: `@${platform}:${identifier}` }],
    };
  }

  return {
    type: 'element' as const,
    tagName: 'a',
    properties: {
      href: `https://github.com/${identifier}`,
      class: 'mention',
      rel: 'noopener noreferrer',
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

function platformUrl(platform: string, identifier: string): string {
  switch (platform) {
    case 'github':
      return `https://github.com/${identifier}`;
    case 'twitter':
    case 'x':
      return `https://x.com/${identifier}`;
    case 'mastodon':
      return `https://mastodon.social/@${identifier}`;
    default:
      return `https://github.com/${identifier}`;
  }
}
