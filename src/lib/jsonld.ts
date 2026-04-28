// Single-source-of-truth JSON-LD generator. Base.astro calls
// `buildSchemaGraph` once per page; output is a `@graph` so every
// entity gets a stable @id and references (author, publisher,
// breadcrumb items) are by-id rather than duplicated.

const SITE_ORIGIN = 'https://birdcar.dev';

const PERSON_ID = `${SITE_ORIGIN}/#person`;
const WEBSITE_ID = `${SITE_ORIGIN}/#website`;
const ORG_ID = `${SITE_ORIGIN}/#organization`;
const SERVICE_ID = `${SITE_ORIGIN}/#service`;

const PERSON_NAME = 'Nick Cannariato';
const ORG_NAME = 'Birdcar';
const ORG_LOGO_URL = `${SITE_ORIGIN}/og-square.png`;
const ORG_LOGO_DIM = 1200;

const SAME_AS = [
  'https://github.com/birdcar',
  'https://linkedin.com/in/birdcar',
] as const;

export type SchemaPageInput = {
  pathname: string;
  canonicalURL: string;
  title: string;
  description: string;
  ogImage: string;
  type: 'website' | 'article';
  publishedDate?: Date;
  modifiedDate?: Date;
  tags?: string[];
};

type JsonLdNode = Record<string, unknown>;
type JsonLdGraph = { '@context': 'https://schema.org'; '@graph': JsonLdNode[] };

type PageKind =
  | 'home'
  | 'about'
  | 'contact'
  | 'work'
  | 'blog-index'
  | 'blog-post'
  | 'tags-index'
  | 'tag-detail'
  | 'generic';

function classifyPage(pathname: string, type: SchemaPageInput['type']): PageKind {
  if (type === 'article') return 'blog-post';
  if (pathname === '/' || pathname === '') return 'home';
  if (pathname.startsWith('/about')) return 'about';
  if (pathname.startsWith('/contact')) return 'contact';
  if (pathname.startsWith('/work')) return 'work';
  // /tags/ and /tags/<slug>/ — distinguish on segment count
  if (pathname.startsWith('/tags')) {
    const trimmed = pathname.replace(/^\/tags\/?/, '').replace(/\/$/, '');
    return trimmed.length === 0 ? 'tags-index' : 'tag-detail';
  }
  // /writing/ and /writing/<page-num>/ — paginated index, not a post
  if (pathname.startsWith('/writing')) return 'blog-index';
  return 'generic';
}

function person(): JsonLdNode {
  return {
    '@type': 'Person',
    '@id': PERSON_ID,
    name: PERSON_NAME,
    alternateName: 'Birdcar',
    url: SITE_ORIGIN,
    jobTitle: 'Solutions engineer & consultant',
    worksFor: { '@id': ORG_ID },
    address: {
      '@type': 'PostalAddress',
      addressLocality: 'Fort Worth',
      addressRegion: 'TX',
      addressCountry: 'US',
    },
    email: 'hi@birdcar.dev',
    sameAs: [...SAME_AS],
  };
}

function organization(): JsonLdNode {
  return {
    '@type': 'Organization',
    '@id': ORG_ID,
    name: ORG_NAME,
    url: SITE_ORIGIN,
    founder: { '@id': PERSON_ID },
    logo: {
      '@type': 'ImageObject',
      url: ORG_LOGO_URL,
      width: ORG_LOGO_DIM,
      height: ORG_LOGO_DIM,
    },
    sameAs: [...SAME_AS],
  };
}

function website(): JsonLdNode {
  return {
    '@type': 'WebSite',
    '@id': WEBSITE_ID,
    url: SITE_ORIGIN,
    name: 'birdcar.dev',
    description:
      "Nick Cannariato builds internal tools and automations for small businesses in Dallas/Fort Worth. Project-based, 4–12 weeks, fixed scope.",
    publisher: { '@id': ORG_ID },
    inLanguage: 'en-US',
  };
}

function service(): JsonLdNode {
  // Plain Service rather than ProfessionalService/LocalBusiness — those
  // inherit physical-place requirements (address, telephone) we don't
  // expose. Service still carries the right SEO signals for area-served
  // and offerings without claiming to be a brick-and-mortar business.
  return {
    '@type': 'Service',
    '@id': SERVICE_ID,
    name: 'Birdcar — Internal tools & automation',
    url: `${SITE_ORIGIN}/work/`,
    provider: { '@id': PERSON_ID },
    areaServed: { '@type': 'City', name: 'Dallas/Fort Worth' },
    serviceType: 'Custom internal tools and workflow automation',
    description:
      'Project-based engagements (4–12 weeks, fixed scope) building internal tools that replace spreadsheets for CPAs, mortgage brokers, realtors, and small operators in the $100k–$2M range.',
  };
}

