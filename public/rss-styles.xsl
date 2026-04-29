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
        <style>
          *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
          body{
            font-family:'Space Grotesk',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
            background:#1e1e2e;
            color:#cdd6f4;
            line-height:1.6;
            padding:2rem 1rem;
            max-width:44rem;
            margin:0 auto;
          }
          a{color:#89b4fa;text-decoration:none}
          a:hover{color:#b4befe;text-decoration:underline}
          .banner{
            background:#313244;
            border:1px solid #585b70;
            border-radius:0.5rem;
            padding:1rem 1.25rem;
            margin-bottom:2rem;
            font-size:0.875rem;
            color:#a6adc8;
          }
          .banner strong{color:#cdd6f4}
          .banner code{
            background:#45475a;
            padding:0.125rem 0.375rem;
            border-radius:0.25rem;
            font-family:'JetBrains Mono',monospace;
            font-size:0.8125rem;
          }
          header{margin-bottom:2.5rem}
          header h1{
            font-family:'Archivo',sans-serif;
            font-size:1.75rem;
            font-weight:700;
            color:#cdd6f4;
            margin-bottom:0.25rem;
          }
          header h1 span{color:#74c7ec}
          header p{color:#a6adc8;font-size:0.9375rem}
          .items{display:flex;flex-direction:column;gap:1.5rem}
          article{
            border-bottom:1px solid #313244;
            padding-bottom:1.5rem;
          }
          article:last-child{border-bottom:none}
          article h2{
            font-family:'Archivo',sans-serif;
            font-size:1.125rem;
            font-weight:600;
            margin-bottom:0.25rem;
          }
          article h2 a{color:#cdd6f4}
          article h2 a:hover{color:#89b4fa}
          time{
            font-size:0.8125rem;
            color:#7f849c;
            display:block;
            margin-bottom:0.5rem;
          }
          article p{
            color:#bac2de;
            font-size:0.9375rem;
            line-height:1.5;
          }
          footer{
            margin-top:2.5rem;
            padding-top:1.5rem;
            border-top:1px solid #313244;
            font-size:0.8125rem;
            color:#7f849c;
          }
        </style>
        <link rel="preconnect" href="https://fonts.googleapis.com"/>
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin=""/>
        <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700&amp;family=Space+Grotesk:wght@400;500&amp;family=JetBrains+Mono:wght@400&amp;display=swap" rel="stylesheet"/>
      </head>
      <body>
        <div class="banner">
          <strong>This is an RSS feed.</strong> Subscribe by copying the URL into your RSS reader.
          Visit <a href="https://aboutfeeds.com">About Feeds</a> to learn more.
        </div>

        <header>
          <h1><xsl:value-of select="/rss/channel/title"/><span>.dev</span></h1>
          <p><xsl:value-of select="/rss/channel/description"/></p>
        </header>

        <div class="items">
          <xsl:for-each select="/rss/channel/item">
            <article>
              <h2>
                <a>
                  <xsl:attribute name="href"><xsl:value-of select="link"/></xsl:attribute>
                  <xsl:value-of select="title"/>
                </a>
              </h2>
              <time>
                <xsl:value-of select="pubDate"/>
              </time>
              <xsl:if test="description != ''">
                <p><xsl:value-of select="description"/></p>
              </xsl:if>
            </article>
          </xsl:for-each>
        </div>

        <footer>
          <p>
            <a href="https://www.birdcar.dev">birdcar.dev</a>
          </p>
        </footer>
      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
