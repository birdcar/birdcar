---
version: 1
slug: "es-views-components-admin-settings-shell-blade-php"
primary_target: "resources/views/layouts/settings.blade.php"
related_targets: ["routes/admin.php","config/admin.php","resources/views/layouts/admin.blade.php","resources/views/components/admin/settings/⚡index.blade.php","resources/views/components/admin/settings/⚡mail.blade.php","resources/views/components/admin/settings/⚡publishing.blade.php","resources/views/components/admin/settings/group.blade.php","resources/views/components/admin/settings/row.blade.php","resources/css/admin.css"]
---

# Admin — Settings shell

Scope: one Settings shell in Admin holding Application settings and Your account. Operate mode for the owner's short configuration visits; sole operator for now, future staff with partial access handled only by permission filtering. Confirmed through shape on 2026-09-27; comp-led. Approved comp: `.impeccable/mocks/decision/settings/rail.png` (surface seed aded0637, card "Settings rail"). Registry mechanism approved 2026-09-27: section classes implementing a SettingsSection contract, listed in config/admin.php.

## Direction contract

THESIS: One grouped rail, one row anatomy, one save footer per group; every future configuration area plugs into the same shell. Refuses scattered module-local settings pages and card-stacked forms with one page-bottom Save.

OWN-WORLD: Admin's Flux Pro and Inter; zinc-50 module sidebar, white rail with hairline edge, teal current pill, hairline label-left/control-right rows, neutral Environment badges, deep-teal primary Save.

STORY: Open Settings from the sidebar foot, pick a section in the rail, see what each value is and who owns it, change it, save only that group.

FIRST VIEWPORT: 256px module sidebar, Settings current at its foot; 56px "Settings" header; 232px rail, Application above Your account, Mail current; section column: title, description, Admin mail rows, environment and credential rows, Marketing mail beginning; the dirty group's footer pinned at the bottom.

FORM: Settings rail, position 4 of 6; surface seed aded0637. Registry: section classes listed in config/admin.php (owner-approved).

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Placement

- Sidebar: Home, Publishing, spacer, Settings (gear) pinned above the account button. Mail leaves the sidebar. Account menu adds "Your account" → Profile, above Log out.
- `/settings/{section}` (Application), `/settings/account/{section}` (Your account); `/settings` opens the first permitted section. `/mail` → `/settings/mail` and `/publishing/settings` → `/settings/publishing` redirect permanently. Publishing's header keeps a trailing Settings link for users who can configure agents.
- Grouped rail (~232px): Application, then Your account; current item uses the teal pill; a group with no permitted items is not rendered.

## Extension contract

- Each domain declares its sections like authorization catalogs: key, group, label, description, icon, order, required permission, component. Rail, mobile list, and landing derive from it; rail visibility, route middleware, and component mount use the same permission. Adding a section is one definition, one component, one permission; no shell edits. Present the Laravel-native registry option (config-registered catalog vs. class attributes) to the owner before implementing it.
- Section anatomy: title plus one line on what it controls and when changes apply; setting groups (heading, muted line); rows with label/help left (~40%) and control right (~60%), hairline-separated. Row kinds: editable, environment fact (mono value + `Environment` badge), credential (configured / not configured + `Environment` badge), action (button for a flow).
- Save model (owner decision): per setting group. Each group has its own dirty state and a save footer ("N unsaved changes · Discard · Save changes") that appears only while that group is dirty and sticks to the viewport bottom while the group is on screen. One group's validation error never blocks another group. Leaving with any dirty group prompts. Security flows are self-contained actions, not footers.

## Launch sections

Only these ship; no placeholder sections and no search field.

- Mail: Admin mail and Marketing mail groups (From name, From address, Reply-to optional); mailer as environment fact; Resend key as credential. Keep existing domain-validation copy and cross-surface isolation.
- Publishing: Agent requests (pause switch, existing scope copy, OpenRouter credential) and Models by task (nine rows: recommendation, select, pinned/reset-required state, reset), saved independently. The former "Saved: On" badge becomes a row-level state line.
- Profile: name and email via Fortify; changing email requires password confirmation (email verification is off; email is the login).
- Security: password change; two-factor enable → QR, setup key, confirm code → recovery codes shown once with copy/download; later view codes (password-confirmed), regenerate, disable; passkeys list (name, added, last used), add, remove with confirmation. Password confirmation is a focused dialog.
- Preferences: appearance Light/Dark/System stays Flux's per-browser storage, labeled "On this device" (owner decision); timezone stored on the user and used as the default for Publishing's per-release timezone picker.

## States and responsive

Hidden when unpermitted; direct URL 403; every mutation re-authorizes. Footer states dirty → saving → saved (brief, then hides) → error ("Couldn't save. Your changes are still here."). Missing credentials show "Not configured" and name the environment variable, never an input. Passkeys has a teaching empty state. Rail scrolls independently past ~12 items. Native Flux dark mode with cyan accent; reduced motion drops the footer slide. Below `lg`: module drawer, `/settings` becomes a full-width section list, section pages get a back link, rows stack, the footer pins to the viewport. At 1280 with the sidebar expanded the column still fits; collapse stays the owner's choice.

## Boundaries and deferred

Flux Pro, Inter, `admin.css` tokens; Livewire 4 components under `components/admin/settings/`; explicit Laravel routes, no Folio; settings resolved per request; credentials boolean only. Move the existing Mail and Publishing settings tests to the new routes and add redirect tests; add account tests for profile, password, 2FA lifecycle, and passkeys with denial cases. Deferred: a find field reserved at the rail top once sections reach ~10, log out other sessions, and mounting account sections on the customer site.
