import Head from 'next/head'

import { Card } from '@/components/Card'
import { Section } from '@/components/Section'
import { SimpleLayout } from '@/components/SimpleLayout'

function ToolsSection({ children, ...props }) {
  return (
    <Section {...props}>
      <ul role="list" className="space-y-16">
        {children}
      </ul>
    </Section>
  )
}

function Tool({ title, href, children }) {
  return (
    <Card as="li">
      <Card.Title as="h3" href={href}>
        {title}
      </Card.Title>
      <Card.Description>{children}</Card.Description>
    </Card>
  )
}

export default function Uses() {
  return (
    <>
      <Head>
        <title>Uses - Nick Cannariato</title>
        <meta
          name="description"
          content="Software I use, gadgets I love, and other things I recommend."
        />
      </Head>
      <SimpleLayout
        title="Software I use, gadgets I love, and other things I recommend."
        intro="I get asked a lot about the things I use to build software, stay productive, or buy to fool myself into thinking I’m being productive when I’m really just procrastinating. Here’s a big list of all of my favorite stuff."
      >
        <div className="space-y-20">
          <ToolsSection title="Workstation">
            <Tool title="16-inch MacBook Pro, M1 Max, 64GB RAM (2021)">
              Much fast. Very AMD. Wow.
            </Tool>
            <Tool title="LG 40WP95C-W 40-inch UltraWide Curved WUHD">
              Epic photo of monitor is epic
            </Tool>
            <Tool title="Keychron K2">
              They don’t make keyboards the way they used to. I buy these any
              time I see them go up for sale and keep them in storage in case I
              need parts or need to retire my main.
            </Tool>
            <Tool title="Apple Magic Trackpad">
              If you have to use a mouse to navigate your mac? This is the one to use.
            </Tool>
            <Tool title="Humanscale Freedom">
              A cozy place for my aging back.
            </Tool>
          </ToolsSection>
          <ToolsSection title="Development tools">
            <Tool title="VS Code">
              My "do everything" editor.
            </Tool>
            <Tool title="Kitty">
              Imagine if your whole terminal was Tmux. Now imagine it was cross platform so you could keep your config in Git and your terminal was always the same everywhere? Now you understand why you need Kitty.
            </Tool>
            <Tool title="TablePlus">
              Great software for working with databases. Has saved me from
              building about a thousand admin interfaces for my various projects
              over the years.
            </Tool>
          </ToolsSection>
          <ToolsSection title="Productivity">
            <Tool title="Raycast">
              I was a long time Alfred customer. I had a lifetime powerpack subscription, I had been using it since the Beta. Raycast converted me in a single afternoon. It's that good.
            </Tool>
            <Tool title="LogSeq">
              Markdown notes and graph connections with a focus on privacy.
            </Tool>
            <Tool title="Todoist">
              If there's a better platform for tasks I've never found it.
            </Tool>
          </ToolsSection>
        </div>
      </SimpleLayout>
    </>
  )
}
