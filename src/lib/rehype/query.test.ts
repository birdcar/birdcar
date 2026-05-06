import { describe, test, expect } from 'bun:test';
import type { Element, Root } from 'hast';
import { rehypeBfmQuery, type QueryParams, type QueryResult } from './query';

function rootWith(children: Element[]): Root {
  return { type: 'root', children };
}

function queryDiv(props: Record<string, unknown> = {}): Element {
  return {
    type: 'element',
    tagName: 'div',
    properties: { class: 'query', ...props },
    children: [],
  };
}

function makeResult(overrides: Partial<QueryResult> = {}): QueryResult {
  return {
    title: 'A post',
    url: '/writing/a-post/',
    date: new Date('2026-04-01T00:00:00Z'),
    tags: ['ops'],
    description: 'Description.',
    ...overrides,
  };
}

describe('rehypeBfmQuery', () => {
  test('renders resolver results as a post-list ul', async () => {
    const tree = rootWith([queryDiv()]);
    const resolver = async () => [makeResult()];
    await rehypeBfmQuery({ resolver })(tree);

    const ul = tree.children[0] as Element;
    expect(ul.tagName).toBe('ul');
    expect(ul.properties?.class).toBe('post-list');
    expect(ul.children).toHaveLength(1);

    const li = ul.children[0] as Element;
    const titleDiv = li.children[0] as Element;
    const link = titleDiv.children[0] as Element;
    expect(link.properties?.href).toBe('/writing/a-post/');
    expect((link.children[0] as { value: string }).value).toBe('A post');
  });

  test('passes data-* attributes to the resolver as QueryParams', async () => {
    const tree = rootWith([
      queryDiv({
        'data-query-type': 'post',
        'data-query-tag': 'ops',
        'data-query-limit': '5',
        'data-query-sort': 'date-desc',
      }),
    ]);
    let received: QueryParams | undefined;
    const resolver = async (params: QueryParams) => {
      received = params;
      return [];
    };
    await rehypeBfmQuery({ resolver })(tree);
    expect(received).toBeDefined();
    expect(received?.type).toBe('post');
    expect(received?.tag).toBe('ops');
    expect(received?.limit).toBe(5);
    expect(received?.sort).toBe('date-desc');
  });

  test('renders an empty-state list item when the resolver returns nothing', async () => {
    const tree = rootWith([queryDiv()]);
    const resolver = async () => [];
    await rehypeBfmQuery({ resolver })(tree);

    const ul = tree.children[0] as Element;
    expect(ul.children).toHaveLength(1);
    const li = ul.children[0] as Element;
    expect(li.properties?.class).toBe('query__empty');
    expect((li.children[0] as { value: string }).value).toBe('No results found.');
  });

  test('falls back to empty results when the resolver throws', async () => {
    const tree = rootWith([queryDiv()]);
    const resolver = async () => {
      throw new Error('resolver fail');
    };
    await rehypeBfmQuery({ resolver })(tree);

    const ul = tree.children[0] as Element;
    const li = ul.children[0] as Element;
    expect(li.properties?.class).toBe('query__empty');
  });

  test('renders tag list links scoped to /tags/<slug>/', async () => {
    const tree = rootWith([queryDiv()]);
    const resolver = async () => [makeResult({ tags: ['support', 'ops'] })];
    await rehypeBfmQuery({ resolver })(tree);

    const ul = tree.children[0] as Element;
    const li = ul.children[0] as Element;
    const tagsBlock = li.children.find(
      (c): c is Element => (c as Element).properties?.class === 'post-list__tags',
    );
    expect(tagsBlock).toBeDefined();
    const tagList = tagsBlock!.children[0] as Element;
    const items = tagList.children as Element[];
    expect(items).toHaveLength(2);
    const firstLink = items[0].children[0] as Element;
    expect(firstLink.properties?.href).toBe('/tags/support/');
  });

  test('omits the tag list when results have no tags', async () => {
    const tree = rootWith([queryDiv()]);
    const resolver = async () => [makeResult({ tags: [] })];
    await rehypeBfmQuery({ resolver })(tree);

    const ul = tree.children[0] as Element;
    const li = ul.children[0] as Element;
    const tagsBlock = li.children.find(
      (c): c is Element => (c as Element).properties?.class === 'post-list__tags',
    );
    expect(tagsBlock).toBeUndefined();
  });
});
