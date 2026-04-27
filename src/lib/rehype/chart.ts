/**
 * rehypeBfmChart — renders `@chart` directives as inline SVG at build time.
 *
 * Authoring:
 *
 *   @chart type=line src=./data/foo.json caption="Throughput doubled" width=wide
 *   @endchart
 *
 * Data shape (./data/foo.json):
 *
 *   {
 *     "x": "year",
 *     "series": [
 *       { "key": "primary",    "label": "after rewrite" },
 *       { "key": "comparator", "label": "before" }
 *     ],
 *     "data": [
 *       {"year": 2024, "primary": 100, "comparator": 80},
 *       {"year": 2025, "primary": 180, "comparator": 90},
 *       {"year": 2026, "primary": 200, "comparator": 95}
 *     ]
 *   }
 *
 * Supported types: `line`, `bar`. Up to 4 series. Colors track the design
 * system (--ink, --clay, --ink-3, --warn) in that order.
 */

import type { Root, Element } from 'hast';
import type { VFile } from 'vfile';
import { visit } from 'unist-util-visit';
import { fromHtml } from 'hast-util-from-html';
import fs from 'node:fs';
import path from 'node:path';

export interface ChartOptions {
  basePath?: string;
}

interface SeriesDef {
  key: string;
  label?: string;
}

interface ChartData {
  x: string;
  series?: SeriesDef[] | string[];
  /** Single-series shortcut: `y` field name. */
  y?: string;
  data: Array<Record<string, string | number>>;
  yLabel?: string;
}

// SVG presentation attributes don't reliably resolve `var(--token)` across
// browsers — they only honor CSS custom properties when applied through CSS,
// not as raw XML attributes. Use literal hex from the brand palette instead.
const PALETTE = {
  ink:    '#1A1814',
  ink2:   '#4A453D',
  ink3:   '#7A7368',
  rule:   '#D9D1BE',
  ruleStrong: '#BFB59E',
  clay:   '#B8442B',
  warn:   '#A8741A',
} as const;

const SERIES_COLORS = [PALETTE.ink, PALETTE.clay, PALETTE.ink3, PALETTE.warn];

const SIZE: Record<string, { w: number; h: number }> = {
  prose: { w: 580, h: 320 },
  wide:  { w: 820, h: 440 },
  full:  { w: 960, h: 540 },
};

export function rehypeBfmChart(options: ChartOptions = {}) {
  const basePath = options.basePath || process.cwd();

  return function (tree: Root, file: VFile) {
    visit(tree, 'element', (node: Element) => {
      if (node.tagName !== 'figure') return;
      const cls = String(node.properties?.className || node.properties?.class || '');
      if (!cls.includes('bfm-figure')) return;
      if (String(node.properties?.['dataKind'] || node.properties?.['data-kind']) !== 'chart') return;

      const props = node.properties || {};
      const src    = String(props['dataSrc']  || props['data-src']  || '');
      const type   = String(props['dataType'] || props['data-type'] || 'line');
      const width  = String(props['dataWidth'] || props['data-width'] || 'prose');

      if (!src) return;

      const resolved = resolvePath(src, basePath, file.path);
      let data: ChartData;
      try {
        data = JSON.parse(fs.readFileSync(resolved, 'utf-8'));
      } catch {
        node.children = [
          {
            type: 'element',
            tagName: 'div',
            properties: { class: 'bfm-figure-error' },
            children: [{ type: 'text', value: `Chart data not found: ${src}` }],
          },
          ...keepCaption(node),
        ];
        return;
      }

      const size = SIZE[width] || SIZE.prose;
      const svg = renderChart(type, data, size);

      const fragment = fromHtml(svg, { fragment: true });
      const svgNode = (fragment.children as Element[]).find(c => c.tagName === 'svg');
      if (!svgNode) return;
      const captionText = extractCaptionText(node);
      if (svgNode.properties) {
        svgNode.properties.role = 'img';
        if (captionText) svgNode.properties['aria-label'] = captionText;
      }

      // Mark the figure as chart-styled and replace contents with the SVG +
      // any pre-existing figcaption.
      const existingClass = String(node.properties?.class || node.properties?.className || 'bfm-figure');
      node.properties = {
        ...node.properties,
        class: existingClass.includes('bfm-chart-figure') ? existingClass : `${existingClass} bfm-chart-figure`,
      };
      node.children = [svgNode, ...keepCaption(node)];
    });
  };
}

