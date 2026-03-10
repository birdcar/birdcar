import type { Root } from 'mdast';
import { unified } from 'unified';
import remarkParse from 'remark-parse';
import remarkGfm from 'remark-gfm';
import { remarkBfm, extractMetadata } from '@birdcar/markdown';
import type { DocumentMetadata } from '@birdcar/markdown';

const processor = unified().use(remarkParse).use(remarkGfm).use(remarkBfm as any);

export async function getPostMetadata(rawContent: string): Promise<DocumentMetadata> {
  const tree = processor.parse(rawContent);
  const transformed = await processor.run(tree) as Root;
  return extractMetadata(transformed);
}