function webPage(input: SchemaPageInput, kind: PageKind): JsonLdNode {
  const typeByKind: Record<PageKind, string> = {
    home: 'WebPage',
    about: 'AboutPage',
    contact: 'ContactPage',
    work: 'WebPage',
    'blog-index': 'CollectionPage',
    'blog-post': 'WebPage',
    'tags-index': 'CollectionPage',
    'tag-detail': 'CollectionPage',
    generic: 'WebPage',
  };
  const node: JsonLdNode = {
    '@type': typeByKind[kind],
    '@id': `${input.canonicalURL}#webpage`,
    url: input.canonicalURL,
    name: input.title,
    description: input.description,
    inLanguage: 'en-US',
    isPartOf: { '@id': WEBSITE_ID },
    primaryImageOfPage: {
      '@type': 'ImageObject',
      url: input.ogImage,
    },
    about: { '@id': PERSON_ID },
  };
  if (kind === 'blog-post') {
    node.mainEntity = { '@id': `${input.canonicalURL}#article` };
  }
  if (kind === 'blog-index') {
    node.mainEntity = { '@id': `${SITE_ORIGIN}/writing/#blog` };
  }
  return node;
}

function blogCollection(): JsonLdNode {
  return {
    '@type': 'Blog',
    '@id': `${SITE_ORIGIN}/writing/#blog`,
    name: 'birdcar.dev — Writing',
    url: `${SITE_ORIGIN}/writing/`,
    description: 'Essays on building software, support engineering, and tools for small businesses.',
    inLanguage: 'en-US',
    author: { '@id': PERSON_ID },
    publisher: { '@id': ORG_ID },
  };
}

function blogPosting(input: SchemaPageInput): JsonLdNode {
  const published = input.publishedDate?.toISOString();
  const modified = (input.modifiedDate ?? input.publishedDate)?.toISOString();
  const headline = stripSiteSuffix(input.title);
  return {
    '@type': 'BlogPosting',
    '@id': `${input.canonicalURL}#article`,
    headline,
    name: headline,
    description: input.description,
    image: input.ogImage,
    inLanguage: 'en-US',
    url: input.canonicalURL,
    datePublished: published,
    dateModified: modified,
    author: { '@id': PERSON_ID },
    publisher: { '@id': ORG_ID },
    keywords: input.tags?.length ? input.tags.join(', ') : undefined,
    articleSection: input.tags?.[0],
    isPartOf: { '@id': `${SITE_ORIGIN}/writing/#blog` },
    mainEntityOfPage: { '@id': `${input.canonicalURL}#webpage` },
  };
}

function breadcrumb(items: { name: string; url: string }[], canonicalURL: string): JsonLdNode {
  return {
    '@type': 'BreadcrumbList',
    '@id': `${canonicalURL}#breadcrumbs`,
    itemListElement: items.map((item, i) => ({
      '@type': 'ListItem',
      position: i + 1,
      name: item.name,
      item: item.url,
    })),
  };
}

function tagFromPathname(pathname: string): string | null {
  const match = pathname.match(/^\/tags\/([^/]+)\/?$/);
  return match ? decodeURIComponent(match[1]) : null;
}

// Pages pass the full `<title>` value (which includes " — Birdcar") through
// to Base.astro. For BlogPosting.headline and the current breadcrumb item
// we want the bare page title, since search engines treat headline/name as
// the work's own title and the site suffix is redundant.
function stripSiteSuffix(title: string): string {
  return title.replace(/\s+[—–-]\s+Birdcar\s*$/i, '').trim() || title;
}

// Strip undefined leaves recursively so JSON.stringify doesn't drop keys
// silently — explicit pruning keeps output deterministic.
function prune<T>(value: T): T {
  if (Array.isArray(value)) {
    return value.map(prune).filter((v) => v !== undefined) as unknown as T;
  }
  if (value && typeof value === 'object') {
    const out: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(value as Record<string, unknown>)) {
      if (v === undefined) continue;
      out[k] = prune(v);
    }
    return out as T;
  }
  return value;
}

export function buildSchemaGraph(input: SchemaPageInput): JsonLdGraph {
  const kind = classifyPage(input.pathname, input.type);
  const graph: JsonLdNode[] = [website(), organization(), person()];

  // ProfessionalService surfaces only on home and /work/ — pages whose
  // intent is to advertise the consulting practice. Adding it everywhere
  // dilutes the signal.
  if (kind === 'home' || kind === 'work') {
    graph.push(service());
  }

  graph.push(webPage(input, kind));

  if (kind === 'blog-index') {
    graph.push(blogCollection());
  }

  if (kind === 'blog-post') {
    graph.push(blogPosting(input));
    graph.push(
      breadcrumb(
        [
          { name: 'Home', url: `${SITE_ORIGIN}/` },
          { name: 'Writing', url: `${SITE_ORIGIN}/writing/` },
          { name: stripSiteSuffix(input.title), url: input.canonicalURL },
        ],
        input.canonicalURL,
      ),
    );
  }

  if (kind === 'tag-detail') {
    const tag = tagFromPathname(input.pathname) ?? 'tag';
    graph.push(
      breadcrumb(
        [
          { name: 'Home', url: `${SITE_ORIGIN}/` },
          { name: 'Tags', url: `${SITE_ORIGIN}/tags/` },
          { name: tag, url: input.canonicalURL },
        ],
        input.canonicalURL,
      ),
    );
  }

  return prune({
    '@context': 'https://schema.org',
    '@graph': graph,
  } satisfies JsonLdGraph);
}

// XSS-safe serializer: replace '<' so a string containing '</script>'
// can't terminate the host <script> tag prematurely. Standard mitigation
// for JSON-LD embedded in HTML.
export function serializeSchemaGraph(graph: JsonLdGraph): string {
  return JSON.stringify(graph).replace(/</g, '\\u003c');
}
