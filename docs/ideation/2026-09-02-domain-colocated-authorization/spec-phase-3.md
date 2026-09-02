# Implementation Spec: Domain-Colocated Authorization Foundation - Phase 3

**Contract**: ./contract.md
**Estimated Effort**: L

## Technical Approach

Integrate the catalogs and membership principal at two explicit boundaries. Admin entry authenticates a User and checks a global User permission. Tenant authorization resolves the matching `OrganizationMembership` and checks permissions on that membership; a global User assignment of the same Organizations role must never bypass the tenant policy.

Add a neutral named `admin.index` endpoint returning no content so the existing Admin domain can be tested without designing UI. `routes/web.php` already receives the web middleware group, so add `auth` and use Spatie's enum-aware `PermissionMiddleware::using(AdminPermission::View)` instead of duplicating `web` or embedding an untracked string.

Implement only OrganizationPolicy `view` and `update`; leave unfinished abilities denied. Add policy-level permission-matrix tests and a small HTTP wiring test. Persist the architecture in `.ai/guidelines/authorization.md`, verify Boost discovers and composes it, and run `boost:update --no-discover --no-interaction` to update generated project instructions. MCP servers, tools, OAuth, and agent identities remain future work.

## Decisions Considered and Rejected

- **Global roles on User and tenant roles on OrganizationMembership** — rejected Spatie Teams because explicit subjects prevent ambient-tenant and cross-context leakage.
- **`admin.view` for surface entry; domain permissions for actions** — rejected Admin- or transport-prefixed duplicates because UI, API, and MCP should share business capabilities.
- **Separate future Admin and Customer MCP servers sharing capabilities** — rejected one mixed-context server and duplicate permission vocabularies because tool inventories differ while business semantics do not.
- **Membership-only tenant policy** — rejected a global Employee bypass because internal authority must not silently expose customer tenant data.
- **Custom Boost guideline** — rejected relying on the contract or implicit conventions because future ideation and agents need the rules upfront.
- **Deferred agent identity** — rejected inventing delegated tokens or machine principals before credential, narrowing, revocation, and audit requirements exist.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Authorization/AdminAuthorizationTest.php tests/Feature/Authorization/OrganizationAuthorizationTest.php tests/Feature/Authorization/AuthorizationGuidelineTest.php`

**Playground**: Pest HTTP/policy tests and Laravel Boost's actual GuidelineComposer.

**Why this approach**: Focused executable tests verify security boundaries and guidance composition faster and more reliably than manual UI or generated-file inspection.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `tests/Feature/Authorization/AdminAuthorizationTest.php` | Admin route authentication and global permission wiring. |
| `tests/Feature/Authorization/OrganizationAuthorizationTest.php` | Role composition, policy matrix, tenant isolation, and global-role bypass rejection. |
| `.ai/guidelines/authorization.md` | Durable custom Laravel Boost authorization rules. |
| `tests/Feature/Authorization/AuthorizationGuidelineTest.php` | Boost discovery and composition coverage. |

### Modified Files

| File Path | Changes |
| --- | --- |
| `routes/web.php` | Add auth, enum-backed Admin permission middleware, and named no-content Admin endpoint. |
| `app/Policies/OrganizationPolicy.php` | Authorize view/update only through the matching membership. |
| `AGENTS.md` | Regenerate the Boost block with custom authorization guidance. |
| `CLAUDE.md` | Regenerate the Boost block with custom authorization guidance. |

### Deleted Files

None.

## Implementation Details

### Admin Entry Route

Use the existing Admin domain group and Spatie Permission 8.3's backed-enum middleware helper:

```php
Route::domain('admin.birdcar.dev')
    ->name('admin.')
    ->middleware([
        'auth',
        PermissionMiddleware::using(AdminPermission::View),
    ])
    ->group(function (): void {
        Route::get('/', fn (): Response => response()->noContent())
            ->name('index');
    });
```

Retain existing placeholder subgroups. Confirm the exact Laravel 13 response return type. Do not add a dashboard view, redirect, route-domain abstraction, or broad Admin CRUD catalog.

**Feedback loop**:

- **Playground**: Requests sent to `admin.birdcar.dev` from a focused HTTP test.
- **Experiment**: Guest, authenticated User without roles, User with unrelated Organizations role, and User with Admin Access.
- **Check command**: `php artisan test --compact tests/Feature/Authorization/AdminAuthorizationTest.php`

### Membership-Only OrganizationPolicy

Implement:

```php
public function view(User $user, Organization $organization): bool
{
    return $user->membershipFor($organization)
        ?->hasPermissionTo(OrganizationPermission::View) ?? false;
}

