/* empty css                          */import { c as createAstro, b as createComponent, r as renderTemplate, e as addAttribute, m as maybeRenderHead, s as spreadAttributes, f as renderSlot, g as renderComponent, h as renderHead, A as AstroError, C as CollectionDoesNotExistError, i as UnknownContentCollectionError, j as renderUniqueStylesheet, k as renderScriptElement, l as createHeadAndContent, u as unescapeHTML, _ as __astro_tag_component__, F as Fragment, n as createVNode } from './astro_5fda7a2a.mjs';
import 'clsx';
import rss from '@astrojs/rss';
import sharp from 'sharp';
import { p as prependForwardSlash } from './pages/image-endpoint_3b161e1f.mjs';
import init from 'canvaskit-wasm';
import fs from 'fs/promises';
import { createRequire } from 'module';
import axios from 'axios';
import { decodeHTMLStrict } from 'entities';

const $$Astro$a = createAstro("https://example.com");
const $$BaseHead = createComponent(async ($$result, $$props, $$slots) => {
  const Astro2 = $$result.createAstro($$Astro$a, $$props, $$slots);
  Astro2.self = $$BaseHead;
  const canonicalURL = new URL(Astro2.url.pathname, Astro2.site);
  const { title, description, image = "/blog-placeholder-1.jpg" } = Astro2.props;
  return renderTemplate`<!-- Global Metadata --><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" type="image/svg+xml" href="/favicon.svg"><meta name="generator"${addAttribute(Astro2.generator, "content")}><!-- Font preloads --><link rel="preload" href="/fonts/atkinson-regular.woff" as="font" type="font/woff" crossorigin><link rel="preload" href="/fonts/atkinson-bold.woff" as="font" type="font/woff" crossorigin><!-- Canonical URL --><link rel="canonical"${addAttribute(canonicalURL, "href")}><!-- Primary Meta Tags --><title>${title}</title><meta name="title"${addAttribute(title, "content")}><meta name="description"${addAttribute(description, "content")}><!-- Open Graph / Facebook --><meta property="og:type" content="website"><meta property="og:url"${addAttribute(Astro2.url, "content")}><meta property="og:title"${addAttribute(title, "content")}><meta property="og:description"${addAttribute(description, "content")}><meta property="og:image"${addAttribute(new URL(image, Astro2.url), "content")}><!-- Twitter --><meta property="twitter:card" content="summary_large_image"><meta property="twitter:url"${addAttribute(Astro2.url, "content")}><meta property="twitter:title"${addAttribute(title, "content")}><meta property="twitter:description"${addAttribute(description, "content")}><meta property="twitter:image"${addAttribute(new URL(image, Astro2.url), "content")}>`;
}, "/Users/birdcar/Code/birdcar/blog/src/components/BaseHead.astro", void 0);

const $$Astro$9 = createAstro("https://example.com");
const $$HeaderLink = createComponent(async ($$result, $$props, $$slots) => {
  const Astro2 = $$result.createAstro($$Astro$9, $$props, $$slots);
  Astro2.self = $$HeaderLink;
  const { href, class: className, ...props } = Astro2.props;
  const { pathname } = Astro2.url;
  const isActive = href === pathname || href === pathname.replace(/\/$/, "");
  return renderTemplate`${maybeRenderHead()}<a${addAttribute(href, "href")}${addAttribute([className, { active: isActive }], "class:list")}${spreadAttributes(props)}>${renderSlot($$result, $$slots["default"])}</a>`;
}, "/Users/birdcar/Code/birdcar/blog/src/components/HeaderLink.astro", void 0);

const SITE_TITLE = "Astro Blog";
const SITE_DESCRIPTION = "Welcome to my website!";

const $$Astro$8 = createAstro("https://example.com");
const $$Header = createComponent(async ($$result, $$props, $$slots) => {
  const Astro2 = $$result.createAstro($$Astro$8, $$props, $$slots);
  Astro2.self = $$Header;
  return renderTemplate`${maybeRenderHead()}<header data-astro-cid-3ef6ksr2><nav data-astro-cid-3ef6ksr2><h2 data-astro-cid-3ef6ksr2><a href="/" data-astro-cid-3ef6ksr2>${SITE_TITLE}</a></h2><div class="internal-links" data-astro-cid-3ef6ksr2>${renderComponent($$result, "HeaderLink", $$HeaderLink, { "href": "/", "data-astro-cid-3ef6ksr2": true }, { "default": ($$result2) => renderTemplate`Home` })}${renderComponent($$result, "HeaderLink", $$HeaderLink, { "href": "/blog", "data-astro-cid-3ef6ksr2": true }, { "default": ($$result2) => renderTemplate`Blog` })}${renderComponent($$result, "HeaderLink", $$HeaderLink, { "href": "/about", "data-astro-cid-3ef6ksr2": true }, { "default": ($$result2) => renderTemplate`About` })}</div><div class="social-links" data-astro-cid-3ef6ksr2><a href="https://m.webtoo.ls/@astro" target="_blank" data-astro-cid-3ef6ksr2><span class="sr-only" data-astro-cid-3ef6ksr2>Follow Astro on Mastodon</span><svg viewBox="0 0 16 16" aria-hidden="true" width="32" height="32" data-astro-cid-3ef6ksr2><path fill="currentColor" d="M11.19 12.195c2.016-.24 3.77-1.475 3.99-2.603.348-1.778.32-4.339.32-4.339 0-3.47-2.286-4.488-2.286-4.488C12.062.238 10.083.017 8.027 0h-.05C5.92.017 3.942.238 2.79.765c0 0-2.285 1.017-2.285 4.488l-.002.662c-.004.64-.007 1.35.011 2.091.083 3.394.626 6.74 3.78 7.57 1.454.383 2.703.463 3.709.408 1.823-.1 2.847-.647 2.847-.647l-.06-1.317s-1.303.41-2.767.36c-1.45-.05-2.98-.156-3.215-1.928a3.614 3.614 0 0 1-.033-.496s1.424.346 3.228.428c1.103.05 2.137-.064 3.188-.189zm1.613-2.47H11.13v-4.08c0-.859-.364-1.295-1.091-1.295-.804 0-1.207.517-1.207 1.541v2.233H7.168V5.89c0-1.024-.403-1.541-1.207-1.541-.727 0-1.091.436-1.091 1.296v4.079H3.197V5.522c0-.859.22-1.541.66-2.046.456-.505 1.052-.764 1.793-.764.856 0 1.504.328 1.933.983L8 4.39l.417-.695c.429-.655 1.077-.983 1.934-.983.74 0 1.336.259 1.791.764.442.505.661 1.187.661 2.046v4.203z" data-astro-cid-3ef6ksr2></path></svg></a><a href="https://twitter.com/astrodotbuild" target="_blank" data-astro-cid-3ef6ksr2><span class="sr-only" data-astro-cid-3ef6ksr2>Follow Astro on Twitter</span><svg viewBox="0 0 16 16" aria-hidden="true" width="32" height="32" data-astro-cid-3ef6ksr2><path fill="currentColor" d="M5.026 15c6.038 0 9.341-5.003 9.341-9.334 0-.14 0-.282-.006-.422A6.685 6.685 0 0 0 16 3.542a6.658 6.658 0 0 1-1.889.518 3.301 3.301 0 0 0 1.447-1.817 6.533 6.533 0 0 1-2.087.793A3.286 3.286 0 0 0 7.875 6.03a9.325 9.325 0 0 1-6.767-3.429 3.289 3.289 0 0 0 1.018 4.382A3.323 3.323 0 0 1 .64 6.575v.045a3.288 3.288 0 0 0 2.632 3.218 3.203 3.203 0 0 1-.865.115 3.23 3.23 0 0 1-.614-.057 3.283 3.283 0 0 0 3.067 2.277A6.588 6.588 0 0 1 .78 13.58a6.32 6.32 0 0 1-.78-.045A9.344 9.344 0 0 0 5.026 15z" data-astro-cid-3ef6ksr2></path></svg></a><a href="https://github.com/withastro/astro" target="_blank" data-astro-cid-3ef6ksr2><span class="sr-only" data-astro-cid-3ef6ksr2>Go to Astro's GitHub repo</span><svg viewBox="0 0 16 16" aria-hidden="true" width="32" height="32" data-astro-cid-3ef6ksr2><path fill="currentColor" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 16 8c0-4.42-3.58-8-8-8z" data-astro-cid-3ef6ksr2></path></svg></a></div></nav></header>`;
}, "/Users/birdcar/Code/birdcar/blog/src/components/Header.astro", void 0);

const $$Astro$7 = createAstro("https://example.com");
const $$Footer = createComponent(async ($$result, $$props, $$slots) => {
  const Astro2 = $$result.createAstro($$Astro$7, $$props, $$slots);
  Astro2.self = $$Footer;
  const today = /* @__PURE__ */ new Date();
  return renderTemplate`${maybeRenderHead()}<footer data-astro-cid-sz7xmlte>
&copy; ${today.getFullYear()} Your name here. All rights reserved.
<div class="social-links" data-astro-cid-sz7xmlte><a href="https://m.webtoo.ls/@astro" target="_blank" data-astro-cid-sz7xmlte><span class="sr-only" data-astro-cid-sz7xmlte>Follow Astro on Mastodon</span><svg viewBox="0 0 16 16" aria-hidden="true" width="32" height="32" astro-icon="social/mastodon" data-astro-cid-sz7xmlte><path fill="currentColor" d="M11.19 12.195c2.016-.24 3.77-1.475 3.99-2.603.348-1.778.32-4.339.32-4.339 0-3.47-2.286-4.488-2.286-4.488C12.062.238 10.083.017 8.027 0h-.05C5.92.017 3.942.238 2.79.765c0 0-2.285 1.017-2.285 4.488l-.002.662c-.004.64-.007 1.35.011 2.091.083 3.394.626 6.74 3.78 7.57 1.454.383 2.703.463 3.709.408 1.823-.1 2.847-.647 2.847-.647l-.06-1.317s-1.303.41-2.767.36c-1.45-.05-2.98-.156-3.215-1.928a3.614 3.614 0 0 1-.033-.496s1.424.346 3.228.428c1.103.05 2.137-.064 3.188-.189zm1.613-2.47H11.13v-4.08c0-.859-.364-1.295-1.091-1.295-.804 0-1.207.517-1.207 1.541v2.233H7.168V5.89c0-1.024-.403-1.541-1.207-1.541-.727 0-1.091.436-1.091 1.296v4.079H3.197V5.522c0-.859.22-1.541.66-2.046.456-.505 1.052-.764 1.793-.764.856 0 1.504.328 1.933.983L8 4.39l.417-.695c.429-.655 1.077-.983 1.934-.983.74 0 1.336.259 1.791.764.442.505.661 1.187.661 2.046v4.203z" data-astro-cid-sz7xmlte></path></svg></a><a href="https://twitter.com/astrodotbuild" target="_blank" data-astro-cid-sz7xmlte><span class="sr-only" data-astro-cid-sz7xmlte>Follow Astro on Twitter</span><svg viewBox="0 0 16 16" aria-hidden="true" width="32" height="32" astro-icon="social/twitter" data-astro-cid-sz7xmlte><path fill="currentColor" d="M5.026 15c6.038 0 9.341-5.003 9.341-9.334 0-.14 0-.282-.006-.422A6.685 6.685 0 0 0 16 3.542a6.658 6.658 0 0 1-1.889.518 3.301 3.301 0 0 0 1.447-1.817 6.533 6.533 0 0 1-2.087.793A3.286 3.286 0 0 0 7.875 6.03a9.325 9.325 0 0 1-6.767-3.429 3.289 3.289 0 0 0 1.018 4.382A3.323 3.323 0 0 1 .64 6.575v.045a3.288 3.288 0 0 0 2.632 3.218 3.203 3.203 0 0 1-.865.115 3.23 3.23 0 0 1-.614-.057 3.283 3.283 0 0 0 3.067 2.277A6.588 6.588 0 0 1 .78 13.58a6.32 6.32 0 0 1-.78-.045A9.344 9.344 0 0 0 5.026 15z" data-astro-cid-sz7xmlte></path></svg></a><a href="https://github.com/withastro/astro" target="_blank" data-astro-cid-sz7xmlte><span class="sr-only" data-astro-cid-sz7xmlte>Go to Astro's GitHub repo</span><svg viewBox="0 0 16 16" aria-hidden="true" width="32" height="32" astro-icon="social/github" data-astro-cid-sz7xmlte><path fill="currentColor" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 16 8c0-4.42-3.58-8-8-8z" data-astro-cid-sz7xmlte></path></svg></a></div></footer>`;
}, "/Users/birdcar/Code/birdcar/blog/src/components/Footer.astro", void 0);

