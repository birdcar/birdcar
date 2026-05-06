import { describe, test, expect } from 'bun:test';
import { directiveBlock, hashtag, mention } from './bfm-handlers';

const passThroughState = { all: () => [] as unknown[] };

function makeNode(
  name: string,
  params: Record<string, string | boolean> = {},
  meta?: Record<string, unknown>,
) {
  return { type: 'directiveBlock' as const, name, params, children: [], meta };
}

describe('directiveBlock — callout', () => {
  test('renders an aside with the default Note label', () => {
    const out = directiveBlock(passThroughState, makeNode('callout')) as {
      tagName: string;
      properties: Record<string, string>;
      children: Array<{ children: Array<{ value: string }> }>;
    };
    expect(out.tagName).toBe('aside');
    expect(out.properties.class).toContain('bfm-callout--note');
    expect(out.properties['data-callout-type']).toBe('note');
    expect(out.children[0].children[0].value).toBe('Note');
  });

  test('routes a known intent to the matching label', () => {
    const out = directiveBlock(
      passThroughState,
      makeNode('callout', { type: 'warn' }),
    ) as { properties: Record<string, string>; children: Array<{ children: Array<{ value: string }> }> };
    expect(out.properties.class).toContain('bfm-callout--warn');
    expect(out.children[0].children[0].value).toBe('Watch out');
  });

  test('falls back to note for an unknown intent', () => {
    const out = directiveBlock(
      passThroughState,
      makeNode('callout', { type: 'screaming' }),
    ) as { properties: Record<string, string> };
    expect(out.properties['data-callout-type']).toBe('note');
  });

  test('honors a custom title', () => {
    const out = directiveBlock(
      passThroughState,
      makeNode('callout', { type: 'tip', title: 'Pro tip' }),
    ) as { children: Array<{ children: Array<{ value: string }> }> };
    expect(out.children[0].children[0].value).toBe('Pro tip');
  });
});

describe('directiveBlock — figure', () => {
  test('passes data-* attributes through to the figure element', () => {
    const out = directiveBlock(
      passThroughState,
      makeNode('figure', {
        kind: 'chart',
        src: './data.json',
        alt: 'a chart',
        type: 'line',
        width: 'wide',
      }),
    ) as { tagName: string; properties: Record<string, string> };
    expect(out.tagName).toBe('figure');
    expect(out.properties['data-kind']).toBe('chart');
    expect(out.properties['data-src']).toBe('./data.json');
    expect(out.properties['data-alt']).toBe('a chart');
    expect(out.properties['data-type']).toBe('line');
    expect(out.properties['data-width']).toBe('wide');
  });

  test('appends a figcaption when caption param is set', () => {
    const out = directiveBlock(
      passThroughState,
      makeNode('figure', { caption: 'A diagram' }),
    ) as { children: Array<{ tagName: string; children: Array<{ value: string }> }> };
    const captionNode = out.children.find((c) => c.tagName === 'figcaption');
    expect(captionNode).toBeDefined();
    expect(captionNode?.children[0].value).toBe('A diagram');
  });
});

describe('directiveBlock — details / tabs / toc / endnotes', () => {
  test('details uses title param as summary text', () => {
    const out = directiveBlock(
      passThroughState,
      makeNode('details', { title: 'More info' }),
    ) as { tagName: string; children: Array<{ tagName: string; children: Array<{ value: string }> }> };
    expect(out.tagName).toBe('details');
    expect(out.children[0].tagName).toBe('summary');
    expect(out.children[0].children[0].value).toBe('More info');
  });

  test('tabs renders a div container', () => {
    const out = directiveBlock(passThroughState, makeNode('tabs')) as {
      tagName: string;
      properties: Record<string, string>;
    };
    expect(out.tagName).toBe('div');
    expect(out.properties.class).toBe('bfm-tabs');
  });

  test('tab carries data-tab-label', () => {
    const out = directiveBlock(
      passThroughState,
      makeNode('tab', { label: 'Step 1' }),
    ) as { properties: Record<string, string> };
    expect(out.properties['data-tab-label']).toBe('Step 1');
  });

  test('toc renders a nav with aria-label', () => {
    const out = directiveBlock(passThroughState, makeNode('toc')) as {
      tagName: string;
      properties: Record<string, string>;
    };
    expect(out.tagName).toBe('nav');
    expect(out.properties['aria-label']).toBe('Table of contents');
  });

  test('endnotes renders a section with aria-label', () => {
    const out = directiveBlock(passThroughState, makeNode('endnotes')) as {
      tagName: string;
      properties: Record<string, string>;
    };
    expect(out.tagName).toBe('section');
    expect(out.properties['aria-label']).toBe('Endnotes');
  });
});

describe('mention', () => {
  test('renders a known platform with the platform-specific URL', () => {
    const out = mention(null, {
      type: 'mention',
      identifier: 'birdcar',
      platform: 'github',
    }) as { properties: Record<string, string>; children: Array<{ value?: string; children?: Array<{ value: string }> }> };
    expect(out.properties.href).toBe('https://github.com/birdcar');
    expect(out.properties.class).toContain('bfm-mention--github');
  });

  test('routes both `twitter` and `x` to twitter.com', () => {
    const twitter = mention(null, {
      type: 'mention',
      identifier: 'jack',
      platform: 'twitter',
    }) as { properties: Record<string, string> };
    const x = mention(null, {
      type: 'mention',
      identifier: 'jack',
      platform: 'x',
    }) as { properties: Record<string, string> };
    expect(twitter.properties.href).toBe('https://twitter.com/jack');
    expect(x.properties.href).toBe('https://twitter.com/jack');
  });

  test('mastodon with user@instance routes to https://instance/@user', () => {
    const out = mention(null, {
      type: 'mention',
      identifier: 'birdcar@hachyderm.io',
      platform: 'mastodon',
    }) as { properties: Record<string, string> };
    expect(out.properties.href).toBe('https://hachyderm.io/@birdcar');
  });

  test('unknown platform falls back to a span', () => {
    const out = mention(null, {
      type: 'mention',
      identifier: 'someone',
      platform: 'unknown-platform',
    }) as { tagName: string; properties: Record<string, string> };
    expect(out.tagName).toBe('span');
    expect(out.properties.class).toBe('bfm-mention');
  });

  test('plain mention without platform links to /authors/<id>', () => {
    const out = mention(null, {
      type: 'mention',
      identifier: 'nick',
    }) as { tagName: string; properties: Record<string, string> };
    expect(out.tagName).toBe('a');
    expect(out.properties.href).toBe('/authors/nick');
  });
});

describe('hashtag', () => {
  test('lowercases the slug for the URL but preserves display case', () => {
    const out = hashtag(null, {
      type: 'hashtag',
      identifier: 'OpsTooling',
    }) as { tagName: string; properties: Record<string, string>; children: Array<{ value: string }> };
    expect(out.tagName).toBe('a');
    expect(out.properties.href).toBe('/tags/opstooling');
    expect(out.children[0].value).toBe('OpsTooling');
  });
});