function keepCaption(node: Element): Element[] {
  return (node.children as Element[]).filter(
    c => c.type === 'element' && (c as Element).tagName === 'figcaption'
  );
}

function extractCaptionText(node: Element): string {
  const cap = (node.children as Element[]).find(
    c => c.type === 'element' && (c as Element).tagName === 'figcaption'
  );
  if (!cap) return '';
  const collect = (n: any): string => {
    if (n.type === 'text') return n.value || '';
    if (n.children) return n.children.map(collect).join('');
    return '';
  };
  return collect(cap).trim();
}

function resolvePath(src: string, basePath: string, filePath?: string): string {
  if ((src.startsWith('./') || src.startsWith('../')) && filePath) {
    return path.resolve(path.dirname(filePath), src);
  }
  return path.resolve(basePath, 'src/content', src);
}

/* ---------- Rendering --------------------------------------------------- */

function renderChart(type: string, data: ChartData, size: { w: number; h: number }): string {
  const series = normalizeSeries(data);
  if (type === 'bar') return renderBar(data, series, size);
  return renderLine(data, series, size);
}

function normalizeSeries(data: ChartData): SeriesDef[] {
  if (data.series) {
    return data.series.map(s => typeof s === 'string' ? { key: s, label: s } : s);
  }
  if (data.y) return [{ key: data.y, label: data.y }];
  // Auto: take all numeric keys other than the x field.
  const sample = data.data[0] || {};
  return Object.keys(sample)
    .filter(k => k !== data.x && typeof sample[k] === 'number')
    .map(k => ({ key: k, label: k }));
}

function renderLine(data: ChartData, series: SeriesDef[], size: { w: number; h: number }): string {
  const padding = { top: 24, right: 100, bottom: 36, left: 40 };
  const innerW = size.w - padding.left - padding.right;
  const innerH = size.h - padding.top - padding.bottom;

  const xs = data.data.map(d => d[data.x]);
  const xCount = xs.length;
  const xPos = (i: number) => padding.left + (xCount === 1 ? innerW / 2 : (i / (xCount - 1)) * innerW);

  const allValues = series.flatMap(s => data.data.map(d => Number(d[s.key])));
  const yMax = Math.max(...allValues);
  const yMin = Math.min(0, ...allValues);
  const yRange = yMax - yMin || 1;
  const yPos = (v: number) => padding.top + innerH - ((v - yMin) / yRange) * innerH;

  const yTicks = niceTicks(yMin, yMax, 4);
  const xLabels = xs.map(String);

  const seriesPaths = series.slice(0, 4).map((s, idx) => {
    const color = SERIES_COLORS[idx];
    const pts = data.data.map((d, i) => `${xPos(i)},${yPos(Number(d[s.key]))}`).join(' L ');
    const lastIdx = data.data.length - 1;
    const lastX = xPos(lastIdx);
    const lastY = yPos(Number(data.data[lastIdx][s.key]));
    return `
      <path d="M ${pts}" fill="none" stroke="${color}" stroke-width="${idx === 0 ? 2.5 : 1.5}" stroke-linecap="square" stroke-linejoin="miter" />
      ${s.label ? `<text x="${lastX + 6}" y="${lastY}" font-family="JetBrains Mono, monospace" font-size="11" fill="${color}" dominant-baseline="middle">${escapeXml(s.label)}</text>` : ''}
    `;
  }).join('');

  return `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size.w} ${size.h}" width="${size.w}" height="${size.h}" class="bfm-chart-svg">
  <g class="axes">
    <line x1="${padding.left}" y1="${padding.top + innerH}" x2="${padding.left + innerW}" y2="${padding.top + innerH}" stroke="${PALETTE.ruleStrong}" stroke-width="1" />
    <line x1="${padding.left}" y1="${padding.top}" x2="${padding.left}" y2="${padding.top + innerH}" stroke="${PALETTE.ruleStrong}" stroke-width="1" />
    ${yTicks.map(t => `
      <line x1="${padding.left - 4}" y1="${yPos(t)}" x2="${padding.left}" y2="${yPos(t)}" stroke="${PALETTE.ruleStrong}" stroke-width="1" />
      <text x="${padding.left - 8}" y="${yPos(t)}" font-family="JetBrains Mono, monospace" font-size="11" fill="${PALETTE.ink3}" text-anchor="end" dominant-baseline="middle">${formatTick(t)}</text>
    `).join('')}
    ${xLabels.map((label, i) => `
      <text x="${xPos(i)}" y="${padding.top + innerH + 18}" font-family="JetBrains Mono, monospace" font-size="11" fill="${PALETTE.ink3}" text-anchor="middle">${escapeXml(label)}</text>
    `).join('')}
  </g>
  <g class="series">${seriesPaths}</g>
</svg>`.trim();
}

