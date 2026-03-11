import type { Root, Element } from 'hast';
import { visit } from 'unist-util-visit';

export interface QueryResult {
  title: string;
  url: string;
  date: Date;
  tags: string[];
}

export interface QueryOptions {
  resolver: (params: QueryParams) => Promise<QueryResult[]>;
}

export interface QueryParams {
  type?: string;
  state?: string;
  tag?: string;
  limit?: number;
  sort?: string;
}

export function rehypeBfmQuery(options: QueryOptions) {
  return async function (tree: Root) {
    const replacements: Array<{ index: number; parent: any; results: QueryResult[] }> = [];

    visit(tree, 'element', (node: Element, index, parent) => {
      if (!isQueryElement(node) || index == null || !parent) return;

      const params: QueryParams = {
        type: getDataAttr(node, 'query-type'),
        state: getDataAttr(node, 'query-state'),
        tag: getDataAttr(node, 'query-tag'),
        limit: getDataAttr(node, 'query-limit') ? Number(getDataAttr(node, 'query-limit')) : undefined,
        sort: getDataAttr(node, 'query-sort'),
      };

      replacements.push({ index, parent, results: [] as QueryResult[] });
      // Store params on the replacement entry for async resolution
      (replacements[replacements.length - 1] as any).params = params;
    });

    // Resolve all queries
    for (const entry of replacements) {
      try {
        entry.results = await options.resolver((entry as any).params);
      } catch {
        entry.results = [];
      }
    }

    // Apply replacements in reverse order to preserve indices
    for (const { index, parent, results } of replacements.reverse()) {
      const listItems = results.map((r): Element => ({
        type: 'element',
        tagName: 'li',
        properties: { class: 'query__item' },
        children: [
          {
            type: 'element',
            tagName: 'a',
            properties: { href: r.url },
            children: [{ type: 'text', value: r.title }],
          },
          {
            type: 'element',
            tagName: 'time',
            properties: { datetime: r.date.toISOString() },
            children: [{ type: 'text', value: r.date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) }],
          },
        ],
      }));

      const queryNode: Element = {
        type: 'element',
        tagName: 'ul',
        properties: { class: 'query' },
        children: listItems.length > 0
          ? listItems
          : [{ type: 'element', tagName: 'li', properties: { class: 'query__empty' }, children: [{ type: 'text', value: 'No results found.' }] }],
      };

      (parent.children as Element[])[index] = queryNode;
    }
  };
}

function isQueryElement(node: Element): boolean {
  const cls = String(node.properties?.class || '');
  return cls === 'query' || cls.startsWith('query ');
}

function getDataAttr(node: Element, name: string): string | undefined {
  // Try both camelCase (dataQueryType) and kebab (data-query-type)
  const camel = 'data' + name.split('-').map(s => s[0].toUpperCase() + s.slice(1)).join('');
  const val = node.properties?.[camel] ?? node.properties?.[`data-${name}`];
  return val != null ? String(val) : undefined;
}
