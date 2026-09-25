---
paths:
  - 'app/Settings/**'
---

# Settings

## Publishing agent settings are owner-configured through one gated page
Pause and per-role model overrides in PublishingAgentSettings change only through Admin Publishing Settings (admin.publishing.settings), gated by publishing.configure-agents via the publishing.author role. Authorize in mount and in every Livewire mutation; Livewire::test skips route middleware. Resolve settings per request/job (they are scoped), never store the Settings object or provider config in public component state, and show credentials only as a configured boolean. An empty selection unsets the override; picking a model, even the current recommendation, pins it.