function renderBar(data: ChartData, series: SeriesDef[], size: { w: number; h: number }): string {
  const padding = { top: 24, right: 24, bottom: 36, left: 48 };
  const innerW = size.w - padding.left - padding.right;
  const innerH = size.h - padding.top - padding.bottom;

  const xs = data.data.map(d => String(d[data.x]));
  const groupCount = xs.length;
  const seriesCount = Math.min(series.length, 4);
  const groupWidth = innerW / groupCount;
  const barGap = 4;
  const barWidth = (groupWidth - barGap * (seriesCount + 1)) / seriesCount;

  const allValues = series.flatMap(s => data.data.map(d => Number(d[s.key])));
  const yMax = Math.max(...allValues);
  const yMin = 0;
  const yRange = yMax - yMin || 1;
  const yPos = (v: number) => padding.top + innerH - (v / yRange) * innerH;
  const yTicks = niceTicks(yMin, yMax, 4);

  const bars = data.data.flatMap((d, gi) =>
    series.slice(0, 4).map((s, si) => {
      const x = padding.left + gi * groupWidth + barGap + si * (barWidth + barGap);
      const v = Number(d[s.key]);
      const y = yPos(v);
      const h = padding.top + innerH - y;
      return `<rect x="${x}" y="${y}" width="${barWidth}" height="${h}" fill="${SERIES_COLORS[si]}" />`;
    })
  ).join('');

  return `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size.w} ${size.h}" width="${size.w}" height="${size.h}" class="bfm-chart-svg">
  <g class="axes">
    <line x1="${padding.left}" y1="${padding.top + innerH}" x2="${padding.left + innerW}" y2="${padding.top + innerH}" stroke="${PALETTE.ruleStrong}" stroke-width="1" />
    ${yTicks.map(t => `
      <line x1="${padding.left - 4}" y1="${yPos(t)}" x2="${padding.left}" y2="${yPos(t)}" stroke="${PALETTE.ruleStrong}" stroke-width="1" />
      <text x="${padding.left - 8}" y="${yPos(t)}" font-family="JetBrains Mono, monospace" font-size="11" fill="${PALETTE.ink3}" text-anchor="end" dominant-baseline="middle">${formatTick(t)}</text>
    `).join('')}
    ${xs.map((label, i) => `
      <text x="${padding.left + (i + 0.5) * groupWidth}" y="${padding.top + innerH + 18}" font-family="JetBrains Mono, monospace" font-size="11" fill="${PALETTE.ink3}" text-anchor="middle">${escapeXml(label)}</text>
    `).join('')}
  </g>
  <g class="bars">${bars}</g>
</svg>`.trim();
}

function niceTicks(min: number, max: number, count: number): number[] {
  if (max === min) return [min];
  const range = max - min;
  const step = niceStep(range / count);
  const start = Math.floor(min / step) * step;
  const end = Math.ceil(max / step) * step;
  const ticks: number[] = [];
  for (let v = start; v <= end + step / 2; v += step) {
    ticks.push(Number(v.toFixed(10)));
  }
  return ticks;
}

function niceStep(rough: number): number {
  const exp = Math.floor(Math.log10(rough));
  const base = Math.pow(10, exp);
  const norm = rough / base;
  let nice = 1;
  if (norm < 1.5) nice = 1;
  else if (norm < 3)  nice = 2;
  else if (norm < 7)  nice = 5;
  else                nice = 10;
  return nice * base;
}

function formatTick(v: number): string {
  if (Math.abs(v) >= 1000) return v.toLocaleString('en-US');
  if (Number.isInteger(v)) return String(v);
  return v.toFixed(1);
}

function escapeXml(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