public function update(User $user, Organization $organization): bool
{
    return $user->membershipFor($organization)
        ?->hasPermissionTo(OrganizationPermission::Update) ?? false;
}
```

Keep `viewAny`, `create`, `delete`, `restore`, and `forceDelete` denied. Do not add Gate::before or User-global fallbacks.

**Feedback loop**:

- **Playground**: Gate/policy tests with two Users and at least two Organizations.
- **Experiment**: No membership, no role, viewer, editor, both roles, wrong-organization membership, and direct User-global Organizations role.
- **Check command**: `php artisan test --compact tests/Feature/Authorization/OrganizationAuthorizationTest.php`

### Custom Laravel Boost Guideline

Create `.ai/guidelines/authorization.md` as concise imperative guidance. It must state:

1. One User identity supports overlapping Employee, Customer, and future Subscriber contexts; never introduce a mutually exclusive `users.type` for these.
2. Global roles attach to User; tenant roles attach to OrganizationMembership; Spatie Teams stays disabled.
3. Membership is a first-class `organization_memberships` model using the `web` guard.
4. Assign domain-local roles, never direct model permissions, and check permissions rather than role names.
5. Definitions live in `app/Authorization/{Domain}/{Permission,Role,Catalog}.php`; Catalog implements `AuthorizationCatalog` and is registered in `config/authorization.php`.
6. Never create definitions in one monolithic seeder, historical migrations, or application boot; run `authorization:sync`.
7. Normal sync reports stale web-guard definitions; explicit prune fails when active assignments exist.
8. `admin.view` only admits the Admin surface; actions use transport-neutral domain capabilities.
9. Tenant policies check the matching membership and never accept global User permissions as a bypass.
10. Subscription/product access belongs to future entitlement models, not roles.
11. Future Admin and Customer MCP servers remain separate, reuse domain permission enums, filter discovery with `shouldRegister()`, and authorize again in `handle()`.
12. Future machine/delegated agent identities require separate design.
13. Authorization changes require focused global, membership, and cross-tenant denial tests.

Verify discovery through `Laravel\Boost\Install\GuidelineComposer`: `used()` contains `.ai/authorization`, and composed guidance contains stable markers for each rule category. Then run:

```bash
php artisan boost:update --no-discover --no-interaction
```

Do not hand-edit generated Boost blocks in `AGENTS.md` or `CLAUDE.md`.

**Feedback loop**:

- **Playground**: Real GuidelineComposer from the Laravel test container.
- **Experiment**: Verify discovery key and subject/catalog/sync/tenant/subscriber/MCP markers; remove one marker during the test-first loop and observe failure.
- **Check command**: `php artisan test --compact tests/Feature/Authorization/AuthorizationGuidelineTest.php`

## Data Model

No schema changes. Callers choose the authorization subject explicitly:

```text
Admin/global: User -> role -> permission
Tenant: User -> matching OrganizationMembership -> role -> permission
```

The same Organizations role may be assigned to either subject; the policy decides which subject is authoritative.

## API Design

No MCP implementation. Future boundary:

```text
AdminMcpServer: authenticated User + global permissions
CustomerMcpServer: authenticated User + explicit OrganizationMembership
```

Different servers own different tool inventories while sharing domain permission enums. Tool visibility never replaces authorization inside `handle()`.

## Testing Requirements

| Test File | Coverage |
| --- | --- |
| `tests/Feature/Authorization/AdminAuthorizationTest.php` | Guest, unprivileged, unrelated-role, and Admin Access requests. |
| `tests/Feature/Authorization/OrganizationAuthorizationTest.php` | Viewer/editor union, policy matrix, wrong tenant, no membership, and global-role bypass denial. |
| `tests/Feature/Authorization/AuthorizationGuidelineTest.php` | Boost custom-guideline discovery and required rule composition. |

Key cases:

- Guest cannot reach `admin.index` under the current auth response contract.
- Authenticated User without `admin.view` is forbidden; Admin Access succeeds with no content.
- Viewer may view but not update; Editor may update but not view; assigning both grants both.
- Another Organization's membership grants nothing.
- Directly assigning Organizations Viewer to User makes the global permission true but leaves tenant policy denied without membership.
- A User without memberships remains valid and denied tenant abilities.
- Boost discovers the guideline and composes every required category.

Policy tests own the permission matrix; the HTTP test proves route wiring only.

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| Guest Admin request | Let auth middleware use current Fortify/web behavior. |
| Missing Admin permission | Let Spatie middleware return forbidden. |
| Missing/wrong membership | Policy returns false without global fallback. |
| Guideline not discovered | Focused test fails before generated instructions are accepted. |
| Boost updates unrelated generated content | Review diff; keep deterministic current-package output and never hand-edit its managed block. |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Admin route | Marketing route is tested instead | Host omitted | False-positive authorization test | Request/assert Admin host and route name. |
| Admin middleware | Web mistaken for auth | Explicit auth omitted | Unclear guest boundary | Add auth and cover guest response. |
| Tenant policy | Global privilege leakage | Policy calls User::can() | Employee/agent reaches arbitrary customer data | Check only matching membership. |
| Tenant policy | Wrong membership selected | Lookup omits target Organization | Cross-tenant authorization | `membershipFor($organization)` plus negative test. |
| Guidance | File exists but is not loaded | Wrong path or update omitted | Future agents miss decisions | Test GuidelineComposer and commit generated blocks. |
| Future MCP | shouldRegister treated as authorization | Tool checks visibility only | Direct invocation can bypass filtering | Guideline requires both discovery and handle checks. |

## Validation Commands

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Authorization/AdminAuthorizationTest.php
php artisan test --compact tests/Feature/Authorization/OrganizationAuthorizationTest.php
php artisan test --compact tests/Feature/Authorization/AuthorizationGuidelineTest.php
php artisan boost:update --no-discover --no-interaction
composer types:check
php artisan test --compact tests/Feature/Authorization
```

## Rollout Considerations

- Run migrations, then `php artisan authorization:sync --no-interaction`; never include `--prune` in unattended deployment.
- No feature flag is required.
- No MCP/OAuth change ships in this phase.
- Revert route/policy/guideline changes to roll back; do not prune during rollback.

## Open Items

None.

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