const $$Astro$6 = createAstro("https://example.com");
const $$Index$1 = createComponent(async ($$result, $$props, $$slots) => {
  const Astro2 = $$result.createAstro($$Astro$6, $$props, $$slots);
  Astro2.self = $$Index$1;
  return renderTemplate`<html lang="en"><head>${renderComponent($$result, "BaseHead", $$BaseHead, { "title": SITE_TITLE, "description": SITE_DESCRIPTION })}${renderHead()}</head><body>${renderComponent($$result, "Header", $$Header, { "title": SITE_TITLE })}<main><h1>🧑‍🚀 Hello, Astronaut!</h1><p>
Welcome to the official <a href="https://astro.build/">Astro</a> blog
        starter template. This template serves as a lightweight,
        minimally-styled starting point for anyone looking to build a personal
        website, blog, or portfolio with Astro.
</p><p>
This template comes with a few integrations already configured in your
<code>astro.config.mjs</code> file. You can customize your setup with
<a href="https://astro.build/integrations">Astro Integrations</a> to add
        tools like Tailwind, React, or Vue to your project.
</p><p>Here are a few ideas on how to get started with the template:</p><ul><li>Edit this page in <code>src/pages/index.astro</code></li><li>
Edit the site header items in <code>src/components/Header.astro</code></li><li>
Add your name to the footer in <code>src/components/Footer.astro</code></li><li>
Check out the included blog posts in <code>src/pages/blog/</code></li><li>
Customize the blog post page layout in <code>src/layouts/BlogPost.astro</code></li></ul><p>
Have fun! If you get stuck, remember to <a href="https://docs.astro.build/">read the docs
</a> or <a href="https://astro.build/chat">join us on Discord</a> to ask
        questions.
</p><p>
Looking for a blog template with a bit more personality? Check out <a href="https://github.com/Charca/astro-blog-template">astro-blog-template
</a> by <a href="https://twitter.com/Charca">Maxi Ferreira</a>.
</p></main>${renderComponent($$result, "Footer", $$Footer, {})}</body></html>`;
}, "/Users/birdcar/Code/birdcar/blog/src/pages/index.astro", void 0);

const $$file$3 = "/Users/birdcar/Code/birdcar/blog/src/pages/index.astro";
const $$url$3 = "";

const index$1 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  default: $$Index$1,
  file: $$file$3,
  url: $$url$3
}, Symbol.toStringTag, { value: 'Module' }));

function createCollectionToGlobResultMap({
  globResult,
  contentDir
}) {
  const collectionToGlobResultMap = {};
  for (const key in globResult) {
    const keyRelativeToContentDir = key.replace(new RegExp(`^${contentDir}`), "");
    const segments = keyRelativeToContentDir.split("/");
    if (segments.length <= 1)
      continue;
    const collection = segments[0];
    collectionToGlobResultMap[collection] ??= {};
    collectionToGlobResultMap[collection][key] = globResult[key];
  }
  return collectionToGlobResultMap;
}
const cacheEntriesByCollection = /* @__PURE__ */ new Map();
function createGetCollection({
  contentCollectionToEntryMap,
  dataCollectionToEntryMap,
  getRenderEntryImport
}) {
  return async function getCollection(collection, filter) {
    let type;
    if (collection in contentCollectionToEntryMap) {
      type = "content";
    } else if (collection in dataCollectionToEntryMap) {
      type = "data";
    } else {
      throw new AstroError({
        ...CollectionDoesNotExistError,
        message: CollectionDoesNotExistError.message(collection)
      });
    }
    const lazyImports = Object.values(
      type === "content" ? contentCollectionToEntryMap[collection] : dataCollectionToEntryMap[collection]
    );
    let entries = [];
    if (cacheEntriesByCollection.has(collection)) {
      entries = [...cacheEntriesByCollection.get(collection)];
    } else {
      entries = await Promise.all(
        lazyImports.map(async (lazyImport) => {
          const entry = await lazyImport();
          return type === "content" ? {
            id: entry.id,
            slug: entry.slug,
            body: entry.body,
            collection: entry.collection,
            data: entry.data,
            async render() {
              return render({
                collection: entry.collection,
                id: entry.id,
                renderEntryImport: await getRenderEntryImport(collection, entry.slug)
              });
            }
          } : {
            id: entry.id,
            collection: entry.collection,
            data: entry.data
          };
        })
      );
      cacheEntriesByCollection.set(collection, entries);
    }
    if (typeof filter === "function") {
      return entries.filter(filter);
    } else {
      return entries;
    }
  };
}
async function render({
  collection,
  id,
  renderEntryImport
}) {
  const UnexpectedRenderError = new AstroError({
    ...UnknownContentCollectionError,
    message: `Unexpected error while rendering ${String(collection)} \u2192 ${String(id)}.`
  });
  if (typeof renderEntryImport !== "function")
    throw UnexpectedRenderError;
  const baseMod = await renderEntryImport();
  if (baseMod == null || typeof baseMod !== "object")
    throw UnexpectedRenderError;
  const { default: defaultMod } = baseMod;
  if (isPropagatedAssetsModule(defaultMod)) {
    const { collectedStyles, collectedLinks, collectedScripts, getMod } = defaultMod;
    if (typeof getMod !== "function")
      throw UnexpectedRenderError;
    const propagationMod = await getMod();
    if (propagationMod == null || typeof propagationMod !== "object")
      throw UnexpectedRenderError;
    const Content = createComponent({
      factory(result, baseProps, slots) {
        let styles = "", links = "", scripts = "";
        if (Array.isArray(collectedStyles)) {
          styles = collectedStyles.map((style) => {
            return renderUniqueStylesheet(result, {
              type: "inline",
              content: style
            });
          }).join("");
        }
        if (Array.isArray(collectedLinks)) {
          links = collectedLinks.map((link) => {
            return renderUniqueStylesheet(result, {
              type: "external",
              src: prependForwardSlash(link)
            });
          }).join("");
        }
        if (Array.isArray(collectedScripts)) {
          scripts = collectedScripts.map((script) => renderScriptElement(script)).join("");
        }
        let props = baseProps;
        if (id.endsWith("mdx")) {
          props = {
            components: propagationMod.components ?? {},
            ...baseProps
          };
        }
        return createHeadAndContent(
          unescapeHTML(styles + links + scripts),
          renderTemplate`${renderComponent(
            result,
            "Content",
            propagationMod.Content,
            props,
            slots
          )}`
        );
      },
      propagation: "self"
    });
    return {
      Content,
      headings: propagationMod.getHeadings?.() ?? [],
      remarkPluginFrontmatter: propagationMod.frontmatter ?? {}
    };
  } else if (baseMod.Content && typeof baseMod.Content === "function") {
    return {
      Content: baseMod.Content,
      headings: baseMod.getHeadings?.() ?? [],
      remarkPluginFrontmatter: baseMod.frontmatter ?? {}
    };
  } else {
    throw UnexpectedRenderError;
  }
}
function isPropagatedAssetsModule(module) {
  return typeof module === "object" && module != null && "__astroPropagation" in module;
}

// astro-head-inject

const contentDir = '/src/content/';

const contentEntryGlob = /* #__PURE__ */ Object.assign({"/src/content/blog/first-post.md": () => import('./first-post_5a6dabbb.mjs'),"/src/content/blog/markdown-style-guide.md": () => import('./markdown-style-guide_e45a3606.mjs'),"/src/content/blog/second-post.md": () => import('./second-post_0aeae2df.mjs'),"/src/content/blog/third-post.md": () => import('./third-post_14715d99.mjs'),"/src/content/blog/using-mdx.mdx": () => import('./using-mdx_10319a2e.mjs')

});
const contentCollectionToEntryMap = createCollectionToGlobResultMap({
	globResult: contentEntryGlob,
	contentDir,
});

const dataEntryGlob = /* #__PURE__ */ Object.assign({

});
const dataCollectionToEntryMap = createCollectionToGlobResultMap({
	globResult: dataEntryGlob,
	contentDir,
});
createCollectionToGlobResultMap({
	globResult: { ...contentEntryGlob, ...dataEntryGlob },
	contentDir,
});

let lookupMap = {};
lookupMap = {"blog":{"type":"content","entries":{"first-post":"/src/content/blog/first-post.md","markdown-style-guide":"/src/content/blog/markdown-style-guide.md","second-post":"/src/content/blog/second-post.md","third-post":"/src/content/blog/third-post.md","using-mdx":"/src/content/blog/using-mdx.mdx"}}};

function createGlobLookup(glob) {
	return async (collection, lookupId) => {
		const filePath = lookupMap[collection]?.entries[lookupId];

		if (!filePath) return undefined;
		return glob[collection][filePath];
	};
}

const renderEntryGlob = /* #__PURE__ */ Object.assign({"/src/content/blog/first-post.md": () => import('./first-post_c006a85c.mjs'),"/src/content/blog/markdown-style-guide.md": () => import('./markdown-style-guide_ba497a26.mjs'),"/src/content/blog/second-post.md": () => import('./second-post_81fe3bc2.mjs'),"/src/content/blog/third-post.md": () => import('./third-post_e4efa6b0.mjs'),"/src/content/blog/using-mdx.mdx": () => import('./using-mdx_299e085e.mjs')

});
const collectionToRenderEntryMap = createCollectionToGlobResultMap({
	globResult: renderEntryGlob,
	contentDir,
});

const getCollection = createGetCollection({
	contentCollectionToEntryMap,
	dataCollectionToEntryMap,
	getRenderEntryImport: createGlobLookup(collectionToRenderEntryMap),
});

async function GET(context) {
	const posts = await getCollection('blog');
	return rss({
		title: SITE_TITLE,
		description: SITE_DESCRIPTION,
		site: context.site,
		items: posts.map((post) => ({
			...post.data,
			link: `/blog/${post.slug}/`,
		})),
	});
}

const rss_xml = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  GET
}, Symbol.toStringTag, { value: 'Module' }));

const $$Astro$5 = createAstro("https://example.com");
const $$BaseLayout = createComponent(async ($$result, $$props, $$slots) => {
  const Astro2 = $$result.createAstro($$Astro$5, $$props, $$slots);
  Astro2.self = $$BaseLayout;
  return renderTemplate`<html lang="en" class="h-full"><head>${renderComponent($$result, "BaseHead", $$BaseHead, { "title": SITE_TITLE, "description": SITE_DESCRIPTION })}${renderHead()}</head><body class="h-full bg-gray-50 text-zinc-900 selection:bg-yellow-200 selection:text-yellow-900 dark:bg-zinc-900 dark:text-zinc-50">${renderComponent($$result, "Header", $$Header, { "title": SITE_TITLE })}<main>${renderSlot($$result, $$slots["default"])}</main>${renderComponent($$result, "Footer", $$Footer, {})}</body></html>`;
}, "/Users/birdcar/Code/birdcar/blog/src/layouts/BaseLayout.astro", void 0);

const $$Astro$4 = createAstro("https://example.com");
const $$BlogPost = createComponent(async ($$result, $$props, $$slots) => {
  const Astro2 = $$result.createAstro($$Astro$4, $$props, $$slots);
  Astro2.self = $$BlogPost;
  const { title, description, pubDate, updatedDate, heroImage } = Astro2.props;
  return renderTemplate`${renderComponent($$result, "BaseLayout", $$BaseLayout, {}, { "default": ($$result2) => renderTemplate`${maybeRenderHead()}<article class="mx-auto my-12 prose prose-zinc dark:prose-invert lg:prose-lg xl:prose-xl 2xl:prose-2xl"><header><h1>${title}</h1></header>${description}${renderSlot($$result2, $$slots["default"])}</article>` })}`;
}, "/Users/birdcar/Code/birdcar/blog/src/layouts/BlogPost.astro", void 0);

