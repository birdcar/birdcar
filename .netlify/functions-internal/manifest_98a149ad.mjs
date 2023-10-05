import 'cookie';
import 'kleur/colors';
import 'string-width';
import './chunks/astro_c3305d12.mjs';
import 'clsx';
import 'mime';
import { compile } from 'path-to-regexp';
import 'html-escaper';

if (typeof process !== "undefined") {
  let proc = process;
  if ("argv" in proc && Array.isArray(proc.argv)) {
    if (proc.argv.includes("--verbose")) ; else if (proc.argv.includes("--silent")) ; else ;
  }
}

new TextEncoder();

function getRouteGenerator(segments, addTrailingSlash) {
  const template = segments.map((segment) => {
    return "/" + segment.map((part) => {
      if (part.spread) {
        return `:${part.content.slice(3)}(.*)?`;
      } else if (part.dynamic) {
        return `:${part.content}`;
      } else {
        return part.content.normalize().replace(/\?/g, "%3F").replace(/#/g, "%23").replace(/%5B/g, "[").replace(/%5D/g, "]").replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      }
    }).join("");
  }).join("");
  let trailing = "";
  if (addTrailingSlash === "always" && segments.length) {
    trailing = "/";
  }
  const toPath = compile(template + trailing);
  return toPath;
}

function deserializeRouteData(rawRouteData) {
  return {
    route: rawRouteData.route,
    type: rawRouteData.type,
    pattern: new RegExp(rawRouteData.pattern),
    params: rawRouteData.params,
    component: rawRouteData.component,
    generate: getRouteGenerator(rawRouteData.segments, rawRouteData._meta.trailingSlash),
    pathname: rawRouteData.pathname || void 0,
    segments: rawRouteData.segments,
    prerender: rawRouteData.prerender,
    redirect: rawRouteData.redirect,
    redirectRoute: rawRouteData.redirectRoute ? deserializeRouteData(rawRouteData.redirectRoute) : void 0
  };
}

function deserializeManifest(serializedManifest) {
  const routes = [];
  for (const serializedRoute of serializedManifest.routes) {
    routes.push({
      ...serializedRoute,
      routeData: deserializeRouteData(serializedRoute.routeData)
    });
    const route = serializedRoute;
    route.routeData = deserializeRouteData(serializedRoute.routeData);
  }
  const assets = new Set(serializedManifest.assets);
  const componentMetadata = new Map(serializedManifest.componentMetadata);
  const clientDirectives = new Map(serializedManifest.clientDirectives);
  return {
    ...serializedManifest,
    assets,
    componentMetadata,
    clientDirectives,
    routes
  };
}

const manifest = deserializeManifest({"adapterName":"@astrojs/netlify/functions","routes":[{"file":"index.html","links":[],"scripts":[],"styles":[],"routeData":{"route":"/","type":"page","pattern":"^\\/$","segments":[],"params":[],"component":"src/pages/index.astro","pathname":"/","prerender":true,"_meta":{"trailingSlash":"ignore"}}},{"file":"rss.xml","links":[],"scripts":[],"styles":[],"routeData":{"route":"/rss.xml","type":"endpoint","pattern":"^\\/rss\\.xml$","segments":[[{"content":"rss.xml","dynamic":false,"spread":false}]],"params":[],"component":"src/pages/rss.xml.js","pathname":"/rss.xml","prerender":true,"_meta":{"trailingSlash":"ignore"}}},{"file":"blog/index.html","links":[],"scripts":[],"styles":[],"routeData":{"route":"/blog","type":"page","pattern":"^\\/blog\\/?$","segments":[[{"content":"blog","dynamic":false,"spread":false}]],"params":[],"component":"src/pages/blog/index.astro","pathname":"/blog","prerender":true,"_meta":{"trailingSlash":"ignore"}}},{"file":"uses/index.html","links":[],"scripts":[],"styles":[],"routeData":{"route":"/uses","type":"page","pattern":"^\\/uses\\/?$","segments":[[{"content":"uses","dynamic":false,"spread":false}]],"params":[],"component":"src/pages/uses.astro","pathname":"/uses","prerender":true,"_meta":{"trailingSlash":"ignore"}}},{"file":"","links":[],"scripts":[{"type":"external","value":"/_astro/page.3736b48a.js"},{"stage":"head-inline","children":"!(function(w,p,f,c){if(!window.crossOriginIsolated && !navigator.serviceWorker) return;c=w[p]=Object.assign(w[p]||{},{\"lib\":\"/~partytown/\",\"debug\":false});c[f]=(c[f]||[])})(window,'partytown','forward');/* Partytown 0.8.1 - MIT builder.io */\n!function(t,e,n,i,o,r,a,s,d,c,l,p){function u(){p||(p=1,\"/\"==(a=(r.lib||\"/~partytown/\")+(r.debug?\"debug/\":\"\"))[0]&&(d=e.querySelectorAll('script[type=\"text/partytown\"]'),i!=t?i.dispatchEvent(new CustomEvent(\"pt1\",{detail:t})):(s=setTimeout(f,1e4),e.addEventListener(\"pt0\",w),o?h(1):n.serviceWorker?n.serviceWorker.register(a+(r.swPath||\"partytown-sw.js\"),{scope:a}).then((function(t){t.active?h():t.installing&&t.installing.addEventListener(\"statechange\",(function(t){\"activated\"==t.target.state&&h()}))}),console.error):f())))}function h(t){c=e.createElement(t?\"script\":\"iframe\"),t||(c.style.display=\"block\",c.style.width=\"0\",c.style.height=\"0\",c.style.border=\"0\",c.style.visibility=\"hidden\",c.setAttribute(\"aria-hidden\",!0)),c.src=a+\"partytown-\"+(t?\"atomics.js?v=0.8.1\":\"sandbox-sw.html?\"+Date.now()),e.querySelector(r.sandboxParent||\"body\").appendChild(c)}function f(n,o){for(w(),i==t&&(r.forward||[]).map((function(e){delete t[e.split(\".\")[0]]})),n=0;n<d.length;n++)(o=e.createElement(\"script\")).innerHTML=d[n].innerHTML,o.nonce=r.nonce,e.head.appendChild(o);c&&c.parentNode.removeChild(c)}function w(){clearTimeout(s)}r=t.partytown||{},i==t&&(r.forward||[]).map((function(e){l=t,e.split(\".\").map((function(e,n,i){l=l[i[n]]=n+1<i.length?\"push\"==i[n+1]?[]:l[i[n]]||{}:function(){(t._ptf=t._ptf||[]).push(i,arguments)}}))})),\"complete\"==e.readyState?u():(t.addEventListener(\"DOMContentLoaded\",u),t.addEventListener(\"load\",u))}(window,document,navigator,top,window.crossOriginIsolated);"}],"styles":[],"routeData":{"type":"endpoint","route":"/_image","pattern":"^\\/_image$","segments":[[{"content":"_image","dynamic":false,"spread":false}]],"params":[],"component":"node_modules/astro/dist/assets/image-endpoint.js","pathname":"/_image","prerender":false,"_meta":{"trailingSlash":"ignore"}}}],"site":"https://example.com","base":"/","compressHTML":true,"componentMetadata":[["\u0000astro:content",{"propagation":"in-tree","containsHead":false}],["/Users/birdcar/Code/birdcar/blog/src/pages/blog/[...slug].astro",{"propagation":"in-tree","containsHead":true}],["\u0000@astro-page:src/pages/blog/[...slug]@_@astro",{"propagation":"in-tree","containsHead":false}],["\u0000@astrojs-ssr-virtual-entry",{"propagation":"in-tree","containsHead":false}],["/Users/birdcar/Code/birdcar/blog/src/pages/blog/index.astro",{"propagation":"in-tree","containsHead":true}],["\u0000@astro-page:src/pages/blog/index@_@astro",{"propagation":"in-tree","containsHead":false}],["/Users/birdcar/Code/birdcar/blog/src/pages/index.astro",{"propagation":"in-tree","containsHead":true}],["\u0000@astro-page:src/pages/index@_@astro",{"propagation":"in-tree","containsHead":false}],["/Users/birdcar/Code/birdcar/blog/src/pages/og/[...route].ts",{"propagation":"in-tree","containsHead":false}],["\u0000@astro-page:src/pages/og/[...route]@_@ts",{"propagation":"in-tree","containsHead":false}],["/Users/birdcar/Code/birdcar/blog/src/pages/rss.xml.js",{"propagation":"in-tree","containsHead":false}],["\u0000@astro-page:src/pages/rss.xml@_@js",{"propagation":"in-tree","containsHead":false}],["/Users/birdcar/Code/birdcar/blog/src/pages/uses.astro",{"propagation":"none","containsHead":true}]],"renderers":[],"clientDirectives":[["idle","(()=>{var i=t=>{let e=async()=>{await(await t())()};\"requestIdleCallback\"in window?window.requestIdleCallback(e):setTimeout(e,200)};(self.Astro||(self.Astro={})).idle=i;window.dispatchEvent(new Event(\"astro:idle\"));})();"],["load","(()=>{var e=async t=>{await(await t())()};(self.Astro||(self.Astro={})).load=e;window.dispatchEvent(new Event(\"astro:load\"));})();"],["media","(()=>{var s=(i,t)=>{let a=async()=>{await(await i())()};if(t.value){let e=matchMedia(t.value);e.matches?a():e.addEventListener(\"change\",a,{once:!0})}};(self.Astro||(self.Astro={})).media=s;window.dispatchEvent(new Event(\"astro:media\"));})();"],["only","(()=>{var e=async t=>{await(await t())()};(self.Astro||(self.Astro={})).only=e;window.dispatchEvent(new Event(\"astro:only\"));})();"],["visible","(()=>{var r=(i,c,n)=>{let s=async()=>{await(await i())()},t=new IntersectionObserver(e=>{for(let o of e)if(o.isIntersecting){t.disconnect(),s();break}});for(let e of n.children)t.observe(e)};(self.Astro||(self.Astro={})).visible=r;window.dispatchEvent(new Event(\"astro:visible\"));})();"]],"entryModules":{"\u0000@astrojs-ssr-virtual-entry":"entry.mjs","\u0000@astro-renderers":"renderers.mjs","\u0000empty-middleware":"_empty-middleware.mjs","\u0000@astrojs-manifest":"manifest_98a149ad.mjs","\u0000@astro-page:node_modules/astro/dist/assets/image-endpoint@_@js":"chunks/image-endpoint_607cfa37.mjs","\u0000@astro-page:src/pages/index@_@astro":"chunks/index_1bfda1d0.mjs","\u0000@astro-page:src/pages/rss.xml@_@js":"chunks/rss_b658b8f0.mjs","\u0000@astro-page:src/pages/blog/index@_@astro":"chunks/index_b4a89994.mjs","\u0000@astro-page:src/pages/blog/[...slug]@_@astro":"chunks/_.._49d69294.mjs","\u0000@astro-page:src/pages/uses@_@astro":"chunks/uses_b7914533.mjs","\u0000@astro-page:src/pages/og/[...route]@_@ts":"chunks/_.._9489016b.mjs","/Users/birdcar/Code/birdcar/blog/node_modules/astro/dist/assets/services/sharp.js":"chunks/sharp_de0a5be6.mjs","/Users/birdcar/Code/birdcar/blog/src/content/blog/first-post.md?astroContentCollectionEntry=true":"chunks/first-post_d14acc6e.mjs","/Users/birdcar/Code/birdcar/blog/src/content/blog/markdown-style-guide.md?astroContentCollectionEntry=true":"chunks/markdown-style-guide_2aaba126.mjs","/Users/birdcar/Code/birdcar/blog/src/content/blog/second-post.md?astroContentCollectionEntry=true":"chunks/second-post_d7050777.mjs","/Users/birdcar/Code/birdcar/blog/src/content/blog/third-post.md?astroContentCollectionEntry=true":"chunks/third-post_985b0322.mjs","/Users/birdcar/Code/birdcar/blog/src/content/blog/using-mdx.mdx?astroContentCollectionEntry=true":"chunks/using-mdx_09a3fb12.mjs","/Users/birdcar/Code/birdcar/blog/src/content/blog/first-post.md?astroPropagatedAssets":"chunks/first-post_a20bbf3b.mjs","/Users/birdcar/Code/birdcar/blog/src/content/blog/markdown-style-guide.md?astroPropagatedAssets":"chunks/markdown-style-guide_c27e976f.mjs","/Users/birdcar/Code/birdcar/blog/src/content/blog/second-post.md?astroPropagatedAssets":"chunks/second-post_670c199a.mjs","/Users/birdcar/Code/birdcar/blog/src/content/blog/third-post.md?astroPropagatedAssets":"chunks/third-post_12b3f320.mjs","/Users/birdcar/Code/birdcar/blog/src/content/blog/using-mdx.mdx?astroPropagatedAssets":"chunks/using-mdx_6a10f7dd.mjs","astro:scripts/page.js":"_astro/page.3736b48a.js","astro:scripts/before-hydration.js":""},"assets":["/_astro/_...slug_.00023130.css","/blog-placeholder-1.jpg","/blog-placeholder-2.jpg","/blog-placeholder-3.jpg","/blog-placeholder-4.jpg","/blog-placeholder-5.jpg","/blog-placeholder-about.jpg","/favicon.svg","/_astro/page.3736b48a.js","/fonts/atkinson-bold.woff","/fonts/atkinson-regular.woff","/_astro/page.3736b48a.js","/index.html","/rss.xml","/blog/index.html","/uses/index.html","/~partytown/partytown-atomics.js","/~partytown/partytown-media.js","/~partytown/partytown-sw.js","/~partytown/partytown.js"]});

export { manifest };
