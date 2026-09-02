# Authorization

- Use one User identity for overlapping Employee, Customer, and future Subscriber contexts; never add a mutually exclusive `users.type` for these identities.
- Attach global roles to User and tenant roles to OrganizationMembership; keep Spatie Teams disabled.
- Treat membership as the first-class `organization_memberships` model, and keep it on the `web` guard.
- Assign domain-local roles instead of direct model permissions, and authorize by checking permissions rather than role names.
- Define authorization in `app/Authorization/{Domain}/{Permission,Role,Catalog}.php`; every Catalog implements `AuthorizationCatalog` and is registered in `config/authorization.php`.
- Never create authorization definitions in a monolithic seeder, historical migrations, or application boot; run `authorization:sync`.
- Normal sync reports stale web-guard definitions; explicit prune fails when active assignments exist.
- Use `admin.view` only to admit the Admin surface; protect actions with transport-neutral domain capabilities.
- Tenant policies must check the matching membership and must never accept global User permissions as a bypass.
- Model subscription and product access with future entitlement models, not roles.
- Keep future Admin and Customer MCP servers separate, reuse domain permission enums, filter discovery with `shouldRegister()`, and authorize again in `handle()`.
- Design future machine and delegated agent identities separately before introducing them.
- Cover authorization changes with focused global, membership, and cross-tenant denial tests.