const $$Astro$3 = createAstro("https://example.com");
const $$About = createComponent(async ($$result, $$props, $$slots) => {
  const Astro2 = $$result.createAstro($$Astro$3, $$props, $$slots);
  Astro2.self = $$About;
  return renderTemplate`${renderComponent($$result, "Layout", $$BlogPost, { "title": "About Me", "description": "Lorem ipsum dolor sit amet", "pubDate":  new Date("August 08 2021"), "heroImage": "/blog-placeholder-about.jpg" }, { "default": ($$result2) => renderTemplate`${maybeRenderHead()}<p>
Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut
		labore et dolore magna aliqua. Vitae ultricies leo integer malesuada nunc vel risus commodo
		viverra. Adipiscing enim eu turpis egestas pretium. Euismod elementum nisi quis eleifend quam
		adipiscing. In hac habitasse platea dictumst vestibulum. Sagittis purus sit amet volutpat. Netus
		et malesuada fames ac turpis egestas. Eget magna fermentum iaculis eu non diam phasellus
		vestibulum lorem. Varius sit amet mattis vulputate enim. Habitasse platea dictumst quisque
		sagittis. Integer quis auctor elit sed vulputate mi. Dictumst quisque sagittis purus sit amet.
</p><p>
Morbi tristique senectus et netus. Id semper risus in hendrerit gravida rutrum quisque non
		tellus. Habitasse platea dictumst quisque sagittis purus sit amet. Tellus molestie nunc non
		blandit massa. Cursus vitae congue mauris rhoncus. Accumsan tortor posuere ac ut. Fringilla urna
		porttitor rhoncus dolor. Elit ullamcorper dignissim cras tincidunt lobortis. In cursus turpis
		massa tincidunt dui ut ornare lectus. Integer feugiat scelerisque varius morbi enim nunc.
		Bibendum neque egestas congue quisque egestas diam. Cras ornare arcu dui vivamus arcu felis
		bibendum. Dignissim suspendisse in est ante in nibh mauris. Sed tempus urna et pharetra pharetra
		massa massa ultricies mi.
</p><p>
Mollis nunc sed id semper risus in. Convallis a cras semper auctor neque. Diam sit amet nisl
		suscipit. Lacus viverra vitae congue eu consequat ac felis donec. Egestas integer eget aliquet
		nibh praesent tristique magna sit amet. Eget magna fermentum iaculis eu non diam. In vitae
		turpis massa sed elementum. Tristique et egestas quis ipsum suspendisse ultrices. Eget lorem
		dolor sed viverra ipsum. Vel turpis nunc eget lorem dolor sed viverra. Posuere ac ut consequat
		semper viverra nam. Laoreet suspendisse interdum consectetur libero id faucibus. Diam phasellus
		vestibulum lorem sed risus ultricies tristique. Rhoncus dolor purus non enim praesent elementum
		facilisis. Ultrices tincidunt arcu non sodales neque. Tempus egestas sed sed risus pretium quam
		vulputate. Viverra suspendisse potenti nullam ac tortor vitae purus faucibus ornare. Fringilla
		urna porttitor rhoncus dolor purus non. Amet dictum sit amet justo donec enim.
</p><p>
Mattis ullamcorper velit sed ullamcorper morbi tincidunt. Tortor posuere ac ut consequat semper
		viverra. Tellus mauris a diam maecenas sed enim ut sem viverra. Venenatis urna cursus eget nunc
		scelerisque viverra mauris in. Arcu ac tortor dignissim convallis aenean et tortor at. Curabitur
		gravida arcu ac tortor dignissim convallis aenean et tortor. Egestas tellus rutrum tellus
		pellentesque eu. Fusce ut placerat orci nulla pellentesque dignissim enim sit amet. Ut enim
		blandit volutpat maecenas volutpat blandit aliquam etiam. Id donec ultrices tincidunt arcu. Id
		cursus metus aliquam eleifend mi.
</p><p>
Tempus quam pellentesque nec nam aliquam sem. Risus at ultrices mi tempus imperdiet. Id porta
		nibh venenatis cras sed felis eget velit. Ipsum a arcu cursus vitae. Facilisis magna etiam
		tempor orci eu lobortis elementum. Tincidunt dui ut ornare lectus sit. Quisque non tellus orci
		ac. Blandit libero volutpat sed cras. Nec tincidunt praesent semper feugiat nibh sed pulvinar
		proin gravida. Egestas integer eget aliquet nibh praesent tristique magna.
</p>` })}`;
}, "/Users/birdcar/Code/birdcar/blog/src/pages/about.astro", void 0);

const $$file$2 = "/Users/birdcar/Code/birdcar/blog/src/pages/about.astro";
const $$url$2 = "/about";

const about = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  default: $$About,
  file: $$file$2,
  url: $$url$2
}, Symbol.toStringTag, { value: 'Module' }));

const $$Astro$2 = createAstro("https://example.com");
const $$FormattedDate = createComponent(async ($$result, $$props, $$slots) => {
  const Astro2 = $$result.createAstro($$Astro$2, $$props, $$slots);
  Astro2.self = $$FormattedDate;
  const { date } = Astro2.props;
  return renderTemplate`${maybeRenderHead()}<time${addAttribute(date.toISOString(), "datetime")}>${date.toLocaleDateString("en-us", {
    year: "numeric",
    month: "short",
    day: "numeric"
  })}</time>`;
}, "/Users/birdcar/Code/birdcar/blog/src/components/FormattedDate.astro", void 0);

const $$Astro$1 = createAstro("https://example.com");
const $$Index = createComponent(async ($$result, $$props, $$slots) => {
  const Astro2 = $$result.createAstro($$Astro$1, $$props, $$slots);
  Astro2.self = $$Index;
  const posts = (await getCollection("blog")).sort(
    (a, b) => a.data.pubDate.valueOf() - b.data.pubDate.valueOf()
  );
  return renderTemplate`<html lang="en"><head>${renderComponent($$result, "BaseHead", $$BaseHead, { "title": SITE_TITLE, "description": SITE_DESCRIPTION })}${renderHead()}</head><body>${renderComponent($$result, "Header", $$Header, {})}<main><section><ul>${posts.map((post) => renderTemplate`<li><a${addAttribute(`/blog/${post.slug}/`, "href")}><img${addAttribute(720, "width")}${addAttribute(360, "height")}${addAttribute(post.data.heroImage, "src")} alt=""><h4 class="title">${post.data.title}</h4><p class="date">${renderComponent($$result, "FormattedDate", $$FormattedDate, { "date": post.data.pubDate })}</p></a></li>`)}</ul></section></main>${renderComponent($$result, "Footer", $$Footer, {})}</body></html>`;
}, "/Users/birdcar/Code/birdcar/blog/src/pages/blog/index.astro", void 0);

const $$file$1 = "/Users/birdcar/Code/birdcar/blog/src/pages/blog/index.astro";
const $$url$1 = "/blog";

const index = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  default: $$Index,
  file: $$file$1,
  url: $$url$1
}, Symbol.toStringTag, { value: 'Module' }));

const $$Astro = createAstro("https://example.com");
async function getStaticPaths$1() {
  const posts = await getCollection("blog");
  return posts.map((post) => ({
    params: { slug: post.slug },
    props: post
  }));
}
const $$ = createComponent(async ($$result, $$props, $$slots) => {
  const Astro2 = $$result.createAstro($$Astro, $$props, $$slots);
  Astro2.self = $$;
  const post = Astro2.props;
  const { Content } = await post.render();
  return renderTemplate`${renderComponent($$result, "BlogPost", $$BlogPost, { ...post.data }, { "default": ($$result2) => renderTemplate`${renderComponent($$result2, "Content", Content, {})}` })}`;
}, "/Users/birdcar/Code/birdcar/blog/src/pages/blog/[...slug].astro", void 0);

const $$file = "/Users/birdcar/Code/birdcar/blog/src/pages/blog/[...slug].astro";
const $$url = "/blog/[...slug]";

const ____slug_ = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  default: $$,
  file: $$file,
  getStaticPaths: getStaticPaths$1,
  url: $$url
}, Symbol.toStringTag, { value: 'Module' }));

const images$4 = {
					
				};

				function updateImageReferences$3(html) {
					return html.replaceAll(
						/__ASTRO_IMAGE_="([^"]+)"/gm,
						(full, imagePath) => spreadAttributes({src: images$4[imagePath].src, ...images$4[imagePath].attributes})
					);
				}

				const html$3 = updateImageReferences$3("<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Vitae ultricies leo integer malesuada nunc vel risus commodo viverra. Adipiscing enim eu turpis egestas pretium. Euismod elementum nisi quis eleifend quam adipiscing. In hac habitasse platea dictumst vestibulum. Sagittis purus sit amet volutpat. Netus et malesuada fames ac turpis egestas. Eget magna fermentum iaculis eu non diam phasellus vestibulum lorem. Varius sit amet mattis vulputate enim. Habitasse platea dictumst quisque sagittis. Integer quis auctor elit sed vulputate mi. Dictumst quisque sagittis purus sit amet.</p>\n<p>Morbi tristique senectus et netus. Id semper risus in hendrerit gravida rutrum quisque non tellus. Habitasse platea dictumst quisque sagittis purus sit amet. Tellus molestie nunc non blandit massa. Cursus vitae congue mauris rhoncus. Accumsan tortor posuere ac ut. Fringilla urna porttitor rhoncus dolor. Elit ullamcorper dignissim cras tincidunt lobortis. In cursus turpis massa tincidunt dui ut ornare lectus. Integer feugiat scelerisque varius morbi enim nunc. Bibendum neque egestas congue quisque egestas diam. Cras ornare arcu dui vivamus arcu felis bibendum. Dignissim suspendisse in est ante in nibh mauris. Sed tempus urna et pharetra pharetra massa massa ultricies mi.</p>\n<p>Mollis nunc sed id semper risus in. Convallis a cras semper auctor neque. Diam sit amet nisl suscipit. Lacus viverra vitae congue eu consequat ac felis donec. Egestas integer eget aliquet nibh praesent tristique magna sit amet. Eget magna fermentum iaculis eu non diam. In vitae turpis massa sed elementum. Tristique et egestas quis ipsum suspendisse ultrices. Eget lorem dolor sed viverra ipsum. Vel turpis nunc eget lorem dolor sed viverra. Posuere ac ut consequat semper viverra nam. Laoreet suspendisse interdum consectetur libero id faucibus. Diam phasellus vestibulum lorem sed risus ultricies tristique. Rhoncus dolor purus non enim praesent elementum facilisis. Ultrices tincidunt arcu non sodales neque. Tempus egestas sed sed risus pretium quam vulputate. Viverra suspendisse potenti nullam ac tortor vitae purus faucibus ornare. Fringilla urna porttitor rhoncus dolor purus non. Amet dictum sit amet justo donec enim.</p>\n<p>Mattis ullamcorper velit sed ullamcorper morbi tincidunt. Tortor posuere ac ut consequat semper viverra. Tellus mauris a diam maecenas sed enim ut sem viverra. Venenatis urna cursus eget nunc scelerisque viverra mauris in. Arcu ac tortor dignissim convallis aenean et tortor at. Curabitur gravida arcu ac tortor dignissim convallis aenean et tortor. Egestas tellus rutrum tellus pellentesque eu. Fusce ut placerat orci nulla pellentesque dignissim enim sit amet. Ut enim blandit volutpat maecenas volutpat blandit aliquam etiam. Id donec ultrices tincidunt arcu. Id cursus metus aliquam eleifend mi.</p>\n<p>Tempus quam pellentesque nec nam aliquam sem. Risus at ultrices mi tempus imperdiet. Id porta nibh venenatis cras sed felis eget velit. Ipsum a arcu cursus vitae. Facilisis magna etiam tempor orci eu lobortis elementum. Tincidunt dui ut ornare lectus sit. Quisque non tellus orci ac. Blandit libero volutpat sed cras. Nec tincidunt praesent semper feugiat nibh sed pulvinar proin gravida. Egestas integer eget aliquet nibh praesent tristique magna.</p>");

				const frontmatter$4 = {"title":"First post","description":"Lorem ipsum dolor sit amet","pubDate":"Jul 08 2022","heroImage":"/blog-placeholder-3.jpg"};
				const file$4 = "/Users/birdcar/Code/birdcar/blog/src/content/blog/first-post.md";
				const url$4 = undefined;
				function rawContent$3() {
					return "\nLorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Vitae ultricies leo integer malesuada nunc vel risus commodo viverra. Adipiscing enim eu turpis egestas pretium. Euismod elementum nisi quis eleifend quam adipiscing. In hac habitasse platea dictumst vestibulum. Sagittis purus sit amet volutpat. Netus et malesuada fames ac turpis egestas. Eget magna fermentum iaculis eu non diam phasellus vestibulum lorem. Varius sit amet mattis vulputate enim. Habitasse platea dictumst quisque sagittis. Integer quis auctor elit sed vulputate mi. Dictumst quisque sagittis purus sit amet.\n\nMorbi tristique senectus et netus. Id semper risus in hendrerit gravida rutrum quisque non tellus. Habitasse platea dictumst quisque sagittis purus sit amet. Tellus molestie nunc non blandit massa. Cursus vitae congue mauris rhoncus. Accumsan tortor posuere ac ut. Fringilla urna porttitor rhoncus dolor. Elit ullamcorper dignissim cras tincidunt lobortis. In cursus turpis massa tincidunt dui ut ornare lectus. Integer feugiat scelerisque varius morbi enim nunc. Bibendum neque egestas congue quisque egestas diam. Cras ornare arcu dui vivamus arcu felis bibendum. Dignissim suspendisse in est ante in nibh mauris. Sed tempus urna et pharetra pharetra massa massa ultricies mi.\n\nMollis nunc sed id semper risus in. Convallis a cras semper auctor neque. Diam sit amet nisl suscipit. Lacus viverra vitae congue eu consequat ac felis donec. Egestas integer eget aliquet nibh praesent tristique magna sit amet. Eget magna fermentum iaculis eu non diam. In vitae turpis massa sed elementum. Tristique et egestas quis ipsum suspendisse ultrices. Eget lorem dolor sed viverra ipsum. Vel turpis nunc eget lorem dolor sed viverra. Posuere ac ut consequat semper viverra nam. Laoreet suspendisse interdum consectetur libero id faucibus. Diam phasellus vestibulum lorem sed risus ultricies tristique. Rhoncus dolor purus non enim praesent elementum facilisis. Ultrices tincidunt arcu non sodales neque. Tempus egestas sed sed risus pretium quam vulputate. Viverra suspendisse potenti nullam ac tortor vitae purus faucibus ornare. Fringilla urna porttitor rhoncus dolor purus non. Amet dictum sit amet justo donec enim.\n\nMattis ullamcorper velit sed ullamcorper morbi tincidunt. Tortor posuere ac ut consequat semper viverra. Tellus mauris a diam maecenas sed enim ut sem viverra. Venenatis urna cursus eget nunc scelerisque viverra mauris in. Arcu ac tortor dignissim convallis aenean et tortor at. Curabitur gravida arcu ac tortor dignissim convallis aenean et tortor. Egestas tellus rutrum tellus pellentesque eu. Fusce ut placerat orci nulla pellentesque dignissim enim sit amet. Ut enim blandit volutpat maecenas volutpat blandit aliquam etiam. Id donec ultrices tincidunt arcu. Id cursus metus aliquam eleifend mi.\n\nTempus quam pellentesque nec nam aliquam sem. Risus at ultrices mi tempus imperdiet. Id porta nibh venenatis cras sed felis eget velit. Ipsum a arcu cursus vitae. Facilisis magna etiam tempor orci eu lobortis elementum. Tincidunt dui ut ornare lectus sit. Quisque non tellus orci ac. Blandit libero volutpat sed cras. Nec tincidunt praesent semper feugiat nibh sed pulvinar proin gravida. Egestas integer eget aliquet nibh praesent tristique magna.\n";
				}
				function compiledContent$3() {
					return html$3;
				}
				function getHeadings$4() {
					return [];
				}

				const Content$4 = createComponent((result, _props, slots) => {
					const { layout, ...content } = frontmatter$4;
					content.file = file$4;
					content.url = url$4;

					return renderTemplate`${maybeRenderHead()}${unescapeHTML(html$3)}`;
				});

