import { describe, test, expect, beforeAll, afterAll } from 'bun:test';
import type { Element, Root } from 'hast';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { rehypeBfmInclude } from './include';

let tmpRoot: string;

beforeAll(() => {
  tmpRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'bfm-include-'));
  const contentDir = path.join(tmpRoot, 'src', 'content');
  fs.mkdirSync(contentDir, { recursive: true });
  fs.writeFileSync(
    path.join(contentDir, 'sample.md'),
    [
      '# Top heading',
      '',
      'Top body.',
      '',
      '## Middle heading',
      '',
      'Middle body.',
      '',
      '## Other heading',
      '',
      'Other body.',
      '',
    ].join('\n'),
  );
});

afterAll(() => {
  fs.rmSync(tmpRoot, { recursive: true, force: true });
});

function rootWith(node: Element): Root {
  return { type: 'root', children: [node] };
}

function includeDiv(props: Record<string, unknown>): Element {
  return {
    type: 'element',
    tagName: 'div',
    properties: { class: 'include', ...props },
    children: [],
  };
}

describe('rehypeBfmInclude', () => {
  test('replaces the include div with the file contents wrapped in include', () => {
    const tree = rootWith(includeDiv({ 'data-path': 'sample.md' }));
    rehypeBfmInclude({ basePath: tmpRoot })(tree);

    const replaced = tree.children[0] as Element;
    expect(replaced.tagName).toBe('div');
    expect(replaced.properties?.class).toBe('include');
    expect(replaced.properties?.['data-source']).toBe('sample.md');
    const raw = replaced.children[0] as { type: string; value: string };
    expect(raw.type).toBe('raw');
    expect(raw.value).toContain('Top body.');
    expect(raw.value).toContain('Middle body.');
  });

  test('extracts only the requested section when data-heading is set', () => {
    const tree = rootWith(
      includeDiv({ 'data-path': 'sample.md', 'data-heading': 'Middle heading' }),
    );
    rehypeBfmInclude({ basePath: tmpRoot })(tree);

    const replaced = tree.children[0] as Element;
    const raw = replaced.children[0] as { value: string };
    expect(raw.value).toContain('Middle heading');
    expect(raw.value).toContain('Middle body.');
    expect(raw.value).not.toContain('Other body.');
    expect(raw.value).not.toContain('Top body.');
  });

  test('renders an error element for a missing file', () => {
    const tree = rootWith(includeDiv({ 'data-path': 'does-not-exist.md' }));
    rehypeBfmInclude({ basePath: tmpRoot })(tree);

    const replaced = tree.children[0] as Element;
    expect(replaced.properties?.class).toBe('include include--error');
    const text = (replaced.children[0] as { value: string }).value;
    expect(text).toBe('Include not found: does-not-exist.md');
  });

  test('escapes embedded HTML in the included content', () => {
    const file = path.join(tmpRoot, 'src', 'content', 'with-html.md');
    fs.writeFileSync(file, 'before <script>x</script> after\n');
    const tree = rootWith(includeDiv({ 'data-path': 'with-html.md' }));
    rehypeBfmInclude({ basePath: tmpRoot })(tree);

    const replaced = tree.children[0] as Element;
    const raw = replaced.children[0] as { value: string };
    expect(raw.value).toContain('&lt;script&gt;');
    expect(raw.value).not.toContain('<script>');
  });

  test('skips when data-path is missing', () => {
    const tree = rootWith(includeDiv({}));
    rehypeBfmInclude({ basePath: tmpRoot })(tree);
    const node = tree.children[0] as Element;
    // Untouched: still the bare include div with no data-source
    expect(node.tagName).toBe('div');
    expect(node.properties?.['data-source']).toBeUndefined();
  });
});
