import { describe, test, expect } from 'bun:test';
import {
  buildSchemaGraph,
  serializeSchemaGraph,
  type SchemaPageInput,
} from './jsonld';

function basePage(overrides: Partial<SchemaPageInput>): SchemaPageInput {
  return {
    pathname: '/',
    canonicalURL: 'https://www.birdcar.dev/',
    title: 'Birdcar',
    description: 'Internal tools and automation.',
    ogImage: 'https://www.birdcar.dev/og.png',
    type: 'website',
    ...overrides,
  };
}

function findById(graph: ReturnType<typeof buildSchemaGraph>, id: string) {
  return graph['@graph'].find((n) => n['@id'] === id);
}

function findByType(graph: ReturnType<typeof buildSchemaGraph>, type: string) {
  return graph['@graph'].find((n) => n['@type'] === type);
}

describe('buildSchemaGraph', () => {
  test('always emits Person, Organization, and WebSite anchors', () => {
    const graph = buildSchemaGraph(basePage({}));
    expect(findById(graph, 'https://www.birdcar.dev/#person')).toBeDefined();
    expect(findById(graph, 'https://www.birdcar.dev/#organization')).toBeDefined();
    expect(findById(graph, 'https://www.birdcar.dev/#website')).toBeDefined();
  });

  test('exposes the schema.org @context', () => {
    const graph = buildSchemaGraph(basePage({}));
    expect(graph['@context']).toBe('https://schema.org');
  });

  test('home page includes a Service node', () => {
    const graph = buildSchemaGraph(basePage({ pathname: '/' }));
    expect(findById(graph, 'https://www.birdcar.dev/#service')).toBeDefined();
  });

  test('about page omits the Service node', () => {
    const graph = buildSchemaGraph(
      basePage({ pathname: '/about', canonicalURL: 'https://www.birdcar.dev/about/' }),
    );
    expect(findById(graph, 'https://www.birdcar.dev/#service')).toBeUndefined();
  });

  test('blog post emits BlogPosting and BreadcrumbList', () => {
    const graph = buildSchemaGraph(
      basePage({
        type: 'article',
        pathname: '/writing/my-post/',
        canonicalURL: 'https://www.birdcar.dev/writing/my-post/',
        title: 'My post — Birdcar',
        publishedDate: new Date('2026-04-01T00:00:00Z'),
        modifiedDate: new Date('2026-04-02T00:00:00Z'),
        tags: ['ops', 'support'],
      }),
    );
    const article = findByType(graph, 'BlogPosting');
    expect(article).toBeDefined();
    expect(article?.headline).toBe('My post');
    expect(article?.datePublished).toBe('2026-04-01T00:00:00.000Z');
    expect(article?.dateModified).toBe('2026-04-02T00:00:00.000Z');
    expect(findByType(graph, 'BreadcrumbList')).toBeDefined();
  });

  test('about page is typed AboutPage', () => {
    const graph = buildSchemaGraph(
      basePage({ pathname: '/about/', canonicalURL: 'https://www.birdcar.dev/about/' }),
    );
    expect(findByType(graph, 'AboutPage')).toBeDefined();
  });

  test('blog index page emits a Blog node', () => {
    const graph = buildSchemaGraph(
      basePage({ pathname: '/writing/', canonicalURL: 'https://www.birdcar.dev/writing/' }),
    );
    expect(findByType(graph, 'Blog')).toBeDefined();
  });

  test('tag-detail page emits a BreadcrumbList for the tag', () => {
    const graph = buildSchemaGraph(
      basePage({
        pathname: '/tags/ops/',
        canonicalURL: 'https://www.birdcar.dev/tags/ops/',
      }),
    );
    const breadcrumbs = findByType(graph, 'BreadcrumbList');
    expect(breadcrumbs).toBeDefined();
    const items = breadcrumbs?.itemListElement as Array<{ name: string }>;
    expect(items.map((i) => i.name)).toContain('ops');
  });

  test('prunes undefined leaves so JSON is deterministic', () => {
    const graph = buildSchemaGraph(
      basePage({
        type: 'article',
        pathname: '/writing/p/',
        canonicalURL: 'https://www.birdcar.dev/writing/p/',
        title: 'P',
        // tags omitted on purpose
      }),
    );
    const article = findByType(graph, 'BlogPosting') as Record<string, unknown>;
    expect('keywords' in article).toBe(false);
    expect('articleSection' in article).toBe(false);
  });
});

describe('serializeSchemaGraph', () => {
  test('escapes < as \\u003c so embedded JSON cannot terminate <script>', () => {
    const graph = {
      '@context': 'https://schema.org' as const,
      '@graph': [
        { '@type': 'Test', payload: 'before</script>after' } as Record<string, unknown>,
      ],
    };
    const out = serializeSchemaGraph(graph);
    expect(out).not.toContain('</script>');
    expect(out).toContain('\\u003c/script>');
  });
});