const __vite_glob_0_0 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  Content: Content$4,
  compiledContent: compiledContent$3,
  default: Content$4,
  file: file$4,
  frontmatter: frontmatter$4,
  getHeadings: getHeadings$4,
  images: images$4,
  rawContent: rawContent$3,
  url: url$4
}, Symbol.toStringTag, { value: 'Module' }));

const images$3 = {
					
				};

				function updateImageReferences$2(html) {
					return html.replaceAll(
						/__ASTRO_IMAGE_="([^"]+)"/gm,
						(full, imagePath) => spreadAttributes({src: images$3[imagePath].src, ...images$3[imagePath].attributes})
					);
				}

				const html$2 = updateImageReferences$2("<p>Here is a sample of some basic Markdown syntax that can be used when writing Markdown content in Astro.</p>\n<h2 id=\"headings\">Headings</h2>\n<p>The following HTML <code>&#x3C;h1></code>—<code>&#x3C;h6></code> elements represent six levels of section headings. <code>&#x3C;h1></code> is the highest section level while <code>&#x3C;h6></code> is the lowest.</p>\n<h1 id=\"h1\">H1</h1>\n<h2 id=\"h2\">H2</h2>\n<h3 id=\"h3\">H3</h3>\n<h4 id=\"h4\">H4</h4>\n<h5 id=\"h5\">H5</h5>\n<h6 id=\"h6\">H6</h6>\n<h2 id=\"paragraph\">Paragraph</h2>\n<p>Xerum, quo qui aut unt expliquam qui dolut labo. Aque venitatiusda cum, voluptionse latur sitiae dolessi aut parist aut dollo enim qui voluptate ma dolestendit peritin re plis aut quas inctum laceat est volestemque commosa as cus endigna tectur, offic to cor sequas etum rerum idem sintibus eiur? Quianimin porecus evelectur, cum que nis nust voloribus ratem aut omnimi, sitatur? Quiatem. Nam, omnis sum am facea corem alique molestrunt et eos evelece arcillit ut aut eos eos nus, sin conecerem erum fuga. Ri oditatquam, ad quibus unda veliamenimin cusam et facea ipsamus es exerum sitate dolores editium rerore eost, temped molorro ratiae volorro te reribus dolorer sperchicium faceata tiustia prat.</p>\n<p>Itatur? Quiatae cullecum rem ent aut odis in re eossequodi nonsequ idebis ne sapicia is sinveli squiatum, core et que aut hariosam ex eat.</p>\n<h2 id=\"images\">Images</h2>\n<h4 id=\"syntax\">Syntax</h4>\n<pre is:raw=\"\" class=\"astro-code github-dark\" style=\"background-color: #24292e; overflow-x: auto;\" tabindex=\"0\"><code><span class=\"line\"><span style=\"color: #E1E4E8\">![</span><span style=\"color: #DBEDFF; text-decoration: underline\">Alt text</span><span style=\"color: #E1E4E8\">](</span><span style=\"color: #E1E4E8; text-decoration: underline\">./full/or/relative/path/of/image</span><span style=\"color: #E1E4E8\">)</span></span></code></pre>\n<h4 id=\"output\">Output</h4>\n<p><img src=\"/blog-placeholder-about.jpg\" alt=\"blog placeholder\"></p>\n<h2 id=\"blockquotes\">Blockquotes</h2>\n<p>The blockquote element represents content that is quoted from another source, optionally with a citation which must be within a <code>footer</code> or <code>cite</code> element, and optionally with in-line changes such as annotations and abbreviations.</p>\n<h3 id=\"blockquote-without-attribution\">Blockquote without attribution</h3>\n<h4 id=\"syntax-1\">Syntax</h4>\n<pre is:raw=\"\" class=\"astro-code github-dark\" style=\"background-color: #24292e; overflow-x: auto;\" tabindex=\"0\"><code><span class=\"line\"><span style=\"color: #85E89D\">> Tiam, ad mint andaepu dandae nostion secatur sequo quae.  </span></span>\n<span class=\"line\"><span style=\"color: #85E89D\">> </span><span style=\"color: #E1E4E8; font-weight: bold\">**Note**</span><span style=\"color: #85E89D\"> that you can use </span><span style=\"color: #E1E4E8; font-style: italic\">_Markdown syntax_</span><span style=\"color: #85E89D\"> within a blockquote.</span></span></code></pre>\n<h4 id=\"output-1\">Output</h4>\n<blockquote>\n<p>Tiam, ad mint andaepu dandae nostion secatur sequo quae.<br>\n<strong>Note</strong> that you can use <em>Markdown syntax</em> within a blockquote.</p>\n</blockquote>\n<h3 id=\"blockquote-with-attribution\">Blockquote with attribution</h3>\n<h4 id=\"syntax-2\">Syntax</h4>\n<pre is:raw=\"\" class=\"astro-code github-dark\" style=\"background-color: #24292e; overflow-x: auto;\" tabindex=\"0\"><code><span class=\"line\"><span style=\"color: #85E89D\">> Don't communicate by sharing memory, share memory by communicating.&#x3C;br></span></span>\n<span class=\"line\"><span style=\"color: #85E89D\">> — &#x3C;cite>Rob Pike[</span><span style=\"color: #DBEDFF; text-decoration: underline\">^1</span><span style=\"color: #85E89D\">]&#x3C;/cite></span></span></code></pre>\n<h4 id=\"output-2\">Output</h4>\n<blockquote>\n<p>Don’t communicate by sharing memory, share memory by communicating.<br>\n— <cite>Rob Pike<sup><a href=\"#user-content-fn-1\" id=\"user-content-fnref-1\" data-footnote-ref=\"\" aria-describedby=\"footnote-label\">1</a></sup></cite></p>\n</blockquote>\n<h2 id=\"tables\">Tables</h2>\n<h4 id=\"syntax-3\">Syntax</h4>\n<pre is:raw=\"\" class=\"astro-code github-dark\" style=\"background-color: #24292e; overflow-x: auto;\" tabindex=\"0\"><code><span class=\"line\"><span style=\"color: #E1E4E8\">| Italics   | Bold     | Code   |</span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">| --------- | -------- | ------ |</span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">| </span><span style=\"color: #E1E4E8; font-style: italic\">_italics_</span><span style=\"color: #E1E4E8\"> | </span><span style=\"color: #E1E4E8; font-weight: bold\">**bold**</span><span style=\"color: #E1E4E8\"> | </span><span style=\"color: #79B8FF\">`code`</span><span style=\"color: #E1E4E8\"> |</span></span></code></pre>\n<h4 id=\"output-3\">Output</h4>\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n<table><thead><tr><th>Italics</th><th>Bold</th><th>Code</th></tr></thead><tbody><tr><td><em>italics</em></td><td><strong>bold</strong></td><td><code>code</code></td></tr></tbody></table>\n<h2 id=\"code-blocks\">Code Blocks</h2>\n<h4 id=\"syntax-4\">Syntax</h4>\n<p>we can use 3 backticks ``` in new line and write snippet and close with 3 backticks on new line and to highlight language specific syntac, write one word of language name after first 3 backticks, for eg. html, javascript, css, markdown, typescript, txt, bash</p>\n<pre is:raw=\"\" class=\"astro-code github-dark\" style=\"background-color: #24292e; overflow-x: auto;\" tabindex=\"0\"><code><span class=\"line\"><span style=\"color: #E1E4E8\">```html</span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">&#x3C;!</span><span style=\"color: #85E89D\">doctype</span><span style=\"color: #E1E4E8\"> </span><span style=\"color: #B392F0\">html</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">&#x3C;</span><span style=\"color: #85E89D\">html</span><span style=\"color: #E1E4E8\"> </span><span style=\"color: #B392F0\">lang</span><span style=\"color: #E1E4E8\">=</span><span style=\"color: #9ECBFF\">\"en\"</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">  &#x3C;</span><span style=\"color: #85E89D\">head</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">    &#x3C;</span><span style=\"color: #85E89D\">meta</span><span style=\"color: #E1E4E8\"> </span><span style=\"color: #B392F0\">charset</span><span style=\"color: #E1E4E8\">=</span><span style=\"color: #9ECBFF\">\"utf-8\"</span><span style=\"color: #E1E4E8\"> /></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">    &#x3C;</span><span style=\"color: #85E89D\">title</span><span style=\"color: #E1E4E8\">>Example HTML5 Document&#x3C;/</span><span style=\"color: #85E89D\">title</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">  &#x3C;/</span><span style=\"color: #85E89D\">head</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">  &#x3C;</span><span style=\"color: #85E89D\">body</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">    &#x3C;</span><span style=\"color: #85E89D\">p</span><span style=\"color: #E1E4E8\">>Test&#x3C;/</span><span style=\"color: #85E89D\">p</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">  &#x3C;/</span><span style=\"color: #85E89D\">body</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">&#x3C;/</span><span style=\"color: #85E89D\">html</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">```</span></span></code></pre>\n<p>Output</p>\n<pre is:raw=\"\" class=\"astro-code github-dark\" style=\"background-color: #24292e; overflow-x: auto;\" tabindex=\"0\"><code><span class=\"line\"><span style=\"color: #E1E4E8\">&#x3C;!</span><span style=\"color: #85E89D\">doctype</span><span style=\"color: #E1E4E8\"> </span><span style=\"color: #B392F0\">html</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">&#x3C;</span><span style=\"color: #85E89D\">html</span><span style=\"color: #E1E4E8\"> </span><span style=\"color: #B392F0\">lang</span><span style=\"color: #E1E4E8\">=</span><span style=\"color: #9ECBFF\">\"en\"</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">  &#x3C;</span><span style=\"color: #85E89D\">head</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">    &#x3C;</span><span style=\"color: #85E89D\">meta</span><span style=\"color: #E1E4E8\"> </span><span style=\"color: #B392F0\">charset</span><span style=\"color: #E1E4E8\">=</span><span style=\"color: #9ECBFF\">\"utf-8\"</span><span style=\"color: #E1E4E8\"> /></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">    &#x3C;</span><span style=\"color: #85E89D\">title</span><span style=\"color: #E1E4E8\">>Example HTML5 Document&#x3C;/</span><span style=\"color: #85E89D\">title</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">  &#x3C;/</span><span style=\"color: #85E89D\">head</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">  &#x3C;</span><span style=\"color: #85E89D\">body</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">    &#x3C;</span><span style=\"color: #85E89D\">p</span><span style=\"color: #E1E4E8\">>Test&#x3C;/</span><span style=\"color: #85E89D\">p</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">  &#x3C;/</span><span style=\"color: #85E89D\">body</span><span style=\"color: #E1E4E8\">></span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">&#x3C;/</span><span style=\"color: #85E89D\">html</span><span style=\"color: #E1E4E8\">></span></span></code></pre>\n<h2 id=\"list-types\">List Types</h2>\n<h3 id=\"ordered-list\">Ordered List</h3>\n<h4 id=\"syntax-5\">Syntax</h4>\n<pre is:raw=\"\" class=\"astro-code github-dark\" style=\"background-color: #24292e; overflow-x: auto;\" tabindex=\"0\"><code><span class=\"line\"><span style=\"color: #FFAB70\">1.</span><span style=\"color: #E1E4E8\"> First item</span></span>\n<span class=\"line\"><span style=\"color: #FFAB70\">2.</span><span style=\"color: #E1E4E8\"> Second item</span></span>\n<span class=\"line\"><span style=\"color: #FFAB70\">3.</span><span style=\"color: #E1E4E8\"> Third item</span></span></code></pre>\n<h4 id=\"output-4\">Output</h4>\n<ol>\n<li>First item</li>\n<li>Second item</li>\n<li>Third item</li>\n</ol>\n<h3 id=\"unordered-list\">Unordered List</h3>\n<h4 id=\"syntax-6\">Syntax</h4>\n<pre is:raw=\"\" class=\"astro-code github-dark\" style=\"background-color: #24292e; overflow-x: auto;\" tabindex=\"0\"><code><span class=\"line\"><span style=\"color: #FFAB70\">-</span><span style=\"color: #E1E4E8\"> List item</span></span>\n<span class=\"line\"><span style=\"color: #FFAB70\">-</span><span style=\"color: #E1E4E8\"> Another item</span></span>\n<span class=\"line\"><span style=\"color: #FFAB70\">-</span><span style=\"color: #E1E4E8\"> And another item</span></span></code></pre>\n<h4 id=\"output-5\">Output</h4>\n<ul>\n<li>List item</li>\n<li>Another item</li>\n<li>And another item</li>\n</ul>\n<h3 id=\"nested-list\">Nested list</h3>\n<h4 id=\"syntax-7\">Syntax</h4>\n<pre is:raw=\"\" class=\"astro-code github-dark\" style=\"background-color: #24292e; overflow-x: auto;\" tabindex=\"0\"><code><span class=\"line\"><span style=\"color: #FFAB70\">-</span><span style=\"color: #E1E4E8\"> Fruit</span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">  </span><span style=\"color: #FFAB70\">-</span><span style=\"color: #E1E4E8\"> Apple</span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">  </span><span style=\"color: #FFAB70\">-</span><span style=\"color: #E1E4E8\"> Orange</span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">  </span><span style=\"color: #FFAB70\">-</span><span style=\"color: #E1E4E8\"> Banana</span></span>\n<span class=\"line\"><span style=\"color: #FFAB70\">-</span><span style=\"color: #E1E4E8\"> Dairy</span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">  </span><span style=\"color: #FFAB70\">-</span><span style=\"color: #E1E4E8\"> Milk</span></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">  </span><span style=\"color: #FFAB70\">-</span><span style=\"color: #E1E4E8\"> Cheese</span></span></code></pre>\n<h4 id=\"output-6\">Output</h4>\n<ul>\n<li>Fruit\n<ul>\n<li>Apple</li>\n<li>Orange</li>\n<li>Banana</li>\n</ul>\n</li>\n<li>Dairy\n<ul>\n<li>Milk</li>\n<li>Cheese</li>\n</ul>\n</li>\n</ul>\n<h2 id=\"other-elements--abbr-sub-sup-kbd-mark\">Other Elements — abbr, sub, sup, kbd, mark</h2>\n<h4 id=\"syntax-8\">Syntax</h4>\n<pre is:raw=\"\" class=\"astro-code github-dark\" style=\"background-color: #24292e; overflow-x: auto;\" tabindex=\"0\"><code><span class=\"line\"><span style=\"color: #E1E4E8\">&#x3C;abbr title=\"Graphics Interchange Format\">GIF&#x3C;/abbr> is a bitmap image format.</span></span>\n<span class=\"line\"></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">H&#x3C;sub>2&#x3C;/sub>O</span></span>\n<span class=\"line\"></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">X&#x3C;sup>n&#x3C;/sup> + Y&#x3C;sup>n&#x3C;/sup> = Z&#x3C;sup>n&#x3C;/sup></span></span>\n<span class=\"line\"></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">Press &#x3C;kbd>&#x3C;kbd>CTRL&#x3C;/kbd>+&#x3C;kbd>ALT&#x3C;/kbd>+&#x3C;kbd>Delete&#x3C;/kbd>&#x3C;/kbd> to end the session.</span></span>\n<span class=\"line\"></span>\n<span class=\"line\"><span style=\"color: #E1E4E8\">Most &#x3C;mark>salamanders&#x3C;/mark> are nocturnal, and hunt for insects, worms, and other small creatures.</span></span></code></pre>\n<h4 id=\"output-7\">Output</h4>\n<p><abbr title=\"Graphics Interchange Format\">GIF</abbr> is a bitmap image format.</p>\n<p>H<sub>2</sub>O</p>\n<p>X<sup>n</sup> + Y<sup>n</sup> = Z<sup>n</sup></p>\n<p>Press <kbd><kbd>CTRL</kbd>+<kbd>ALT</kbd>+<kbd>Delete</kbd></kbd> to end the session.</p>\n<p>Most <mark>salamanders</mark> are nocturnal, and hunt for insects, worms, and other small creatures.</p>\n<section data-footnotes=\"\" class=\"footnotes\"><h2 class=\"sr-only\" id=\"footnote-label\">Footnotes</h2>\n<ol>\n<li id=\"user-content-fn-1\">\n<p>The above quote is excerpted from Rob Pike’s <a href=\"https://www.youtube.com/watch?v=PAAkCSZUG1c\">talk</a> during Gopherfest, November 18, 2015. <a href=\"#user-content-fnref-1\" data-footnote-backref=\"\" class=\"data-footnote-backref\" aria-label=\"Back to content\">↩</a></p>\n</li>\n</ol>\n</section>");

				const frontmatter$3 = {"title":"Markdown Style Guide","description":"Here is a sample of some basic Markdown syntax that can be used when writing Markdown content in Astro.","pubDate":"Jul 01 2022","heroImage":"/blog-placeholder-1.jpg"};
				const file$3 = "/Users/birdcar/Code/birdcar/blog/src/content/blog/markdown-style-guide.md";
				const url$3 = undefined;
				function rawContent$2() {
					return "\nHere is a sample of some basic Markdown syntax that can be used when writing Markdown content in Astro.\n\n## Headings\n\nThe following HTML `<h1>`—`<h6>` elements represent six levels of section headings. `<h1>` is the highest section level while `<h6>` is the lowest.\n\n# H1\n\n## H2\n\n### H3\n\n#### H4\n\n##### H5\n\n###### H6\n\n## Paragraph\n\nXerum, quo qui aut unt expliquam qui dolut labo. Aque venitatiusda cum, voluptionse latur sitiae dolessi aut parist aut dollo enim qui voluptate ma dolestendit peritin re plis aut quas inctum laceat est volestemque commosa as cus endigna tectur, offic to cor sequas etum rerum idem sintibus eiur? Quianimin porecus evelectur, cum que nis nust voloribus ratem aut omnimi, sitatur? Quiatem. Nam, omnis sum am facea corem alique molestrunt et eos evelece arcillit ut aut eos eos nus, sin conecerem erum fuga. Ri oditatquam, ad quibus unda veliamenimin cusam et facea ipsamus es exerum sitate dolores editium rerore eost, temped molorro ratiae volorro te reribus dolorer sperchicium faceata tiustia prat.\n\nItatur? Quiatae cullecum rem ent aut odis in re eossequodi nonsequ idebis ne sapicia is sinveli squiatum, core et que aut hariosam ex eat.\n\n## Images\n\n#### Syntax\n\n```markdown\n![Alt text](./full/or/relative/path/of/image)\n```\n\n#### Output\n\n![blog placeholder](/blog-placeholder-about.jpg)\n\n## Blockquotes\n\nThe blockquote element represents content that is quoted from another source, optionally with a citation which must be within a `footer` or `cite` element, and optionally with in-line changes such as annotations and abbreviations.\n\n### Blockquote without attribution\n\n#### Syntax\n\n```markdown\n> Tiam, ad mint andaepu dandae nostion secatur sequo quae.  \n> **Note** that you can use _Markdown syntax_ within a blockquote.\n```\n\n#### Output\n\n> Tiam, ad mint andaepu dandae nostion secatur sequo quae.  \n> **Note** that you can use _Markdown syntax_ within a blockquote.\n\n### Blockquote with attribution\n\n#### Syntax\n\n```markdown\n> Don't communicate by sharing memory, share memory by communicating.<br>\n> — <cite>Rob Pike[^1]</cite>\n```\n\n#### Output\n\n> Don't communicate by sharing memory, share memory by communicating.<br>\n> — <cite>Rob Pike[^1]</cite>\n\n[^1]: The above quote is excerpted from Rob Pike's [talk](https://www.youtube.com/watch?v=PAAkCSZUG1c) during Gopherfest, November 18, 2015.\n\n## Tables\n\n#### Syntax\n\n```markdown\n| Italics   | Bold     | Code   |\n| --------- | -------- | ------ |\n| _italics_ | **bold** | `code` |\n```\n\n#### Output\n\n| Italics   | Bold     | Code   |\n| --------- | -------- | ------ |\n| _italics_ | **bold** | `code` |\n\n## Code Blocks\n\n#### Syntax\n\nwe can use 3 backticks ``` in new line and write snippet and close with 3 backticks on new line and to highlight language specific syntac, write one word of language name after first 3 backticks, for eg. html, javascript, css, markdown, typescript, txt, bash\n\n````markdown\n```html\n<!doctype html>\n<html lang=\"en\">\n  <head>\n    <meta charset=\"utf-8\" />\n    <title>Example HTML5 Document</title>\n  </head>\n  <body>\n    <p>Test</p>\n  </body>\n</html>\n```\n````\n\nOutput\n\n```html\n<!doctype html>\n<html lang=\"en\">\n  <head>\n    <meta charset=\"utf-8\" />\n    <title>Example HTML5 Document</title>\n  </head>\n  <body>\n    <p>Test</p>\n  </body>\n</html>\n```\n\n## List Types\n\n### Ordered List\n\n#### Syntax\n\n```markdown\n1. First item\n2. Second item\n3. Third item\n```\n\n#### Output\n\n1. First item\n2. Second item\n3. Third item\n\n### Unordered List\n\n#### Syntax\n\n```markdown\n- List item\n- Another item\n- And another item\n```\n\n#### Output\n\n- List item\n- Another item\n- And another item\n\n### Nested list\n\n#### Syntax\n\n```markdown\n- Fruit\n  - Apple\n  - Orange\n  - Banana\n- Dairy\n  - Milk\n  - Cheese\n```\n\n#### Output\n\n- Fruit\n  - Apple\n  - Orange\n  - Banana\n- Dairy\n  - Milk\n  - Cheese\n\n## Other Elements — abbr, sub, sup, kbd, mark\n\n#### Syntax\n\n```markdown\n<abbr title=\"Graphics Interchange Format\">GIF</abbr> is a bitmap image format.\n\nH<sub>2</sub>O\n\nX<sup>n</sup> + Y<sup>n</sup> = Z<sup>n</sup>\n\nPress <kbd><kbd>CTRL</kbd>+<kbd>ALT</kbd>+<kbd>Delete</kbd></kbd> to end the session.\n\nMost <mark>salamanders</mark> are nocturnal, and hunt for insects, worms, and other small creatures.\n```\n\n#### Output\n\n<abbr title=\"Graphics Interchange Format\">GIF</abbr> is a bitmap image format.\n\nH<sub>2</sub>O\n\nX<sup>n</sup> + Y<sup>n</sup> = Z<sup>n</sup>\n\nPress <kbd><kbd>CTRL</kbd>+<kbd>ALT</kbd>+<kbd>Delete</kbd></kbd> to end the session.\n\nMost <mark>salamanders</mark> are nocturnal, and hunt for insects, worms, and other small creatures.\n";
				}
				function compiledContent$2() {
					return html$2;
				}
				function getHeadings$3() {
					return [{"depth":2,"slug":"headings","text":"Headings"},{"depth":1,"slug":"h1","text":"H1"},{"depth":2,"slug":"h2","text":"H2"},{"depth":3,"slug":"h3","text":"H3"},{"depth":4,"slug":"h4","text":"H4"},{"depth":5,"slug":"h5","text":"H5"},{"depth":6,"slug":"h6","text":"H6"},{"depth":2,"slug":"paragraph","text":"Paragraph"},{"depth":2,"slug":"images","text":"Images"},{"depth":4,"slug":"syntax","text":"Syntax"},{"depth":4,"slug":"output","text":"Output"},{"depth":2,"slug":"blockquotes","text":"Blockquotes"},{"depth":3,"slug":"blockquote-without-attribution","text":"Blockquote without attribution"},{"depth":4,"slug":"syntax-1","text":"Syntax"},{"depth":4,"slug":"output-1","text":"Output"},{"depth":3,"slug":"blockquote-with-attribution","text":"Blockquote with attribution"},{"depth":4,"slug":"syntax-2","text":"Syntax"},{"depth":4,"slug":"output-2","text":"Output"},{"depth":2,"slug":"tables","text":"Tables"},{"depth":4,"slug":"syntax-3","text":"Syntax"},{"depth":4,"slug":"output-3","text":"Output"},{"depth":2,"slug":"code-blocks","text":"Code Blocks"},{"depth":4,"slug":"syntax-4","text":"Syntax"},{"depth":2,"slug":"list-types","text":"List Types"},{"depth":3,"slug":"ordered-list","text":"Ordered List"},{"depth":4,"slug":"syntax-5","text":"Syntax"},{"depth":4,"slug":"output-4","text":"Output"},{"depth":3,"slug":"unordered-list","text":"Unordered List"},{"depth":4,"slug":"syntax-6","text":"Syntax"},{"depth":4,"slug":"output-5","text":"Output"},{"depth":3,"slug":"nested-list","text":"Nested list"},{"depth":4,"slug":"syntax-7","text":"Syntax"},{"depth":4,"slug":"output-6","text":"Output"},{"depth":2,"slug":"other-elements--abbr-sub-sup-kbd-mark","text":"Other Elements — abbr, sub, sup, kbd, mark"},{"depth":4,"slug":"syntax-8","text":"Syntax"},{"depth":4,"slug":"output-7","text":"Output"},{"depth":2,"slug":"footnote-label","text":"Footnotes"}];
				}

				const Content$3 = createComponent((result, _props, slots) => {
					const { layout, ...content } = frontmatter$3;
					content.file = file$3;
					content.url = url$3;

					return renderTemplate`${maybeRenderHead()}${unescapeHTML(html$2)}`;
				});

const __vite_glob_0_1 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  Content: Content$3,
  compiledContent: compiledContent$2,
  default: Content$3,
  file: file$3,
  frontmatter: frontmatter$3,
  getHeadings: getHeadings$3,
  images: images$3,
  rawContent: rawContent$2,
  url: url$3
}, Symbol.toStringTag, { value: 'Module' }));

