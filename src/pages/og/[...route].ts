import { OGDynamicImageRoute } from '@cyberkoalastudios/og-image-generator'
import { getCollection } from 'astro:content'

// (Optional) Example of filtration by current month and non draft posts.
const nonDraftFreshArticlesEntries = await getCollection(
  'blog',
  ({ data }) => {
    const currentDate = new Date()
    const monthNumber = currentDate.getMonth()
    if (data.pubDate?.getMonth() == monthNumber) {
      return data.published
    }
  }
);

export const { getStaticPaths, get } = OGDynamicImageRoute({
  // Tell us the name of your dynamic route segment.
  // In this case it's `route`, because the file is named `[...route].ts`.
  param: 'route',

  // (Optional) CollectionEntries (array of content collection)
  // If you want generate only filtered posts from collection
  collection: nonDraftFreshArticlesEntries,

  // A collection of pages to generate images for.
  // This can be any map of paths to data, not necessarily a glob result.
  pages: await import.meta.glob('/src/content/blog/**/*.{md,mdx,mdoc}', {
    eager: true,
  }),

  // For each page, this callback will be used to customize the OpenGraph
  // image. For example, if `pages` was passed a glob like above, you
  // could read values from frontmatter.
  getImageOptions: (path, page) => ({
    title: page.frontmatter.title,
    description: page.frontmatter.description,
    logo: {
      path: './src/favicon.png',
    },
    backgroundImage: {
      url: page.frontmatter.image?.url,
      alpha: 0.2,
      blurStrength: 3,
    },
    // There are a bunch more options you can use here!
    bgGradient: [
      [26, 26, 26],
      [24, 24, 24],
    ],
    /** Border config. Highlights a single edge of the image. */
    border: {
      /** RGB border color, e.g. `[0, 255, 0]`. */
      color: [76, 0, 153],

      /** Border width. Default: `0`. */
      width: 15,

      /** Side of the image to draw the border on. Inline start/end respects writing direction. */
      side: 'block-end',
    },

    font: {
      title: {
        families: [
          'Open Sans',
          'Ubuntu',
          'Istok Web',
          'Source Sans Pro',
          'PT Serif',
          'Andika',
        ],
      },
      description: {
        families: [
          'Open Sans',
          'Ubuntu',
          'Istok Web',
          'Source Sans Pro',
          'PT Serif',
          'Andika',
        ],
      },
    },
  }),
})
