# Domain-Colocated Authorization Foundation Contract

**Created**: 2026-09-02
**Readiness**: All 5 gates ready
**Status**: Approved
**Approval**: Express — single consolidated confirmation, no per-artifact review
**Supersedes**: None

## Problem Statement

Birdcar needs one authenticated User identity to support overlapping access contexts: Employees with platform-wide Admin capabilities, Customers with permissions limited to a specific OrganizationMembership, and Subscribers who may have no organization. The current User model has global Spatie roles, while OrganizationMembership is an empty custom pivot and organization authorization is deny-all, so tenant-scoped permissions are not yet represented.

As the application grows, one large seeder or permission-creation script would centralize unrelated authorization knowledge, become difficult to review, and encourage UI- or transport-specific permission names. The foundation must let each business domain own a small typed catalog while preserving one explicit, safe database synchronization path and preventing global Employee permissions from leaking into tenant access.

## Goals

1. Make a new authorization domain additive: implementation requires a domain Permission enum, Role enum, Catalog class, and one config registry entry without modifying the synchronization command.
2. Represent platform-wide permissions on User and organization-scoped permissions on a first-class OrganizationMembership so multiple role assignments produce the union of their permissions without crossing organization boundaries.
3. Provide an idempotent authorization:sync command that owns the complete role and permission catalog for the configured web guard, applies exact role-permission mappings, reports stale definitions by default, and only prunes unassigned stale records when explicitly requested.
4. Establish real Admin-entry and tenant-isolation behavior with Pest coverage, while keeping permission names reusable by future Admin UI, Customer UI, HTTP API, and separate Admin and Customer MCP servers.
5. Persist the authorization architecture as a custom Laravel Boost AI guideline that is discovered and composed into project agent instructions for future ideation and implementation work.

## Success Criteria

- [ ] Running authorization:sync repeatedly produces the same permissions, roles, and role-permission mappings without duplicate rows or additional changes. — check: `php artisan test --compact tests/Feature/Authorization/AuthorizationSyncCommandTest.php --filter=idempotent` → exits 0 and the second synchronization leaves the persisted authorization graph unchanged
- [ ] Only catalogs listed in config/authorization.php are loaded, and the Admin and Organizations catalogs create their exact enum-backed definitions and mappings for the configured web guard. — check: `php artisan test --compact tests/Feature/Authorization/AuthorizationSyncCommandTest.php --filter=catalogs` → exits 0 with registered catalogs synchronized, an unregistered fixture catalog ignored, and definitions for other guards untouched
- [ ] Duplicate role or permission names across registered catalogs fail before any authorization records are written. — check: `php artisan test --compact tests/Feature/Authorization/AuthorizationSyncCommandTest.php --filter=collisions` → exits 0 after proving collisions produce a failing command result and leave the authorization tables unchanged
- [ ] The Admin access role grants admin.view to a User, the named admin.index route requires authentication, and an authenticated User without that permission receives no Admin access. — check: `php artisan test --compact tests/Feature/Authorization/AdminAuthorizationTest.php` → exits 0 for permitted, unauthenticated, and authenticated-but-forbidden cases
- [ ] OrganizationMembership is a first-class web-guard authorization subject backed by organization_memberships, retains its primary key, and rejects duplicate organization-user memberships. — check: `php artisan test --compact tests/Feature/Authorization/OrganizationMembershipTest.php` → exits 0 after persisting a membership identity and observing a database constraint violation for a duplicate organization-user pair
- [ ] Assigning the Organizations viewer and editor roles to one OrganizationMembership gives that membership the union of organizations.view and organizations.update. — check: `php artisan test --compact tests/Feature/Authorization/OrganizationAuthorizationTest.php --filter=composes` → exits 0 and both permissions are inherited through separate membership roles
- [ ] A membership permission applies only to its own organization and never authorizes the same User in another organization. — check: `php artisan test --compact tests/Feature/Authorization/OrganizationAuthorizationTest.php --filter=isolates` → exits 0 with access allowed for the matching membership and denied for the other organization
- [ ] A global User assignment of an Organizations role does not bypass membership-based tenant authorization, and a User with no memberships remains valid but receives no tenant access. — check: `php artisan test --compact tests/Feature/Authorization/OrganizationAuthorizationTest.php --filter=global` → exits 0 with global Admin-side capability preserved and tenant access denied without the matching membership
- [ ] Normal authorization:sync reports catalog-managed roles and permissions that are stale without deleting them or their assignments. — check: `php artisan test --compact tests/Feature/Authorization/AuthorizationSyncCommandTest.php --filter=reports` → exits 0 with stale records listed and persisted
- [ ] authorization:sync --prune deletes unassigned stale definitions but refuses to delete stale definitions that still have active role or model assignments. — check: `php artisan test --compact tests/Feature/Authorization/AuthorizationSyncCommandTest.php --filter=prune` → exits 0 with unassigned records removed and assigned records reported without deletion
- [ ] A custom Laravel Boost authorization guideline is discovered from .ai/guidelines and its composed output includes the approved subject, catalog, synchronization, tenant-isolation, and MCP-boundary rules. — check: `php artisan test --compact tests/Feature/Authorization/AuthorizationGuidelineTest.php` → exits 0 after proving Boost discovers the custom guideline and composes every required architectural invariant
- [ ] The focused authorization suite and PHPStan analysis pass after changed PHP files have been formatted with Pint. — check: `composer types:check && php artisan test --compact tests/Feature/Authorization` → both commands exit 0; the implementation validation steps run vendor/bin/pint --dirty --format agent before this check