const images$2 = {
					
				};

				function updateImageReferences$1(html) {
					return html.replaceAll(
						/__ASTRO_IMAGE_="([^"]+)"/gm,
						(full, imagePath) => spreadAttributes({src: images$2[imagePath].src, ...images$2[imagePath].attributes})
					);
				}

				const html$1 = updateImageReferences$1("<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Vitae ultricies leo integer malesuada nunc vel risus commodo viverra. Adipiscing enim eu turpis egestas pretium. Euismod elementum nisi quis eleifend quam adipiscing. In hac habitasse platea dictumst vestibulum. Sagittis purus sit amet volutpat. Netus et malesuada fames ac turpis egestas. Eget magna fermentum iaculis eu non diam phasellus vestibulum lorem. Varius sit amet mattis vulputate enim. Habitasse platea dictumst quisque sagittis. Integer quis auctor elit sed vulputate mi. Dictumst quisque sagittis purus sit amet.</p>\n<p>Morbi tristique senectus et netus. Id semper risus in hendrerit gravida rutrum quisque non tellus. Habitasse platea dictumst quisque sagittis purus sit amet. Tellus molestie nunc non blandit massa. Cursus vitae congue mauris rhoncus. Accumsan tortor posuere ac ut. Fringilla urna porttitor rhoncus dolor. Elit ullamcorper dignissim cras tincidunt lobortis. In cursus turpis massa tincidunt dui ut ornare lectus. Integer feugiat scelerisque varius morbi enim nunc. Bibendum neque egestas congue quisque egestas diam. Cras ornare arcu dui vivamus arcu felis bibendum. Dignissim suspendisse in est ante in nibh mauris. Sed tempus urna et pharetra pharetra massa massa ultricies mi.</p>\n<p>Mollis nunc sed id semper risus in. Convallis a cras semper auctor neque. Diam sit amet nisl suscipit. Lacus viverra vitae congue eu consequat ac felis donec. Egestas integer eget aliquet nibh praesent tristique magna sit amet. Eget magna fermentum iaculis eu non diam. In vitae turpis massa sed elementum. Tristique et egestas quis ipsum suspendisse ultrices. Eget lorem dolor sed viverra ipsum. Vel turpis nunc eget lorem dolor sed viverra. Posuere ac ut consequat semper viverra nam. Laoreet suspendisse interdum consectetur libero id faucibus. Diam phasellus vestibulum lorem sed risus ultricies tristique. Rhoncus dolor purus non enim praesent elementum facilisis. Ultrices tincidunt arcu non sodales neque. Tempus egestas sed sed risus pretium quam vulputate. Viverra suspendisse potenti nullam ac tortor vitae purus faucibus ornare. Fringilla urna porttitor rhoncus dolor purus non. Amet dictum sit amet justo donec enim.</p>\n<p>Mattis ullamcorper velit sed ullamcorper morbi tincidunt. Tortor posuere ac ut consequat semper viverra. Tellus mauris a diam maecenas sed enim ut sem viverra. Venenatis urna cursus eget nunc scelerisque viverra mauris in. Arcu ac tortor dignissim convallis aenean et tortor at. Curabitur gravida arcu ac tortor dignissim convallis aenean et tortor. Egestas tellus rutrum tellus pellentesque eu. Fusce ut placerat orci nulla pellentesque dignissim enim sit amet. Ut enim blandit volutpat maecenas volutpat blandit aliquam etiam. Id donec ultrices tincidunt arcu. Id cursus metus aliquam eleifend mi.</p>\n<p>Tempus quam pellentesque nec nam aliquam sem. Risus at ultrices mi tempus imperdiet. Id porta nibh venenatis cras sed felis eget velit. Ipsum a arcu cursus vitae. Facilisis magna etiam tempor orci eu lobortis elementum. Tincidunt dui ut ornare lectus sit. Quisque non tellus orci ac. Blandit libero volutpat sed cras. Nec tincidunt praesent semper feugiat nibh sed pulvinar proin gravida. Egestas integer eget aliquet nibh praesent tristique magna.</p>");

				const frontmatter$2 = {"title":"Second post","description":"Lorem ipsum dolor sit amet","pubDate":"Jul 22 2022","heroImage":"/blog-placeholder-4.jpg"};
				const file$2 = "/Users/birdcar/Code/birdcar/blog/src/content/blog/second-post.md";
				const url$2 = undefined;
				function rawContent$1() {
					return "\nLorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Vitae ultricies leo integer malesuada nunc vel risus commodo viverra. Adipiscing enim eu turpis egestas pretium. Euismod elementum nisi quis eleifend quam adipiscing. In hac habitasse platea dictumst vestibulum. Sagittis purus sit amet volutpat. Netus et malesuada fames ac turpis egestas. Eget magna fermentum iaculis eu non diam phasellus vestibulum lorem. Varius sit amet mattis vulputate enim. Habitasse platea dictumst quisque sagittis. Integer quis auctor elit sed vulputate mi. Dictumst quisque sagittis purus sit amet.\n\nMorbi tristique senectus et netus. Id semper risus in hendrerit gravida rutrum quisque non tellus. Habitasse platea dictumst quisque sagittis purus sit amet. Tellus molestie nunc non blandit massa. Cursus vitae congue mauris rhoncus. Accumsan tortor posuere ac ut. Fringilla urna porttitor rhoncus dolor. Elit ullamcorper dignissim cras tincidunt lobortis. In cursus turpis massa tincidunt dui ut ornare lectus. Integer feugiat scelerisque varius morbi enim nunc. Bibendum neque egestas congue quisque egestas diam. Cras ornare arcu dui vivamus arcu felis bibendum. Dignissim suspendisse in est ante in nibh mauris. Sed tempus urna et pharetra pharetra massa massa ultricies mi.\n\nMollis nunc sed id semper risus in. Convallis a cras semper auctor neque. Diam sit amet nisl suscipit. Lacus viverra vitae congue eu consequat ac felis donec. Egestas integer eget aliquet nibh praesent tristique magna sit amet. Eget magna fermentum iaculis eu non diam. In vitae turpis massa sed elementum. Tristique et egestas quis ipsum suspendisse ultrices. Eget lorem dolor sed viverra ipsum. Vel turpis nunc eget lorem dolor sed viverra. Posuere ac ut consequat semper viverra nam. Laoreet suspendisse interdum consectetur libero id faucibus. Diam phasellus vestibulum lorem sed risus ultricies tristique. Rhoncus dolor purus non enim praesent elementum facilisis. Ultrices tincidunt arcu non sodales neque. Tempus egestas sed sed risus pretium quam vulputate. Viverra suspendisse potenti nullam ac tortor vitae purus faucibus ornare. Fringilla urna porttitor rhoncus dolor purus non. Amet dictum sit amet justo donec enim.\n\nMattis ullamcorper velit sed ullamcorper morbi tincidunt. Tortor posuere ac ut consequat semper viverra. Tellus mauris a diam maecenas sed enim ut sem viverra. Venenatis urna cursus eget nunc scelerisque viverra mauris in. Arcu ac tortor dignissim convallis aenean et tortor at. Curabitur gravida arcu ac tortor dignissim convallis aenean et tortor. Egestas tellus rutrum tellus pellentesque eu. Fusce ut placerat orci nulla pellentesque dignissim enim sit amet. Ut enim blandit volutpat maecenas volutpat blandit aliquam etiam. Id donec ultrices tincidunt arcu. Id cursus metus aliquam eleifend mi.\n\nTempus quam pellentesque nec nam aliquam sem. Risus at ultrices mi tempus imperdiet. Id porta nibh venenatis cras sed felis eget velit. Ipsum a arcu cursus vitae. Facilisis magna etiam tempor orci eu lobortis elementum. Tincidunt dui ut ornare lectus sit. Quisque non tellus orci ac. Blandit libero volutpat sed cras. Nec tincidunt praesent semper feugiat nibh sed pulvinar proin gravida. Egestas integer eget aliquet nibh praesent tristique magna.\n";
				}
				function compiledContent$1() {
					return html$1;
				}
				function getHeadings$2() {
					return [];
				}

				const Content$2 = createComponent((result, _props, slots) => {
					const { layout, ...content } = frontmatter$2;
					content.file = file$2;
					content.url = url$2;

					return renderTemplate`${maybeRenderHead()}${unescapeHTML(html$1)}`;
				});

