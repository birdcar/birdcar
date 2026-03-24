import type { Root } from 'mdast';
import { unified } from 'unified';
import remarkParse from 'remark-parse';
import remarkRehype from 'remark-rehype';
import rehypeStringify from 'rehype-stringify';
import { remarkBfm, extractMetadata } from '@birdcar/markdown';
import { remarkBfmDirectives } from '@birdcar/markdown/directives';
import { remarkBfmTasks } from '@birdcar/markdown/tasks';
import { remarkBfmModifiers } from '@birdcar/markdown/modifiers';
import { remarkBfmMentions } from '@birdcar/markdown/mentions';
import { remarkBfmHashtags } from '@birdcar/markdown/hashtags';
import { mention, hashtag, directiveBlock } from './bfm-handlers';
import type { DocumentMetadata } from '@birdcar/markdown';

const processor = unified().use(remarkParse).use(remarkBfm as any);

export async function getPostMetadata(rawContent: string): Promise<DocumentMetadata> {
  const tree = processor.parse(rawContent);
  const transformed = await processor.run(tree) as Root;
  return extractMetadata(transformed);
}

const htmlProcessor = unified()
  .use(remarkParse)
  .use(remarkBfmDirectives as any)
  .use(remarkBfmTasks as any)
  .use(remarkBfmModifiers as any)
  .use(remarkBfmMentions as any)
  .use(remarkBfmHashtags as any)
  .use(remarkRehype, {
    handlers: {
      mention,
      hashtag,
      directiveBlock,
    } as any,
  })
  .use(rehypeStringify);

export async function renderPostToHtml(rawContent: string): Promise<string> {
  const result = await htmlProcessor.process(rawContent);
  return String(result);
}
