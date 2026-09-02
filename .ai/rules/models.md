---
paths:
  - 'app/Models/{User,Organization,OrganizationMembership}.php'
---

# Models

## Bulk membership assignment cleanup
Parent deletion must bulk-delete OrganizationMembership authorization-assignment rows from both Spatie polymorphic pivot tables, scoped by the membership morph class and a membership-ID subquery, then rely on the membership foreign-key cascade. Do not iterate and delete memberships one model at a time; that makes deletion query count grow with organization size.

## Transactional parent deletion
Delete User and Organization instances with `deleteOrFail()` or inside an explicit `DB::transaction()` so Spatie assignment cleanup, the parent delete, and membership foreign-key cascades commit or roll back together. Never mass-delete or use `deleteQuietly()` for these parents because those paths bypass the model cleanup events.