const __vite_glob_0_2 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  Content: Content$2,
  compiledContent: compiledContent$1,
  default: Content$2,
  file: file$2,
  frontmatter: frontmatter$2,
  getHeadings: getHeadings$2,
  images: images$2,
  rawContent: rawContent$1,
  url: url$2
}, Symbol.toStringTag, { value: 'Module' }));

const images$1 = {
					
				};

				function updateImageReferences(html) {
					return html.replaceAll(
						/__ASTRO_IMAGE_="([^"]+)"/gm,
						(full, imagePath) => spreadAttributes({src: images$1[imagePath].src, ...images$1[imagePath].attributes})
					);
				}

				const html = updateImageReferences("<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Vitae ultricies leo integer malesuada nunc vel risus commodo viverra. Adipiscing enim eu turpis egestas pretium. Euismod elementum nisi quis eleifend quam adipiscing. In hac habitasse platea dictumst vestibulum. Sagittis purus sit amet volutpat. Netus et malesuada fames ac turpis egestas. Eget magna fermentum iaculis eu non diam phasellus vestibulum lorem. Varius sit amet mattis vulputate enim. Habitasse platea dictumst quisque sagittis. Integer quis auctor elit sed vulputate mi. Dictumst quisque sagittis purus sit amet.</p>\n<p>Morbi tristique senectus et netus. Id semper risus in hendrerit gravida rutrum quisque non tellus. Habitasse platea dictumst quisque sagittis purus sit amet. Tellus molestie nunc non blandit massa. Cursus vitae congue mauris rhoncus. Accumsan tortor posuere ac ut. Fringilla urna porttitor rhoncus dolor. Elit ullamcorper dignissim cras tincidunt lobortis. In cursus turpis massa tincidunt dui ut ornare lectus. Integer feugiat scelerisque varius morbi enim nunc. Bibendum neque egestas congue quisque egestas diam. Cras ornare arcu dui vivamus arcu felis bibendum. Dignissim suspendisse in est ante in nibh mauris. Sed tempus urna et pharetra pharetra massa massa ultricies mi.</p>\n<p>Mollis nunc sed id semper risus in. Convallis a cras semper auctor neque. Diam sit amet nisl suscipit. Lacus viverra vitae congue eu consequat ac felis donec. Egestas integer eget aliquet nibh praesent tristique magna sit amet. Eget magna fermentum iaculis eu non diam. In vitae turpis massa sed elementum. Tristique et egestas quis ipsum suspendisse ultrices. Eget lorem dolor sed viverra ipsum. Vel turpis nunc eget lorem dolor sed viverra. Posuere ac ut consequat semper viverra nam. Laoreet suspendisse interdum consectetur libero id faucibus. Diam phasellus vestibulum lorem sed risus ultricies tristique. Rhoncus dolor purus non enim praesent elementum facilisis. Ultrices tincidunt arcu non sodales neque. Tempus egestas sed sed risus pretium quam vulputate. Viverra suspendisse potenti nullam ac tortor vitae purus faucibus ornare. Fringilla urna porttitor rhoncus dolor purus non. Amet dictum sit amet justo donec enim.</p>\n<p>Mattis ullamcorper velit sed ullamcorper morbi tincidunt. Tortor posuere ac ut consequat semper viverra. Tellus mauris a diam maecenas sed enim ut sem viverra. Venenatis urna cursus eget nunc scelerisque viverra mauris in. Arcu ac tortor dignissim convallis aenean et tortor at. Curabitur gravida arcu ac tortor dignissim convallis aenean et tortor. Egestas tellus rutrum tellus pellentesque eu. Fusce ut placerat orci nulla pellentesque dignissim enim sit amet. Ut enim blandit volutpat maecenas volutpat blandit aliquam etiam. Id donec ultrices tincidunt arcu. Id cursus metus aliquam eleifend mi.</p>\n<p>Tempus quam pellentesque nec nam aliquam sem. Risus at ultrices mi tempus imperdiet. Id porta nibh venenatis cras sed felis eget velit. Ipsum a arcu cursus vitae. Facilisis magna etiam tempor orci eu lobortis elementum. Tincidunt dui ut ornare lectus sit. Quisque non tellus orci ac. Blandit libero volutpat sed cras. Nec tincidunt praesent semper feugiat nibh sed pulvinar proin gravida. Egestas integer eget aliquet nibh praesent tristique magna.</p>");

				const frontmatter$1 = {"title":"Third post","description":"Lorem ipsum dolor sit amet","pubDate":"Jul 15 2022","heroImage":"/blog-placeholder-2.jpg"};
				const file$1 = "/Users/birdcar/Code/birdcar/blog/src/content/blog/third-post.md";
				const url$1 = undefined;
				function rawContent() {
					return "\nLorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Vitae ultricies leo integer malesuada nunc vel risus commodo viverra. Adipiscing enim eu turpis egestas pretium. Euismod elementum nisi quis eleifend quam adipiscing. In hac habitasse platea dictumst vestibulum. Sagittis purus sit amet volutpat. Netus et malesuada fames ac turpis egestas. Eget magna fermentum iaculis eu non diam phasellus vestibulum lorem. Varius sit amet mattis vulputate enim. Habitasse platea dictumst quisque sagittis. Integer quis auctor elit sed vulputate mi. Dictumst quisque sagittis purus sit amet.\n\nMorbi tristique senectus et netus. Id semper risus in hendrerit gravida rutrum quisque non tellus. Habitasse platea dictumst quisque sagittis purus sit amet. Tellus molestie nunc non blandit massa. Cursus vitae congue mauris rhoncus. Accumsan tortor posuere ac ut. Fringilla urna porttitor rhoncus dolor. Elit ullamcorper dignissim cras tincidunt lobortis. In cursus turpis massa tincidunt dui ut ornare lectus. Integer feugiat scelerisque varius morbi enim nunc. Bibendum neque egestas congue quisque egestas diam. Cras ornare arcu dui vivamus arcu felis bibendum. Dignissim suspendisse in est ante in nibh mauris. Sed tempus urna et pharetra pharetra massa massa ultricies mi.\n\nMollis nunc sed id semper risus in. Convallis a cras semper auctor neque. Diam sit amet nisl suscipit. Lacus viverra vitae congue eu consequat ac felis donec. Egestas integer eget aliquet nibh praesent tristique magna sit amet. Eget magna fermentum iaculis eu non diam. In vitae turpis massa sed elementum. Tristique et egestas quis ipsum suspendisse ultrices. Eget lorem dolor sed viverra ipsum. Vel turpis nunc eget lorem dolor sed viverra. Posuere ac ut consequat semper viverra nam. Laoreet suspendisse interdum consectetur libero id faucibus. Diam phasellus vestibulum lorem sed risus ultricies tristique. Rhoncus dolor purus non enim praesent elementum facilisis. Ultrices tincidunt arcu non sodales neque. Tempus egestas sed sed risus pretium quam vulputate. Viverra suspendisse potenti nullam ac tortor vitae purus faucibus ornare. Fringilla urna porttitor rhoncus dolor purus non. Amet dictum sit amet justo donec enim.\n\nMattis ullamcorper velit sed ullamcorper morbi tincidunt. Tortor posuere ac ut consequat semper viverra. Tellus mauris a diam maecenas sed enim ut sem viverra. Venenatis urna cursus eget nunc scelerisque viverra mauris in. Arcu ac tortor dignissim convallis aenean et tortor at. Curabitur gravida arcu ac tortor dignissim convallis aenean et tortor. Egestas tellus rutrum tellus pellentesque eu. Fusce ut placerat orci nulla pellentesque dignissim enim sit amet. Ut enim blandit volutpat maecenas volutpat blandit aliquam etiam. Id donec ultrices tincidunt arcu. Id cursus metus aliquam eleifend mi.\n\nTempus quam pellentesque nec nam aliquam sem. Risus at ultrices mi tempus imperdiet. Id porta nibh venenatis cras sed felis eget velit. Ipsum a arcu cursus vitae. Facilisis magna etiam tempor orci eu lobortis elementum. Tincidunt dui ut ornare lectus sit. Quisque non tellus orci ac. Blandit libero volutpat sed cras. Nec tincidunt praesent semper feugiat nibh sed pulvinar proin gravida. Egestas integer eget aliquet nibh praesent tristique magna.\n";
				}
				function compiledContent() {
					return html;
				}
				function getHeadings$1() {
					return [];
				}

				const Content$1 = createComponent((result, _props, slots) => {
					const { layout, ...content } = frontmatter$1;
					content.file = file$1;
					content.url = url$1;

					return renderTemplate`${maybeRenderHead()}${unescapeHTML(html)}`;
				});

