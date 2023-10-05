import * as adapter from '@astrojs/netlify/netlify-functions.js';
import { renderers } from './renderers.mjs';
import { manifest } from './manifest_98a149ad.mjs';
import './chunks/astro_c3305d12.mjs';
import 'clsx';
import 'html-escaper';
import 'cookie';
import 'kleur/colors';
import 'string-width';
import 'mime';
import 'path-to-regexp';

const _page0  = () => import('./chunks/image-endpoint_607cfa37.mjs');
const _page1  = () => import('./chunks/index_1bfda1d0.mjs');
const _page2  = () => import('./chunks/rss_b658b8f0.mjs');
const _page3  = () => import('./chunks/index_b4a89994.mjs');
const _page4  = () => import('./chunks/_.._49d69294.mjs');
const _page5  = () => import('./chunks/uses_b7914533.mjs');
const _page6  = () => import('./chunks/_.._9489016b.mjs');const pageMap = new Map([["node_modules/astro/dist/assets/image-endpoint.js", _page0],["src/pages/index.astro", _page1],["src/pages/rss.xml.js", _page2],["src/pages/blog/index.astro", _page3],["src/pages/blog/[...slug].astro", _page4],["src/pages/uses.astro", _page5],["src/pages/og/[...route].ts", _page6]]);
const _manifest = Object.assign(manifest, {
	pageMap,
	renderers,
});
const _args = {};

const _exports = adapter.createExports(_manifest, _args);
const handler = _exports['handler'];

const _start = 'start';
if(_start in adapter) {
	adapter[_start](_manifest, _args);
}

export { handler, pageMap };
