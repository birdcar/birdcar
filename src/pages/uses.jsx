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
  // @TODO: Make this a call to the /api/uses endpoint
  const uses = {
    workstation: [
      {
        title: `16-inch MacBook Pro, M1 Max, 64GB RAM (2021)`,
        slug: `m1-macbook-pro`,
        href: `https://www.apple.com/macbook-pro-14-and-16/`,
        note: `I've been on Macs my whole professional career, even before my first job in tech (which, depending on what you count as "in tech";, was the Apple Store), and I can't describe to you how incredible this laptop is. I will go through multiple workdays without ever plugging it in, with no noticeable loss of power or overheating. Truly the best laptop I've ever owned.`,
      },
      {
        title: `Lenovo Thinkpad X1-Extreme Gen 2, i7, 16GB RAM`,
        slug: `thinkpad-x1-extreme`,
        href: `https://www.lenovo.com/us/en/p/laptops/thinkpad/thinkpadx1/x1-extreme-g4/22tp2x1x1e4`,
        note: `This is my Linux laptop (Fedora) and personal daily driver. It's just about time for an upgrade but I haven't yet figured out what's next.`,
      },
      {
        title: `LG 40WP95C-W 40-inch UltraWide Curved WUHD`,
        slug: `lg-40-in-widescreen-monitor`,
        href: `https://www.lg.com/us/monitors/lg-40wp95c-w`,
        note: `I switched to this from a multi-monitor setup and I&apos;m never going back ever. I was a naysayer on the curved monitor thing for a long time but it genuinely is a game changer.`,
      },
      {
        title: `Keychron K2`,
        slug: `keychron-k2`,
        href: `https://www.keychron.com/products/keychron-k2-wireless-mechanical-keyboard`,
        note: `As you can see above, I connect to more than one laptop at the same workstation. the Keychron saved me from needing a (likely terrible) KVM. Extremely dependable, and as a bonus it also connects to my android pone`,
      },
      {
        title: `Apple Magic Trackpad`,
        slug: `https://www.apple.com/shop/product/MK2D3AM/A/magic-trackpad-white-multi-touch-surface`,
        href: `apple-magic-trackpad`,
        note: `My preferred style of work means I never touch the mouse if I can help it. Having said that, one of the small things that keeps me on MacOS for work and prevents me from going all in on Linux is just how damned good their multi-touch gestures are.`,
      },
      {
        title: `Platform Output desk`,
        slug: `platform-output`,
        href: `https://output.com/products/platform`,
        note: `Yes, this is a desk for musicians, but please hear me out. This is the perfect desk for remote work, and that&apos;s doubly true if you&apos;re an SRE or Devops engineer. It&apos;s got 9U of rack space built in (perfect for power, a homelab server, a switch, or other rack mounted goodies) and is sturdy as hell. Its only flaw is that it&apos;s not able to be a standing desk. If you can live without that, I can&apos;t recomment it enough.`,
      },
      {
        title: `Humanscale Freedom`,
        slug: `humanscale-freedom`,
        href: `https://www.humanscale.com/products/seating/freedom-headrest-executive-chair`,
        note: `I actually inherited this chair, and my back is extremelly thankful I did. It fundamentally changed my posture and comfort while working at a desk.`,
      }
    ],
    development: [
      {
        title: `VS Code`,
        slug: `visual-studio-code`,
        href: `https://code.visualstudio.com/`,
        note: `I'm sure that no one needs a link to this but VS Code is my everyday "do everything" editor. I still keep (neo)vim around for when I need it, but VS Code + the Vim extension is genuinely better and more versitile than any editor I've ever used.`,
      },
      {
        title: `Kitty`,
        slug: `kitty-terminal`,
        href: `https://sw.kovidgoyal.net/kitty/`,
        note: `A GPU powered terminal for people who prefer the terminal as their ideal interface. Tons of great features, highly configurable, fully cross platform.`,
      }
    ],
    productivity: [
      {
        title: `Raycast`,
        slug: `raycast`,
        href: `https://www.raycast.com/`,
        note: `I was a long time Alfred customer. I had a lifetime powerpack subscription, I had been using it since the Beta. Raycast converted me in a single afternoon. It&apos;s that good.`,
      },
      {
        title: `GitHub`,
        slug: `github`,
        href: `/blog/just-use-github`,
        note: `Why is this in productivity and not development tools? Because honestly I use it way more as a way to organize my life and work than I do as a way to just get code into production.`,
      }
    ]
  }
  return (
    <>
      <Head>
        <title>Uses - Birdcar.dev</title>
        <meta
          name="description"
          content="The tools in my toolbox"
        />
      </Head>
      <SimpleLayout
        title="Software, hardware, and trinkets I'd rather not live without."
        intro="In the interest of supporting the individuals and (less importantly) companies who make my life that much better every day, here's my current setup."
      >
        <div className="space-y-20">
          {Object.keys(uses).map(section => (
            <ToolsSection title={`${section.toUpperCase()}`} key={section}>
              {uses[section].map(({ title, slug, href, note}) => (
                <Tool key={slug} title={title} href={href}>
                  {note}
                </Tool>
              ))}
            </ToolsSection>
          ))}
        </div>
      </SimpleLayout>
    </>
  )
}
