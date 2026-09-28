---
paths:
  - 'app/Settings/**'
---

# Settings

## Publishing agent settings are owner-configured through one gated page
Pause and per-role model overrides in PublishingAgentSettings change only through the Publishing settings section (admin.settings.publishing), gated by publishing.configure-agents via the publishing.author role. Authorize in mount and in every Livewire mutation; Livewire::test skips route middleware. Resolve settings per request/job (they are scoped), never store the Settings object or provider config in public component state, and show credentials only as a configured boolean. An empty selection unsets the override; picking a model, even the current recommendation, pins it.

## New Admin configuration is a registered Settings section
Admin settings live in one shell (/settings, layouts.settings) with a grouped rail. Add a section by writing an App\Settings\Sections\*Section class implementing SettingsSection (key, group, label, description, icon, permission, component), listing it in config/admin.php settings.sections (rail order), and building its Livewire SFC under resources/views/components/admin/settings/ with #[Layout('layouts.settings')]. Routes, rail and landing derive from the registry; never add a module-local settings page or sidebar item. Call app(SettingsSections::class)->authorize(Section::class) in mount and every mutation. Use x-admin.settings.group (one save bar per group, saves isolated) and x-admin.settings.row; deployment-owned values are read-only rows with an Environment badge, credentials show only configured/not configured.
