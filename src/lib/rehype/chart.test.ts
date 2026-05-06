import { describe, test, expect, beforeAll, afterAll } from 'bun:test';
import type { Element, Root } from 'hast';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { rehypeBfmChart } from './chart';

let tmpRoot: string;

beforeAll(() => {
  tmpRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'bfm-chart-'));
  const dir = path.join(tmpRoot, 'src', 'content');
  fs.mkdirSync(dir, { recursive: true });

  fs.writeFileSync(
    path.join(dir, 'two-series.json'),
    JSON.stringify({
      x: 'year',
      series: [
        { key: 'after', label: 'after rewrite' },
        { key: 'before', label: 'before' },
      ],
      data: [
        { year: 2024, after: 100, before: 80 },
        { year: 2025, after: 180, before: 90 },
        { year: 2026, after: 200, before: 95 },
      ],
    }),
  );

  fs.writeFileSync(
    path.join(dir, 'single-row.json'),
    JSON.stringify({
      x: 'year',
      series: [{ key: 'value', label: 'value' }],
      data: [{ year: 2026, value: 42 }],
    }),
  );

  fs.writeFileSync(
    path.join(dir, 'six-series.json'),
    JSON.stringify({
      x: 'year',
      series: ['a', 'b', 'c', 'd', 'e', 'f'],
      data: [{ year: 2026, a: 1, b: 2, c: 3, d: 4, e: 5, f: 6 }],
    }),
  );
});

afterAll(() => {
  fs.rmSync(tmpRoot, { recursive: true, force: true });
});

function rootWith(node: Element): Root {
  return { type: 'root', children: [node] };
}

function chartFigure(props: Record<string, unknown>, children: Element[] = []): Element {
  return {
    type: 'element',
    tagName: 'figure',
    properties: { class: 'bfm-figure', 'data-kind': 'chart', ...props },
    children,
  };
}

describe('rehypeBfmChart', () => {
  test('renders a line chart SVG with both series', () => {
    const tree = rootWith(
      chartFigure({ 'data-src': 'two-series.json', 'data-type': 'line' }),
    );
    rehypeBfmChart({ basePath: tmpRoot })(tree, { path: undefined } as any);

    const fig = tree.children[0] as Element;
    expect(String(fig.properties?.class)).toContain('bfm-chart-figure');
    const svg = fig.children[0] as Element;
    expect(svg.tagName).toBe('svg');
    // hast-util-from-html parses class="..." into properties.className (array).
    const className = svg.properties?.className;
    expect(Array.isArray(className) ? className.join(' ') : String(className)).toContain('bfm-chart-svg');
    expect(svg.properties?.role).toBe('img');
    // Both series labels should appear in the SVG XML
    const text = JSON.stringify(svg);
    expect(text).toContain('after rewrite');
    expect(text).toContain('before');
  });

  test('renders a bar chart when type=bar', () => {
    const tree = rootWith(
      chartFigure({ 'data-src': 'two-series.json', 'data-type': 'bar' }),
    );
    rehypeBfmChart({ basePath: tmpRoot })(tree, { path: undefined } as any);

    const fig = tree.children[0] as Element;
    const svg = fig.children[0] as Element;
    const text = JSON.stringify(svg);
    // The "bars" group is parsed as className: ["bars"]
    expect(text).toContain('"bars"');
    expect(text).toContain('"rect"');
  });

  test('renders a single-row chart without divide-by-zero', () => {
    const tree = rootWith(
      chartFigure({ 'data-src': 'single-row.json', 'data-type': 'line' }),
    );
    rehypeBfmChart({ basePath: tmpRoot })(tree, { path: undefined } as any);

    const fig = tree.children[0] as Element;
    const svg = fig.children[0] as Element;
    expect(svg.tagName).toBe('svg');
    const text = JSON.stringify(svg);
    expect(text).not.toContain('NaN');
    expect(text).not.toContain('Infinity');
  });

  test('limits rendering to 4 series even when more are declared', () => {
    const tree = rootWith(
      chartFigure({ 'data-src': 'six-series.json', 'data-type': 'line' }),
    );
    rehypeBfmChart({ basePath: tmpRoot })(tree, { path: undefined } as any);

    const fig = tree.children[0] as Element;
    const svg = fig.children[0] as Element;
    const text = JSON.stringify(svg);
    // Series labels e and f should not appear in the rendered SVG
    expect(text).toContain('"a"');
    expect(text).toContain('"d"');
    expect(text).not.toContain(',"e"');
    expect(text).not.toContain('"f"');
  });

  test('renders an error element when the data file is missing', () => {
    const tree = rootWith(chartFigure({ 'data-src': 'missing.json' }));
    rehypeBfmChart({ basePath: tmpRoot })(tree, { path: undefined } as any);

    const fig = tree.children[0] as Element;
    const err = fig.children[0] as Element;
    expect(err.properties?.class).toBe('bfm-figure-error');
    expect((err.children[0] as { value: string }).value).toBe(
      'Chart data not found: missing.json',
    );
  });

  test('preserves a pre-existing figcaption alongside the SVG', () => {
    const tree = rootWith(
      chartFigure({ 'data-src': 'two-series.json' }, [
        {
          type: 'element',
          tagName: 'figcaption',
          properties: {},
          children: [{ type: 'text', value: 'Throughput doubled.' }],
        },
      ]),
    );
    rehypeBfmChart({ basePath: tmpRoot })(tree, { path: undefined } as any);

    const fig = tree.children[0] as Element;
    const captions = fig.children.filter(
      (c): c is Element => (c as Element).tagName === 'figcaption',
    );
    expect(captions).toHaveLength(1);
    expect((captions[0].children[0] as { value: string }).value).toBe(
      'Throughput doubled.',
    );
  });

  test('uses caption text as the SVG aria-label', () => {
    const tree = rootWith(
      chartFigure({ 'data-src': 'two-series.json' }, [
        {
          type: 'element',
          tagName: 'figcaption',
          properties: {},
          children: [{ type: 'text', value: 'Throughput doubled.' }],
        },
      ]),
    );
    rehypeBfmChart({ basePath: tmpRoot })(tree, { path: undefined } as any);

    const fig = tree.children[0] as Element;
    const svg = fig.children.find(
      (c): c is Element => (c as Element).tagName === 'svg',
    );
    expect(svg?.properties?.['aria-label']).toBe('Throughput doubled.');
  });
});