## Scope Boundaries

### In Scope

- Convert OrganizationMembership into a first-class role-bearing Eloquent model, change the unmigrated table definition to organization_memberships while preserving its existing primary key, add User and Organization relationships, and enforce a unique organization-user membership constraint. — The membership is the tenant authorization subject and now has relationships and behavior of its own; using the plural table avoids a hidden model table override.
- Keep global Spatie roles on User, add Spatie roles to OrganizationMembership with an explicit web guard, and keep teams disabled. — Spatie's polymorphic assignments can distinguish global User authority from organization-scoped membership authority without mutable current-team state or ambiguous guard resolution.
- Add a shared AuthorizationCatalog contract, enum-backed definitions, and an explicit config/authorization.php catalog registry. — This is the stable, searchable extension contract that replaces a monolithic role and permission script.
- Add minimal Admin and Organizations catalogs: admin.access grants admin.view; organizations.viewer and organizations.editor grant separate organizations.view and organizations.update capabilities. — These definitions exercise surface entry, global capabilities, membership composition, and tenant isolation without anticipating unfinished workflows.
- Add an idempotent authorization:sync command that treats all Spatie role and permission rows for the configured web guard as catalog-owned, validates catalog collisions before writing, upserts definitions, synchronizes exact role mappings, and refreshes Spatie's cache. — Birdcar owns all role definitions, so guard-scoped ownership provides one explicit and testable synchronization boundary without adding tracking tables.
- Report web-guard definitions absent from registered catalogs during normal sync and support fail-closed pruning of only unassigned stale records through --prune. — Code remains the declared source of truth without turning ordinary deploys into silent access revocations or touching definitions for other guards.
- Add a named GET admin.index placeholder endpoint and require authentication plus admin.view for the Admin domain group using the Admin catalog enum rather than an untracked string. — The existing group has no requestable route and lacks an explicit authentication boundary, so a neutral no-content endpoint is needed to exercise the foundation without designing the Admin UI.
- Implement membership-only OrganizationPolicy checks and the model relationships needed to resolve the matching membership explicitly. — Tenant authorization must not infer access from global User roles or ambient organization state.
- Add factories and focused Pest coverage for catalog synchronization, global Admin access, additive membership roles, tenant isolation, users without memberships, and pruning safety. — Authorization and cross-tenant boundaries require executable regression evidence before the foundation is reused.
- Add .ai/guidelines/authorization.md as a custom Laravel Boost AI guideline, verify its discovery and composition with one focused test, and regenerate the project agent instructions with boost:update. — Future ideation runs and coding agents must inherit the authorization subjects, catalog conventions, synchronization rules, tenant-isolation boundary, and future MCP direction; the existing catalog behavior tests already cover the PHP structure itself.

### Out of Scope

