---
paths:
  - 'app/Authorization/**'
---

# Authorization

## Root Admin bootstrap roles
The operator invitation provisions the first/root Admin with an explicit bundle of global domain roles: admin.access and publishing.author currently. Future Admin modules extend that bundle deliberately. Reuse an existing User and preserve unrelated roles/credentials; do not create a User type, enable Teams, grant direct permissions or use a blanket Gate::before bypass. Root Admin capabilities must not bypass tenant membership checks.
