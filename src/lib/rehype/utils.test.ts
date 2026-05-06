import { describe, test, expect } from 'bun:test';
import type { Element } from 'hast';
import { buildErrorElement, extractText, getDataAttr } from './utils';

describe('extractText', () => {
  test('returns the text value of a single text node', () => {
    expect(extractText({ type: 'text', value: 'hi' } as any)).toBe('hi');
  });

  test('concatenates text across nested children without trimming', () => {
    const node = {
      type: 'element',
      tagName: 'span',
      properties: {},
      children: [
        { type: 'text', value: '  one ' },
        {
          type: 'element',
          tagName: 'em',
          properties: {},
          children: [{ type: 'text', value: 'two' }],
        },
        { type: 'text', value: ' three  ' },
      ],
    };
    expect(extractText(node as any)).toBe('  one two three  ');
  });

  test('returns empty string for a node with no text', () => {
    const node = { type: 'element', tagName: 'br', properties: {}, children: [] };
    expect(extractText(node as any)).toBe('');
  });

  test('returns empty string for a non-text leaf', () => {
    const node = { type: 'comment', value: 'ignored' };
    expect(extractText(node as any)).toBe('');
  });
});

describe('getDataAttr', () => {
  function elt(properties: Record<string, string | number | boolean>): Element {
    return { type: 'element', tagName: 'div', properties, children: [] };
  }

  test('reads kebab-case data-* attribute', () => {
    expect(getDataAttr(elt({ 'data-kind': 'chart' }), 'kind')).toBe('chart');
  });

  test('reads camelCase data attribute when kebab is absent', () => {
    expect(getDataAttr(elt({ dataKind: 'chart' }), 'kind')).toBe('chart');
  });

  test('prefers kebab-case when both forms are present', () => {
    expect(
      getDataAttr(elt({ 'data-kind': 'kebab', dataKind: 'camel' }), 'kind'),
    ).toBe('kebab');
  });

  test('reads multi-segment kebab attributes via camel fallback', () => {
    expect(
      getDataAttr(elt({ dataQueryLimit: '5' }), 'query-limit'),
    ).toBe('5');
  });

  test('returns empty string when the attribute is missing', () => {
    expect(getDataAttr(elt({}), 'kind')).toBe('');
  });

  test('coerces non-string values via String()', () => {
    expect(getDataAttr(elt({ 'data-count': 5 }), 'count')).toBe('5');
  });
});

describe('buildErrorElement', () => {
  test('uses the default bfm-figure-error class', () => {
    const el = buildErrorElement('boom');
    expect(el.tagName).toBe('div');
    expect(el.properties?.class).toBe('bfm-figure-error');
    expect((el.children?.[0] as { value: string }).value).toBe('boom');
  });

  test('honors a custom class name', () => {
    const el = buildErrorElement('missing', 'include include--error');
    expect(el.properties?.class).toBe('include include--error');
  });
});