- Subscriber subscription, purchase, and entitlement models — No product or billing access semantics exist yet; commercial access should not be represented as roles.
- Admin MCP and Customer MCP server, tool, OAuth, or agent identity implementation — The servers will be separate and share domain capabilities, but their tool inventories and credential models are future projects.
- Machine principals, delegated-agent tokens, or token-level permission narrowing — The catalog remains principal-neutral, but agent identity and auditing require a separately designed security boundary.
- Organization-authored custom roles or role editors — Birdcar owns role definitions and the product is not targeting enterprise customization.
- A broad User, Organization, or membership CRUD permission matrix — Those workflows do not exist yet; only representative permissions needed to prove the foundation are defined.
- Role hierarchies, deny permissions, cross-domain roles, and assignment presets — Authorization is intentionally additive through several small domain-local roles.
- Automatic filesystem catalog discovery or role and permission writes during application boot — Explicit registration and command-driven synchronization are easier to trace and safe under Octane.
- Separate Employee, Customer, and Subscriber User models or a mutually exclusive users.type column — These access contexts may overlap and should be derived from global roles, memberships, and future entitlements.

### Future Considerations

- Add subscription and entitlement models when paid-content and product access rules are known.
- Build separate Admin and Customer MCP servers whose tools reuse domain permission enums, hide unavailable tools through shouldRegister(), and authorize again during handle().
- Choose delegated-user or independent machine-principal authentication and auditing when autonomous agent requirements are concrete.
- Add additional business-domain catalogs as product workflows are implemented.

## Decisions Considered and Rejected

- **Use one User identity with overlapping access contexts.** — rejected: Mutually exclusive Employee, Customer, and Subscriber user types. A Subscriber can later gain an OrganizationMembership without losing subscription history, and one person may occupy several contexts simultaneously.
- **Use User as the global authorization subject and OrganizationMembership as the tenant authorization subject.** — rejected: Spatie Teams as the primary tenant abstraction. Explicit membership checks preserve the domain invariant and avoid mutable current-team state in web requests, jobs, and Octane workers.
- **Treat OrganizationMembership as a first-class Eloquent model.** — rejected: Keep it as an incidental custom Pivot. The membership has its own identity, metadata, authorization relationships, lifecycle, factories, and policies.
- **Organize authorization catalogs by business domain.** — rejected: Organize strictly by Eloquent model or application surface. Business capabilities often span models and should remain reusable across Admin UI, customer UI, APIs, and MCP tools.
- **Represent each domain with backed Permission and Role enums plus a small Catalog implementing a shared contract.** — rejected: One class per definition or untyped arrays. Enums provide type-safe names accepted by Spatie while the Catalog provides one cohesive mapping boundary.
- **Register catalogs explicitly in config/authorization.php.** — rejected: Filesystem auto-discovery or service-provider tagging. A short explicit registry is searchable and predictable without adding reflection or container ceremony.
- **Synchronize catalogs through an explicit idempotent authorization:sync command.** — rejected: Domain seeders, schema migrations, or application-boot writes. A dedicated command gives production synchronization clear semantics and remains safe for long-running Octane processes.
- **Report stale records by default and prune only through --prune.** — rejected: Automatic strict deletion or silent additive-only synchronization. Operators can see drift without ordinary deploys deleting access or stale data accumulating invisibly.
- **Refuse to prune stale definitions that still have active assignments.** — rejected: Cascade active assignment deletion after confirmation or without confirmation. Intentional access revocation should use an explicit transition or data migration rather than a catalog maintenance command.
- **Keep roles domain-local and compose authority by assigning several roles.** — rejected: Cross-domain roles or broad persona roles. Small local bundles preserve catalog ownership and match the requested additive authorization model.
- **Use admin.view only as the Admin surface-entry permission and use domain capabilities for concrete actions.** — rejected: Prefix every business action with admin or duplicate permissions per transport. The same business capability can be authorized consistently from Admin UI, APIs, and future MCP tools.
- **Plan separate Admin and Customer MCP servers that reuse domain permission names.** — rejected: One mixed-context MCP server or separate permission vocabularies. Separate tool inventories and tenant-resolution paths preserve trust boundaries while shared capabilities avoid duplicated policy semantics.
- **Defer agent identity while keeping catalogs principal-neutral.** — rejected: Implement delegated tokens or machine-principal models in this foundation. Agent credential, narrowing, revocation, and audit requirements are not concrete enough to justify security infrastructure now.
- **Implement Admin access and tenant isolation as the first real behaviors.** — rejected: A speculative full CRUD matrix or catalog infrastructure with no integration. These boundaries prove the architecture against current routes and models without inventing unfinished product rules.
- **Persist the approved authorization architecture as a custom Laravel Boost AI guideline.** — rejected: Rely on the ideation contract or implicit code conventions alone. Boost-composed project guidance makes the decisions visible to future ideation sessions and agentic development before authorization files are changed.
- **Treat every Spatie role and permission for the configured web guard as catalog-owned.** — rejected: Add an authorization ownership manifest or columns to package tables. Birdcar defines all roles, so guard-scoped ownership makes stale detection deterministic without new persistence machinery.
- **Use the plural organization_memberships table and an explicit web permission guard on OrganizationMembership.** — rejected: Keep the singular pivot table through a model override or rely on implicit guard resolution. The database has not been migrated, so adopting first-class model conventions now removes two hidden dependencies.
- **Add a neutral named admin.index endpoint for authorization verification.** — rejected: Claim route-level Admin coverage while the protected route group contains no requestable route. A no-content endpoint proves the boundary without prematurely designing the Admin application.
- **Fold durable AI guidance into the access-boundary phase and remove the separate architecture-convention test.** — rejected: Create a standalone documentation infrastructure phase and duplicate catalog behavior coverage. The custom Boost guideline is an explicit user goal, but one focused composition test and the existing catalog tests are sufficient.
- **Verify behavior and static analysis separately from Pint's mutating formatter.** — rejected: Use git diff --exit-code after formatting as a success criterion. The formatter is an implementation validation step, while the acceptance command must not confuse intended uncommitted changes with formatting failures.
- **Serialize the two foundation phases during the retry.** — rejected: Retry both phases concurrently in one shared working tree. The first strict run proved that concurrent builders contaminate one another's diffs and reviewers even when their declared feature files do not overlap; Phase 2 now follows Phase 1 before integration.

