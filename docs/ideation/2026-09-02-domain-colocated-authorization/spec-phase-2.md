# Implementation Spec: Domain-Colocated Authorization Foundation - Phase 2

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Promote `OrganizationMembership` from `Pivot` to a conventional first-class Eloquent `Model`. The migration has not run, so edit it in place to create the plural `organization_memberships` table, preserve its existing primary key, and add a unique `(organization_id, user_id)` constraint. Add typed relationships, a `User::membershipFor()` helper, an explicit Spatie `web` guard, and factories required by authorization tests.

Keep Spatie Teams disabled. `User` remains the global role subject and each membership becomes the tenant role subject through Spatie's polymorphic `model_has_roles` table. Deleting a parent through a database cascade would bypass membership model events and leave polymorphic role rows orphaned, so User and Organization deletion hooks must delete memberships through their models using `eachById()`, not offset-based `each()`.

This phase runs after Phase 1 on retry to avoid shared-tree validation contamination, but its implementation remains independent of the concrete catalog enums. Use a test-only web role when proving that a membership can receive and clean up role assignments.

## Decisions Considered and Rejected

- **One User identity with overlapping contexts** — rejected mutually exclusive Employee, Customer, and Subscriber types because a User may occupy several contexts over time.
- **User for global roles and OrganizationMembership for tenant roles** — rejected Spatie Teams because explicit membership subjects avoid mutable current-team state.
- **First-class membership model** — rejected retaining an incidental Pivot because the membership now has identity, relationships, authorization, factories, and lifecycle behavior.
- **Plural `organization_memberships` table and explicit `web` guard** — rejected a singular table override and implicit non-authenticatable guard resolution.
- **Model-driven membership deletion with `eachById()`** — rejected relying only on parent foreign-key cascades because polymorphic Spatie assignment rows have no membership foreign key.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Authorization/OrganizationMembershipTest.php`

**Playground**: Pest feature tests using real Eloquent models, factories, database constraints, deletion events, and Spatie assignment rows.

**Why this approach**: Database-backed model tests expose table naming, uniqueness, relationship, guard, and orphan-cleanup failures immediately.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `database/factories/OrganizationMembershipFactory.php` | Creates memberships with User and Organization relationships. |
| `tests/Feature/Authorization/OrganizationMembershipTest.php` | Covers identity, uniqueness, relationships, role subject behavior, and parent cleanup. |

### Modified Files

| File Path | Changes |
| --- | --- |
| `database/migrations/2026_09_01_205016_create_organization_membership_table.php` | Use plural table name and add the composite unique constraint. |
| `app/Models/OrganizationMembership.php` | Extend Model, use HasFactory/HasRoles, set web guard, and add belongs-to relationships. |
| `app/Models/User.php` | Add memberships relationship, membership lookup, and safe membership deletion. |
| `app/Models/Organization.php` | Add memberships relationship and safe membership deletion. |
| `database/factories/OrganizationFactory.php` | Add valid defaults for required organization columns. |

### Deleted Files

None.

## Implementation Details

### Membership Schema and Model

Use the existing migration because it has not been deployed:

```php
Schema::create('organization_memberships', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['organization_id', 'user_id']);
});
```

Update `down()` to drop the plural table.

`OrganizationMembership` must:

- extend `Illuminate\Database\Eloquent\Model`;
- use `HasFactory` and `HasRoles`;
- follow the project's `#[Fillable]` attribute convention for `organization_id` and `user_id`;
- declare the Spatie guard as `web` using the property shape accepted by installed Spatie Permission 8.3;
- return concrete `BelongsTo` types from `user()` and `organization()`.

Do not add soft deletion, invitation state, subscription data, metadata, or custom role tables.

**Feedback loop**:

- **Playground**: Start with one persisted membership and its two parent models.
- **Experiment**: Persist one pair, reject its duplicate, permit the same User in another Organization, assign a test-only role, then delete the membership.
- **Check command**: `php artisan test --compact tests/Feature/Authorization/OrganizationMembershipTest.php --filter='persists|duplicate|role'`

### Parent Relationships and Membership Resolution

Add:

```php
// User
public function organizationMemberships(): HasMany;
public function membershipFor(Organization $organization): ?OrganizationMembership;

// Organization
public function memberships(): HasMany;
```