const __vite_glob_0_3 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  Content: Content$1,
  compiledContent,
  default: Content$1,
  file: file$1,
  frontmatter: frontmatter$1,
  getHeadings: getHeadings$1,
  images: images$1,
  rawContent,
  url: url$1
}, Symbol.toStringTag, { value: 'Module' }));

const frontmatter = {
  "title": "Using MDX",
  "description": "Lorem ipsum dolor sit amet",
  "pubDate": "Jul 02 2022",
  "heroImage": "/blog-placeholder-5.jpg"
};
function getHeadings() {
  return [{
    "depth": 2,
    "slug": "why-mdx",
    "text": "Why MDX?"
  }, {
    "depth": 2,
    "slug": "example",
    "text": "Example"
  }, {
    "depth": 2,
    "slug": "more-links",
    "text": "More Links"
  }];
}
function _createMdxContent(props) {
  const _components = Object.assign({
    p: "p",
    a: "a",
    code: "code",
    h2: "h2",
    br: "br",
    ul: "ul",
    li: "li",
    strong: "strong"
  }, props.components);
  return createVNode(Fragment, {
    children: [createVNode(_components.p, {
      children: ["This theme comes with the ", createVNode(_components.a, {
        href: "https://docs.astro.build/en/guides/integrations-guide/mdx/",
        children: "@astrojs/mdx"
      }), " integration installed and configured in your ", createVNode(_components.code, {
        children: "astro.config.mjs"
      }), " config file. If you prefer not to use MDX, you can disable support by removing the integration from your config file."]
    }), "\n", createVNode(_components.h2, {
      id: "why-mdx",
      children: "Why MDX?"
    }), "\n", createVNode(_components.p, {
      children: ["MDX is a special flavor of Markdown that supports embedded JavaScript & JSX syntax. This unlocks the ability to ", createVNode(_components.a, {
        href: "https://docs.astro.build/en/guides/markdown-content/#mdx-features",
        children: "mix JavaScript and UI Components into your Markdown content"
      }), " for things like interactive charts or alerts."]
    }), "\n", createVNode(_components.p, {
      children: "If you have existing content authored in MDX, this integration will hopefully make migrating to Astro a breeze."
    }), "\n", createVNode(_components.h2, {
      id: "example",
      children: "Example"
    }), "\n", createVNode(_components.p, {
      children: ["Here is how you import and use a UI component inside of MDX.", createVNode(_components.br, {}), "\nWhen you open this page in the browser, you should see the clickable button below."]
    }), "\n", "\n", createVNode($$HeaderLink, {
      href: "#",
      onclick: "alert('clicked!')",
      children: createVNode(_components.p, {
        children: "Embedded component in MDX"
      })
    }), "\n", createVNode(_components.h2, {
      id: "more-links",
      children: "More Links"
    }), "\n", createVNode(_components.ul, {
      children: ["\n", createVNode(_components.li, {
        children: createVNode(_components.a, {
          href: "https://mdxjs.com/docs/what-is-mdx",
          children: "MDX Syntax Documentation"
        })
      }), "\n", createVNode(_components.li, {
        children: createVNode(_components.a, {
          href: "https://docs.astro.build/en/guides/markdown-content/#markdown-and-mdx-pages",
          children: "Astro Usage Documentation"
        })
      }), "\n", createVNode(_components.li, {
        children: [createVNode(_components.strong, {
          children: "Note:"
        }), " ", createVNode(_components.a, {
          href: "https://docs.astro.build/en/reference/directives-reference/#client-directives",
          children: "Client Directives"
        }), " are still required to create interactive components. Otherwise, all components in your MDX will render as static HTML (no JavaScript) by default."]
      }), "\n"]
    })]
  });
}
function MDXContent(props = {}) {
  const {
    wrapper: MDXLayout
  } = props.components || {};
  return MDXLayout ? createVNode(MDXLayout, {
    ...props,
    children: createVNode(_createMdxContent, {
      ...props
    })
  }) : _createMdxContent(props);
}

__astro_tag_component__(getHeadings, "astro:jsx");
__astro_tag_component__(MDXContent, "astro:jsx");
const url = "src/content/blog/using-mdx.mdx";
const file = "/Users/birdcar/Code/birdcar/blog/src/content/blog/using-mdx.mdx";
const Content = (props = {}) => MDXContent({
											...props,
											components: { Fragment, ...props.components },
										});
Content[Symbol.for('mdx-component')] = true;
Content[Symbol.for('astro.needsHeadRendering')] = !Boolean(frontmatter.layout);
Content.moduleId = "/Users/birdcar/Code/birdcar/blog/src/content/blog/using-mdx.mdx";

const __vite_glob_0_4 = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  Content,
  default: Content,
  file,
  frontmatter,
  getHeadings,
  url
}, Symbol.toStringTag, { value: 'Module' }));

var __classPrivateFieldGet = (globalThis && globalThis.__classPrivateFieldGet) || function (receiver, state, kind, f) {
    if (kind === "a" && !f) throw new TypeError("Private accessor was defined without a getter");
    if (typeof state === "function" ? receiver !== state || !f : !state.has(receiver)) throw new TypeError("Cannot read private member from an object whose class did not declare it");
    return kind === "m" ? f : kind === "a" ? f.call(receiver) : f ? f.value : state.get(receiver);
};
var __classPrivateFieldSet = (globalThis && globalThis.__classPrivateFieldSet) || function (receiver, state, value, kind, f) {
    if (kind === "m") throw new TypeError("Private method is not writable");
    if (kind === "a" && !f) throw new TypeError("Private accessor was defined without a setter");
    if (typeof state === "function" ? receiver !== state || !f : !state.has(receiver)) throw new TypeError("Cannot write private member to an object whose class did not declare it");
    return (kind === "a" ? f.call(receiver, value) : f ? f.value = value : state.set(receiver, value)), value;
};
var _FontManager_instances, _FontManager_cache, _FontManager_loading, _FontManager_manager, _FontManager_updateManager;
const { resolve } = createRequire(import.meta.url);
const debug = (...args) => console.debug('[astro-og-canvas]', ...args);
const error = (...args) => console.error('[astro-og-canvas]', ...args);
/** CanvasKit singleton. */
const CanvasKitPromise = init({
    // TODO: Figure how to reliably resolve this without depending on Node.
    locateFile: (file) => resolve(`canvaskit-wasm/bin/${file}`),
});
class FontManager {
    constructor() {
        _FontManager_instances.add(this);
        /** Font data cache to avoid repeat downloads. */
        _FontManager_cache.set(this, new Map());
        /** Promise to co-ordinate `#get` calls to run sequentially. */
        _FontManager_loading.set(this, Promise.resolve());
        /** Current `CanvasKit.FontMgr` instance. */
        _FontManager_manager.set(this, void 0);
    }
    /**
     * Get a font manager instance for the provided fonts.
     *
     * Fonts are backed by an in-memory cache, so fonts are only downloaded once.
     *
     * Tries to avoid repeated instantiation of `CanvasKit.FontMgr` due to a memory leak
     * in their implementation. Will only reinstantiate if it sees a new font in the
     * `fontUrls` array.
     *
     * @param fontUrls Array of URLs to remote font files (TTF recommended).
     * @returns A font manager for all fonts loaded up until now.
     */
    async get(fontUrls) {
        await __classPrivateFieldGet(this, _FontManager_loading, "f");
        let hasNew = false;
        __classPrivateFieldSet(this, _FontManager_loading, new Promise(async (resolve) => {
            for (const url of fontUrls) {
                if (__classPrivateFieldGet(this, _FontManager_cache, "f").has(url))
                    continue;
                hasNew = true;
                debug('Downloading', url);
                const response = await fetch(url);
                if (response.ok) {
                    __classPrivateFieldGet(this, _FontManager_cache, "f").set(url, await response.arrayBuffer());
                }
                else {
                    __classPrivateFieldGet(this, _FontManager_cache, "f").set(url, undefined);
                    error(response.status, response.statusText, '—', url);
                }
            }
            resolve();
        }), "f");
        await __classPrivateFieldGet(this, _FontManager_loading, "f");
        if (hasNew)
            await __classPrivateFieldGet(this, _FontManager_instances, "m", _FontManager_updateManager).call(this);
        return __classPrivateFieldGet(this, _FontManager_manager, "f");
    }
}
_FontManager_cache = new WeakMap(), _FontManager_loading = new WeakMap(), _FontManager_manager = new WeakMap(), _FontManager_instances = new WeakSet(), _FontManager_updateManager = 
/** Instantiate a new `CanvasKit.FontMgr` instance with all the currently cached fonts. */
async function _FontManager_updateManager() {
    const CanvasKit = await CanvasKitPromise;
    const fontData = Array.from(__classPrivateFieldGet(this, _FontManager_cache, "f").values()).filter((v) => !!v);
    __classPrivateFieldSet(this, _FontManager_manager, CanvasKit.FontMgr.FromData(...fontData), "f");
    // Log to the terminal which font families have been loaded.
    // Mostly useful so users can see the name of families as parsed by CanvasKit.
    const fontCount = __classPrivateFieldGet(this, _FontManager_manager, "f").countFamilies();
    const fontFamilies = [];
    for (let i = 0; i < fontCount; i++)
        fontFamilies.push(__classPrivateFieldGet(this, _FontManager_manager, "f").getFamilyName(i));
    debug('Loaded', fontCount, 'font families:\n' + fontFamilies.join(', '));
};
const fontManager = new FontManager();
const images = { cache: new Map(), loading: Promise.resolve() };
/**
 * Load an image. Backed by an in-memory cache to avoid repeat disk-reads.
 * @param path Path to an image file, e.g. `./src/logo.png`.
 * @returns Buffer containing the image contents.
 */
