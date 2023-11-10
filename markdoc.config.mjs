import { defineMarkdocConfig, nodes, component } from '@astrojs/markdoc/config';
import markdoc from '@astrojs/markdoc';
import shiki from '@astrojs/markdoc/shiki';

export default defineMarkdocConfig({
  tags: {
    card: {
      render: component("./src/components/markdoc/tags/Card.astro"),
      attributes: {
        elevation: { type: Number },
        fill: { type: String }
      }
    }
  },
  nodes: {
    heading: {
      ...nodes.heading,
      render: component("./src/components/markdoc/nodes/Heading.astro")
    },
    paragraph: {
      ...nodes.paragraph,
      render: component("./src/components/markdoc/nodes/Paragraph.astro"),
    },
    hr: {
      ...nodes.hr,
      render: component("./src/components/markdoc/nodes/HorizontalRule.astro")
    },
    image: {
      ...nodes.image,
      render: component("./src/components/markdoc/nodes/Image.astro")
    },
    fence: {
      ...nodes.fence
    },
    blockquote: {
      ...nodes.blockquote
    },
    list: {
      ...nodes.list
    },
    item: {
      ...nodes.item
    },
    table: {
      ...nodes.table
    },
    thead: {
      ...nodes.thead
    },
    tbody: {
      ...nodes.tbody
    },
    tr: {
      ...nodes.tr
    },
    td: {
      ...nodes.td
    },
    th: {
      ...nodes.th
    },
    inline: {
      ...nodes.inline
    },
    strong: {
      ...nodes.strong
    },
    em: {
      ...nodes.em
    },
    s: {
      ...nodes.s
    },
    link: {
      ...nodes.link,
      render: component("./src/components/markdoc/nodes/Link.astro")
    },
    code: {
      ...nodes.code
    },
    hardbreak: {
      ...nodes.hardbreak
    },
    softbreak: {
      ...nodes.softbreak
    },
    error: {
      ...nodes.error
    }
  },
  integrations: [markdoc({ allowHTML: true })],
  extends: [
    shiki()
  ]
});