`membershipFor()` must query the relationship with `whereBelongsTo($organization)` and return `null` when no membership exists. Do not cache current organization or membership in static state, singletons, or global scopes.

Do not add direct `belongsToMany` shortcuts that allow callers to bypass the first-class membership.

**Feedback loop**:

- **Playground**: One User with memberships in two Organizations plus a third unrelated Organization.
- **Experiment**: Resolve both matching memberships and confirm the unrelated lookup is null.
- **Check command**: `php artisan test --compact tests/Feature/Authorization/OrganizationMembershipTest.php --filter='relationship|resolves'`

### Polymorphic Assignment Cleanup

Spatie detaches model roles when `OrganizationMembership::delete()` fires, but a database cascade from User or Organization does not fire membership events. Add model deletion hooks on both parents that iterate memberships with `eachById()` and delete each membership model before the parent row is removed.

Use an explicit small chunk size such as 100 so a 101-membership regression test proves chunk traversal. Never use offset-based `each()` while deleting the iterated rows.

**Feedback loop**:

- **Playground**: Create role-bearing memberships for one parent across more rows than the configured chunk size.
- **Experiment**: Delete the parent and assert both membership rows and matching `model_has_roles` rows are gone; repeat for User and Organization parents.
- **Check command**: `php artisan test --compact tests/Feature/Authorization/OrganizationMembershipTest.php --filter='deleting'`

### Factories

Generate `OrganizationMembershipFactory` with Artisan and return relationship factories for `organization_id` and `user_id`. Complete `OrganizationFactory` with minimum valid values for required `name`, `metadata`, and `stripe_customer_id`; let the existing sluggable model derive `slug`.

Each test creates its own mutable data. Keep `beforeEach()` limited to configuration/cache reset. Do not add speculative factory states.

## Data Model

```sql
CREATE TABLE organization_memberships (
    id BIGINT PRIMARY KEY,
    organization_id BIGINT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE (organization_id, user_id)
);
```

Spatie assignment identity:

```text
model_has_roles.model_type = App\Models\OrganizationMembership
model_has_roles.model_id   = organization_memberships.id
```

## Testing Requirements

| Test File | Coverage |
| --- | --- |
| `tests/Feature/Authorization/OrganizationMembershipTest.php` | Primary key, conventional table, unique pair, relationships, lookup, explicit guard, role assignment, and orphan cleanup. |

Key cases:

- A membership persists with its own non-null ID.
- Duplicate User/Organization pair fails at the database constraint.
- One User may join multiple Organizations and one Organization may contain multiple Users.
- Parent and inverse relationships resolve the expected records.
- `membershipFor()` returns the correct row and null for a non-member.
- A membership accepts a web-guard test role and deleting it removes its role pivot.
- Deleting a User and deleting an Organization each clean more than one chunk of role-bearing memberships without skipping rows.

Rerun this file after every change to it.

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| Duplicate membership | Let the database unique constraint reject it; workflow-level translation is future scope. |
| Missing parent | Foreign keys reject invalid IDs. |
| Missing matching membership | Return null and let policy code deny. |
| Wrong role guard | Explicitly select `web` on the membership model. |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Model/table naming | Query targets missing singular/plural table | Model changes but migration does not | All membership access fails | Rename create/drop table and test persistence. |
| Membership uniqueness | Duplicate authority subjects | Repeated/concurrent membership creation | Conflicting role sets for one tenant | Composite unique constraint. |
| Guard resolution | Role mismatch | Membership infers no auth provider | Valid role assignment throws or denies | Explicit web guard and focused test. |
| Parent deletion | Orphaned model_has_roles rows | Database cascade bypasses membership events | Stale authorization data | Delete memberships through models before parent deletion. |
| Chunk traversal | Rows skipped | Offset-based iteration deletes its current page | Some role pivots remain | Use `eachById()` and a multi-chunk regression test. |

## Validation Commands

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Authorization/OrganizationMembershipTest.php
composer types:check
```

## Rollout Considerations

- This spec assumes the membership migration remains undeployed. If that changes, stop and create a forward migration.
- Run migrations before catalog synchronization or role assignment.
- No feature flag is needed.

## Open Items

None.

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
