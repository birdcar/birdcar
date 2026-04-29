<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:atom="http://www.w3.org/2005/Atom"
  xmlns:content="http://purl.org/rss/1.0/modules/content/"
  exclude-result-prefixes="atom content">

  <xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>

  <xsl:template match="/">
    <html lang="en">
      <head>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <title><xsl:value-of select="/rss/channel/title"/> — RSS Feed</title>
        <link rel="preconnect" href="https://fonts.googleapis.com"/>
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin=""/>
        <link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&amp;family=Inter+Tight:wght@400;500;600&amp;family=JetBrains+Mono:wght@400;500&amp;display=swap" rel="stylesheet"/>
        <style>
          *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
          :root{
            --paper:#F7F3EC;
            --paper-2:#EFE9DD;
            --ink:#1A1814;
            --ink-2:#4A453D;
            --ink-3:#6B645A;
            --ink-4:#A89F90;
            --clay:#B8442B;
            --clay-2:#8E2F1B;
            --clay-tint:#F2D8B6;
            --serif:'Source Serif 4','Iowan Old Style','Charter',Georgia,serif;
            --sans:'Inter Tight',-apple-system,'Segoe UI',system-ui,sans-serif;
            --mono:'JetBrains Mono','SF Mono',Menlo,Consolas,monospace;
          }
          body{
            font-family:var(--serif);
            font-feature-settings:"kern","liga","onum";
            background:var(--paper);
            color:var(--ink);
            line-height:1.55;
            padding:3rem 1.25rem;
            max-width:44rem;
            margin:0 auto;
          }
          a{color:var(--clay);text-decoration:none}
          a:hover{color:var(--clay-2);text-decoration:underline;text-underline-offset:0.2em}
          .banner{
            font-family:var(--mono);
            font-size:0.8125rem;
            line-height:1.55;
            color:var(--ink-2);
            background:var(--clay-tint);
            border-top:1px solid var(--ink-4);
            border-bottom:1px solid var(--ink-4);
            padding:0.75rem 1rem;
            margin-bottom:3rem;
          }
          .banner strong{color:var(--ink);font-weight:500}
          header{margin-bottom:3rem}
          header h1{
            font-family:var(--serif);
            font-size:2.25rem;
            font-weight:700;
            line-height:1.1;
            letter-spacing:-0.01em;
            color:var(--ink);
            margin-bottom:0.5rem;
          }
          header h1 span{color:var(--clay);font-style:italic;font-weight:400}
          header p{
            font-family:var(--sans);
            color:var(--ink-2);
            font-size:1rem;
            max-width:38rem;
          }
          .items{display:flex;flex-direction:column}
          article{
            border-top:1px solid var(--paper-2);
            padding:1.5rem 0;
          }
          article:last-child{border-bottom:1px solid var(--paper-2)}
          article time{
            font-family:var(--mono);
            font-size:0.75rem;
            color:var(--ink-3);
            text-transform:uppercase;
            letter-spacing:0.04em;
            display:block;
            margin-bottom:0.375rem;
          }
          article h2{
            font-family:var(--serif);
            font-size:1.25rem;
            font-weight:600;
            line-height:1.25;
            margin-bottom:0.5rem;
          }
          article h2 a{color:var(--ink)}
          article h2 a:hover{color:var(--clay);text-decoration:none}
          article p{
            font-family:var(--serif);
            color:var(--ink-2);
            font-size:1rem;
            line-height:1.5;
          }
          footer{
            margin-top:3rem;
            padding-top:1.25rem;
            border-top:1px solid var(--paper-2);
            font-family:var(--mono);
            font-size:0.75rem;
            color:var(--ink-3);
            text-transform:uppercase;
            letter-spacing:0.04em;
          }
        </style>
      </head>
      <body>
        <div class="banner">
          <strong>This is an RSS feed.</strong> Paste the URL into your reader.
          New to feeds? <a href="https://aboutfeeds.com">aboutfeeds.com</a>.
        </div>

        <header>
          <h1><xsl:value-of select="/rss/channel/title"/><span>.dev</span></h1>
          <p><xsl:value-of select="/rss/channel/description"/></p>
        </header>

        <div class="items">
          <xsl:for-each select="/rss/channel/item">
            <article>
              <time>
                <xsl:value-of select="pubDate"/>
              </time>
              <h2>
                <a>
                  <xsl:attribute name="href"><xsl:value-of select="link"/></xsl:attribute>
                  <xsl:value-of select="title"/>
                </a>
              </h2>
              <xsl:if test="description != ''">
                <p><xsl:value-of select="description"/></p>
              </xsl:if>
            </article>
          </xsl:for-each>
        </div>

        <footer>
          <a href="https://www.birdcar.dev">birdcar.dev</a>
        </footer>
      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