const loadImage = async (path) => {
    await images.loading;
    let image;
    images.loading = new Promise(async (resolve) => {
        const cached = images.cache.get(path);
        if (cached) {
            image = cached;
        }
        else {
            // TODO: Figure out if there’s deno-compatible way to load images.
            image = await fs.readFile(path);
            images.cache.set(path, image);
        }
        resolve();
    });
    await images.loading;
    return image;
};
async function downloadImage(url) {
    const response = await axios.get(url, { responseType: 'arraybuffer' });
    return Buffer.from(response.data, "utf-8");
}

const [width, height] = [1200, 630];
const edges = {
    top: [0, 0, width, 0],
    bottom: [0, height, width, height],
    left: [0, 0, 0, height],
    right: [width, 0, width, height],
};
const defaults = {
    border: {
        color: [255, 255, 255],
        width: 0,
        side: 'inline-start',
    },
    font: {
        title: {
            color: [255, 255, 255],
            size: 70,
            lineHeight: 1,
            weight: 'Normal',
            families: ['Noto Sans'],
        },
        description: {
            color: [255, 255, 255],
            size: 40,
            lineHeight: 1.3,
            weight: 'Normal',
            families: ['Noto Sans'],
        },
    },
};
async function generateOpenGraphImage({ title, description = '', dir = 'ltr', backgroundImage, bgGradient = [[0, 0, 0]], border: borderConfig = {}, padding = 60, logo, font: fontConfig = {}, fonts = ['https://api.fontsource.org/v1/fonts/noto-sans/latin-400-normal.ttf'], format = 'PNG', quality = 90, }) {
    const border = { ...defaults.border, ...borderConfig };
    const font = {
        title: { ...defaults.font.title, ...fontConfig.title },
        description: { ...defaults.font.description, ...fontConfig.description },
    };
    const isRtl = dir === 'rtl';
    const margin = {
        'block-start': padding,
        'block-end': padding,
        'inline-start': padding,
        'inline-end': padding,
    };
    margin[border.side] += border.width;
    const CanvasKit = await CanvasKitPromise;
    const textStyle = (fontConfig) => ({
        color: CanvasKit.Color(...fontConfig.color),
        fontFamilies: fontConfig.families,
        fontSize: fontConfig.size,
        fontStyle: { weight: CanvasKit.FontWeight[fontConfig.weight] },
        heightMultiplier: fontConfig.lineHeight,
    });
    // Set up.
    const surface = CanvasKit.MakeSurface(width, height);
    const canvas = surface.getCanvas();
    // Draw background gradient.
    const bgRect = CanvasKit.XYWHRect(0, 0, width, height);
    const bgPaint = new CanvasKit.Paint();
    bgPaint.setShader(CanvasKit.Shader.MakeLinearGradient([0, 0], [0, height], bgGradient.map((rgb) => CanvasKit.Color(...rgb)), null, CanvasKit.TileMode.Clamp));
    canvas.drawRect(bgRect, bgPaint);
    // Draw bg image
    if (backgroundImage?.url) {
        // Download image
        console.log(backgroundImage.url);
        const imgBuf = await downloadImage(backgroundImage.url);
        const resizedImgBuffer = await sharp(imgBuf)
            .resize({
            width: width,
            height: height,
            fit: sharp.fit.cover,
            position: sharp.strategy.entropy
        }).toBuffer();
        const img = CanvasKit.MakeImageFromEncoded(resizedImgBuffer);
        if (img) {
            // Matrix transform to scale the bg to the desired size.
            let imagePaint = new CanvasKit.Paint();
            imagePaint.setImageFilter(CanvasKit.ImageFilter.MakeBlur(backgroundImage.blurStrength ?? 0, backgroundImage.blurStrength ?? 0, CanvasKit.TileMode.Repeat, null));
            imagePaint.setAlphaf(backgroundImage.alpha ?? 1);
            canvas.drawImage(img, 0, 0, imagePaint);
        }
    }
    // Draw border.
    if (border.width) {
        const borderStyle = new CanvasKit.Paint();
        borderStyle.setStyle(CanvasKit.PaintStyle.Stroke);
        borderStyle.setColor(CanvasKit.Color(...border.color));
        borderStyle.setStrokeWidth(border.width * 2);
        const borders = {
            'block-start': edges.top,
            'block-end': edges.bottom,
            'inline-start': isRtl ? edges.right : edges.left,
            'inline-end': isRtl ? edges.left : edges.right,
        };
        canvas.drawLine(...borders[border.side], borderStyle);
    }
    // Draw logo.
    let logoHeight = 0;
    if (logo) {
        const imgBuf = await loadImage(logo.path);
        const img = CanvasKit.MakeImageFromEncoded(imgBuf);
        if (img) {
            const logoH = img.height();
            const logoW = img.width();
            const targetW = logo.size?.[0] ?? logoW;
            const targetH = logo.size?.[1] ?? (targetW / logoW) * logoH;
            const xRatio = targetW / logoW;
            const yRatio = targetH / logoH;
            logoHeight = targetH;
            // Matrix transform to scale the logo to the desired size.
            const imagePaint = new CanvasKit.Paint();
            imagePaint.setImageFilter(CanvasKit.ImageFilter.MakeMatrixTransform(CanvasKit.Matrix.scaled(xRatio, yRatio), { filter: CanvasKit.FilterMode.Linear }, null));
            const imageLeft = isRtl
                ? (1 / xRatio) * (width - margin['inline-start']) - logoW
                : (1 / xRatio) * margin['inline-start'];
            canvas.drawImage(img, imageLeft, (1 / yRatio) * margin['block-start'], imagePaint);
        }
    }
    // Load and configure font families.
    const fontMgr = await fontManager.get(fonts);
    if (fontMgr) {
        // Create paragraph with initial styles and add title.
        const paragraphStyle = new CanvasKit.ParagraphStyle({
            textAlign: isRtl ? CanvasKit.TextAlign.Right : CanvasKit.TextAlign.Left,
            textStyle: textStyle(font.title),
            textDirection: isRtl ? CanvasKit.TextDirection.RTL : CanvasKit.TextDirection.LTR,
        });
        const paragraphBuilder = CanvasKit.ParagraphBuilder.Make(paragraphStyle, fontMgr);
        paragraphBuilder.addText(decodeHTMLStrict(title));
        // Add small empty line betwen title & description.
        paragraphBuilder.pushStyle(new CanvasKit.TextStyle({ fontSize: padding / 3, heightMultiplier: 1 }));
        paragraphBuilder.addText('\n\n');
        // Add description.
        paragraphBuilder.pushStyle(new CanvasKit.TextStyle(textStyle(font.description)));
        paragraphBuilder.addText(decodeHTMLStrict(description));
        // Draw paragraph to canvas.
        const para = paragraphBuilder.build();
        const paraWidth = width - margin['inline-start'] - margin['inline-end'] - padding;
        para.layout(paraWidth);
        const paraLeft = isRtl
            ? width - margin['inline-start'] - para.getMaxWidth()
            : margin['inline-start'];
        const minTop = margin['block-start'] + logoHeight + (logoHeight ? padding : 0);
        const maxTop = minTop + (logoHeight ? padding : 0);
        const naturalTop = height - margin['block-end'] - para.getHeight();
        const paraTop = Math.max(minTop, Math.min(maxTop, naturalTop));
        canvas.drawParagraph(para, paraLeft, paraTop);
    }
    // Render canvas to a buffer.
    const image = surface.makeImageSnapshot();
    const imageBytes = image.encodeToBytes(CanvasKit.ImageFormat[format], quality) || new Uint8Array();
    // Free any memory our surface might be hanging onto.
    surface.dispose();
    return Buffer.from(imageBytes);
}

const pathToSlug = (path) => {
    path = path.replace(/^\/src\/pages\//, '');
    path = path.replace(/^\/src\/content\//, '');
    path = path.replace(/\.[^\.]*$/, '') + '.png';
    path = path.replace(/\/index\.png$/, '.png');
    return path;
};
const slugFromCollection = (path) => {
    path = path.replace(/^\/src\/content\/articles\//, '');
    path = path.replace(/\.[^\.]*$/, '') + '';
    return path;
};
const addCollectionNameAndImageExtension = (path) => {
    return "articles/" + path + ".png";
};
function makeGetStaticPaths({ pages, collection, param, getSlug = pathToSlug, getRawSlug = slugFromCollection, addCollectionAndExtension = addCollectionNameAndImageExtension, }) {
    let slugs = Object.entries(pages).map((page) => getSlug(...page));
    // Filter only slugs in collection
    if (collection) {
        const slugsRaw = Object.entries(pages).map((page) => getRawSlug(...page));
        const slugsFiltered = slugsRaw.filter(slug => collection.some(entry => entry.slug === slug));
        slugs = slugsFiltered.map((slug) => {
            return addCollectionAndExtension(slug);
        });
    }
    const paths = slugs.map((slug) => ({ params: { [param]: slug } }));
    return function getStaticPaths() {
        return paths;
    };
}
function createOGImageEndpoint({ getSlug = pathToSlug, ...opts }) {
    return async function getOGImage({ params }) {
        const pageEntry = Object.entries(opts.pages).find((page) => getSlug(...page) === params[opts.param]);
        if (!pageEntry)
            return new Response('Page not found', { status: 404 });
        return {
            body: (await generateOpenGraphImage(await opts.getImageOptions(...pageEntry))),
        };
    };
}
function OGDynamicImageRoute(opts) {
    return {
        getStaticPaths: makeGetStaticPaths(opts),
        get: createOGImageEndpoint(opts),
    };
}

const nonDraftFreshArticlesEntries = await getCollection(
  "blog",
  ({ data }) => {
    const currentDate = /* @__PURE__ */ new Date();
    const monthNumber = currentDate.getMonth();
    if (data.pubDate?.getMonth() == monthNumber) {
      return data.published;
    }
  }
);
const { getStaticPaths, get } = OGDynamicImageRoute({
  // Tell us the name of your dynamic route segment.
  // In this case it's `route`, because the file is named `[...route].ts`.
  param: "route",
  // (Optional) CollectionEntries (array of content collection)
  // If you want generate only filtered posts from collection
  collection: nonDraftFreshArticlesEntries,
  // A collection of pages to generate images for.
  // This can be any map of paths to data, not necessarily a glob result.
  pages: await /* #__PURE__ */ Object.assign({"/src/content/blog/first-post.md": __vite_glob_0_0,"/src/content/blog/markdown-style-guide.md": __vite_glob_0_1,"/src/content/blog/second-post.md": __vite_glob_0_2,"/src/content/blog/third-post.md": __vite_glob_0_3,"/src/content/blog/using-mdx.mdx": __vite_glob_0_4

}),
  // For each page, this callback will be used to customize the OpenGraph
  // image. For example, if `pages` was passed a glob like above, you
  // could read values from frontmatter.
  getImageOptions: (path, page) => ({
    title: page.frontmatter.title,
    description: page.frontmatter.description,
    logo: {
      path: "./src/favicon.png"
    },
    backgroundImage: {
      url: page.frontmatter.image?.url,
      alpha: 0.2,
      blurStrength: 3
    },
    // There are a bunch more options you can use here!
    bgGradient: [
      [26, 26, 26],
      [24, 24, 24]
    ],
    /** Border config. Highlights a single edge of the image. */
    border: {
      /** RGB border color, e.g. `[0, 255, 0]`. */
      color: [76, 0, 153],
      /** Border width. Default: `0`. */
      width: 15,
      /** Side of the image to draw the border on. Inline start/end respects writing direction. */
      side: "block-end"
    },
    font: {
      title: {
        families: [
          "Open Sans",
          "Ubuntu",
          "Istok Web",
          "Source Sans Pro",
          "PT Serif",
          "Andika"
        ]
      },
      description: {
        families: [
          "Open Sans",
          "Ubuntu",
          "Istok Web",
          "Source Sans Pro",
          "PT Serif",
          "Andika"
        ]
      }
    }
  })
});

const ____route_ = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
  __proto__: null,
  get,
  getStaticPaths
}, Symbol.toStringTag, { value: 'Module' }));

export { ____slug_ as _, about as a, index as b, __vite_glob_0_0 as c, __vite_glob_0_1 as d, __vite_glob_0_2 as e, __vite_glob_0_3 as f, __vite_glob_0_4 as g, ____route_ as h, index$1 as i, rss_xml as r };
