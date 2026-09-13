---
paths:
  - '{routes/**,app/Providers/FolioServiceProvider.php,app/Services/MarketingSite.php,config/marketing.php}'
---

# Services

## Marketing and application route boundaries
Marketing HTML pages use Folio mounted only on the configured marketing host. Admin and customer tenant applications use standard Laravel routes and their own middleware/authorization. Public RSS, redirects, robots, and sitemap may use standard infrastructure routes. Marketing canonical URLs and structured entity IDs use config marketing.url, never the incoming host; indexing is enabled by default only for APP_ENV=production. Head v0.2.2 requires individually typed schemas; submit linked @type entities separately rather than a top-level untyped @graph.