## Execution Plan

_Added during Phase 5 handoff. Pick up this contract cold and know exactly how to execute._

### Dependency Graph

```
Domain Catalogs and Safe Synchronization
  ├── Membership Authorization Principal  (blocked by Domain Catalogs and Safe Synchronization)
  └── Admin and Tenant Access Boundaries  (blocked by Domain Catalogs and Safe Synchronization, Membership Authorization Principal)
```

### Execution Steps

**Run the project** (recommended) — autopilot reads this contract, plans dependency waves, runs independent phases in parallel, and gates on failure:

```bash
/ideation:autopilot docs/ideation/2026-09-02-domain-colocated-authorization/contract.md
```

**Or run it unattended** — a `/goal` is a durability wrapper around the same autopilot run: Claude re-checks the condition before it is allowed to stop, so failures get repaired and re-run. Generated by `contract-gen --print-goal`; this is the only copy of that string:

```
/goal Drive the Domain-Colocated Authorization Foundation contract (2026-09-02-domain-colocated-authorization) to completion with /ideation:autopilot.

1. Run `/ideation:autopilot docs/ideation/2026-09-02-domain-colocated-authorization/contract.md`.
2. It dispatches a BACKGROUND workflow. Wait for the completion notification — never start a second autopilot run while one is in flight.
3. Then run the ideation plugin's `scripts/verify.mjs` against `docs/ideation/2026-09-02-domain-colocated-authorization/contract-data.json` and leave its VERIFY line in the conversation. Resolve the plugin's install directory first — `${CLAUDE_PLUGIN_ROOT}/scripts/verify.mjs` is a placeholder, not a shell variable, and bash will not expand it. That line is the only evidence this goal is judged on.
4. If anything failed, fix the spec or the implementation and go back to step 1. Autopilot skips phases that already have commits.

Done when the most recent VERIFY line reads fail=0 and commits=3/3 — or when two consecutive VERIFY lines are identical and still failing, in which case name the failing checks and stop, because a contract whose checks have rotted must not trap the run.
```

**Or run phases manually** in dependency order:

**Strategy**: Sequential retry: build and commit catalog synchronization, then membership authorization, then integrate both into Admin and tenant boundaries and persist their conventions in Boost guidance.

1. **Phase 1** — Domain Catalogs and Safe Synchronization _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-02-domain-colocated-authorization/spec-phase-1.md
   ```

2. **Phase 2** — Membership Authorization Principal _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-02-domain-colocated-authorization/spec-phase-2.md
   ```

3. **Phase 3** — Admin and Tenant Access Boundaries _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-02-domain-colocated-authorization/spec-phase-3.md
   ```

---

_This contract was generated from brain dump input. Review and approve before proceeding to specification._
