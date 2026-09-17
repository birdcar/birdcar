# Walkthrough Offer Copy and Funnel Signals Contract

**Created**: 2026-09-17
**Readiness**: All 5 gates ready
**Status**: Approved
**Approval**: Express — single consolidated confirmation, no per-artifact review
**Supersedes**: None

## Problem Statement

The birdcar.dev marketing site sells a free, hour-long assessment that ends in a written report. The copy is calm, honest, and low-friction, and both Hormozi's value equation and Miner's NEPQ method reward that. Both frameworks also expose the same hole: the pages describe the reader's current pain vividly but never picture the after-state, never name the cost of leaving the work alone, and offer almost no proof that this particular person closes the gap. The offer is still comparable to any free consultation on every axis except the report the reader keeps. Several of the highest-leverage fixes were blocked on business decisions PRODUCT.md listed as open: report turnaround, an offer name, whether to promise a pitch-free hour, whether to name past employers, whether to state a fit disqualifier, and whether to mention the paid discovery week.

The site also records nothing about visitor behaviour. PostHog is installed server-side only: the PHP client captures exceptions and the request middleware sets context, but there is no browser client, so pageviews, booking-button clicks, and Cal.com embed events are invisible. The live deployment at birdcar.dev confirms one app bundle and no analytics script. Any copy change shipped today would ship blind, with no before and no after. The owner is building this business alongside full-time employment on 10 to 20 hours a week, starting from almost no lead volume, so the first bookings need to be attributable to a page, a placement, and a source.

## Goals

1. Every step of the visit-to-booking funnel emits a named PostHog event from the marketing host: pageview, booking CTA click carrying page path and placement, booking embed opened, and booking completed with no attendee personal data, with the owner's own browsers excluded through a persisted opt-out that can be reversed.
2. The assessment page carries ten copy changes: the no-pitch promise, a three-business-day report turnaround, a fit disqualifier, the people-first mechanism sentence, a reframed hour-limitation answer, one paid-week sentence, a report component named Where I'd start, one consequence line, one solution-awareness question, and one line answering the fear that the project is about replacing people.
3. The homepage asks the reader at least two questions, one consequence question and one solution-awareness question in the assessment strip; its personal note names GitHub, Heroku, and Zapier while deliberately omitting WorkOS as the current employer; and both the homepage and the assessment page contain at least as many you/your/yours words as I/me/my words once markup is stripped.
4. The offer is named The Walkthrough everywhere the old name appeared: desktop and mobile nav, every button and action note, page title, meta description, footer link, the Service schema, and the Cal.com slug in config, with no free assessment prose surviving in the conversion views or shared marketing components.
5. Standing constraints survive: the hero still says I help businesses and fifteen years, only Craft & Communicate appears as work, GHX and DataDash stay absent, and no scarcity or deadline language appears anywhere.

## Success Criteria

- [ ] Marketing copy tests pass with anchor assertions for the no-pitch promise, the three-business-day turnaround, the disqualifier, the mechanism sentence, the reframed hour answer, the paid-week sentence, Where I'd start, the consequence question, the solution-awareness question, the replacing-people line, GitHub, Heroku, and Zapier in the personal note, and The Walkthrough in title, nav, button, and Service schema. — check: `php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiscoveryTest.php` → exits 0
- [ ] Stripped of markup, the rendered homepage and assessment page each contain at least as many you/your/yours words as I/me/my words. phpunit.xml sets failOnEmptyTestSuite so a missing test fails this run. — check: `php artisan test --compact tests/Feature/MarketingSiteTest.php --filter='speaks to the reader'` → exits 0 and reports at least one test run
- [ ] The rendered homepage body asks the reader at least two questions. phpunit.xml sets failOnEmptyTestSuite so a missing test fails this run. — check: `php artisan test --compact tests/Feature/MarketingSiteTest.php --filter='asks the reader'` → exits 0 and reports at least one test run
- [ ] The marketing layout emits the PostHog token and host when PostHog is configured and enabled, and omits them when POSTHOG_DISABLED is true or the token is blank. The test pins the PostHog environment in phpunit.xml and disables app.debug for the blank-token case so PostHogService does not throw before the layout renders. — check: `php artisan test --compact tests/Feature/MarketingAnalyticsTest.php` → exits 0
- [ ] The production JavaScript bundle contains the three custom funnel event names, the opt-out query parameter, and the posthog-js opt-out call. — check: `bun run build >/dev/null 2>&1 && grep -lE 'booking_cta_clicked' public/build/assets/app-*.js && grep -lE 'booking_embed_opened' public/build/assets/app-*.js && grep -lE 'booking_completed' public/build/assets/app-*.js && grep -lE 'ph_opt_out' public/build/assets/app-*.js && grep -lE 'opt_out_capturing' public/build/assets/app-*.js` → exits 0 and prints the bundle path five times
- [ ] Config points at the new Cal.com slug and nothing under config, the marketing views, or the scripts still references the old free-assessment slug. — check: `grep -q 'cal.com/birdcar/walkthrough' config/marketing.php && ! grep -rq 'free-assessment' config resources/views resources/js` → exits 0
- [ ] No free assessment prose survives in the homepage, the assessment page, or the shared marketing components, including the mobile nav, footer link, default meta description, and invitation button. — check: `! grep -rEil 'free assessment' resources/views/pages/index.blade.php resources/views/pages/assessment.blade.php resources/views/components/marketing` → exits 0 with no matches
- [ ] No scarcity or deadline language appears in the marketing views. The voice read in the final criterion is the broader guard; this pattern catches the common forms. — check: `! grep -rEil 'limited (spots|places|slots|availability)|only [0-9]+ (spots|clients|places|slots)|(few|handful of) (spots|places|slots)|offer ends|book before|closes on|spots? left|this week only|while (I|there is|there.s) (still )?(have )?room|before (the|this) (month|week|quarter) (ends|is out)' resources/views/pages resources/views/components/marketing` → exits 0 with no matches
- [ ] With the dev server running and a real token in the local environment, loading the homepage with PostHog's debug switch, clicking the header CTA and the closing invitation CTA, and opening the embed logs booking_cta_clicked with page and placement properties and booking_embed_opened in the browser console; loading the site with the opt-out parameter and repeating the clicks logs no captures; loading it with the opt-in parameter restores capture. — judgment call: The implementer runs php artisan dev, opens a private window at http://localhost:8000/?__posthog_debug=true, performs the clicks and reads the PostHog debug log, then repeats after visiting /?ph_opt_out=1 and again after /?ph_opt_in=1. Local events land in the production PostHog project, so the private window's person is deleted at the gate.
- [ ] After the walkthrough Cal.com event exists and the release is deployed, one test booking made from a fresh private window that lands on the homepage, clicks a booking CTA, and completes the booking in the modal appears in PostHog as a single person with $pageview, booking_cta_clicked, booking_embed_opened, and booking_completed in order, and booking_completed carries no attendee name or email. — judgment call: The owner performs the booking from a fresh private window, confirms the four events in order on one person in PostHog Activity and inspects the booking_completed properties, then deletes the test person, cancels the booking, and only afterwards opts out everyday browsers with /?ph_opt_out=1.
- [ ] The final homepage, assessment page, and closing invitation read in the owner's voice under the seven copy-editing sweeps (clarity, voice and tone, so what, prove it, specificity, heightened emotion, zero risk) and the PRODUCT.md Written Voice notes, with no hype vocabulary. — judgment call: The owner reads the three rendered pages against the seven sweeps from the copy-editing skill and the PRODUCT.md Written Voice section before approving the release commit.

## Scope Boundaries

### In Scope

- Browser PostHog client via posthog-js, bundled with bun into the existing app.js, default localStorage-plus-cookie persistence, autocapture off, automatic pageview and pageleave on, no consent banner, initialised only when the layout emits a token and host — Without a browser client the funnel is invisible; the full package supplies automatic pageview, pageleave, UTM and referrer person properties, and a persisted opt-out without hand-written capture code.
- A read-only browserConfig accessor on PostHogService that returns the token and host when PostHog is enabled and null otherwise; the marketing layout emits data attributes from it and never re-checks config in Blade. phpunit.xml pins POSTHOG_PROJECT_TOKEN, POSTHOG_HOST, and POSTHOG_DISABLED, and the blank-token test disables app.debug so the service's debug-mode exception does not fire first — The service already computes enabled state from config; the layout should read it, not duplicate it, and the tests must not depend on the developer's .env.
- Funnel events: automatic $pageview; booking_cta_clicked keyed on a data-booking-cta placement marker present on every booking control including the assessment hero anchor and the inline nav link, carrying page path and placement; booking_embed_opened and booking_completed forwarded from Cal.com embed actions subscribed through Cal.ns[namespace]('on', ...), using bookingSuccessfulV2 for completion and forwarding only uid, eventTypeId, startTime, and status, never attendee fields; the opened action (linkReady or bookerViewed) is chosen after verifying inline versus modal timing on the dev server — These four steps are the whole visit-to-booking funnel, and keying clicks on the Cal.com attribute would miss the two controls that do not carry it.
- Owner opt-out and opt-in: visiting any marketing page with ?ph_opt_out=1 calls posthog.opt_out_capturing() and ?ph_opt_in=1 calls posthog.opt_in_capturing(), so the owner's browsers can be silenced and re-enabled for later live checks — At this traffic level the owner's own visits would dominate every insight, and an opt-out with no reversal strands the browser.
- Rename the offer to The Walkthrough in desktop and mobile nav, buttons, action notes, page title, meta description, footer link, and the Service schema; move config to the birdcar/walkthrough Cal.com slug and namespace — The owner chose to name the offer now; one name everywhere keeps the funnel to one Cal.com event.
- Assessment page copy, ten changes: no-pitch promise beside the CTA and in the FAQ, three-business-day turnaround in action notes and step three, fit disqualifier in the fit FAQ, people-first mechanism sentence in the opening, reframed hour-limitation answer, one paid-week sentence in the after-report FAQ, report component renamed Where I'd start, one consequence line and one solution-awareness question in the outcome section, one line answering the fear that the project is about replacing people — Each item closes a specific gap the review found under one or both frameworks.
- Homepage copy: a consequence question and a solution-awareness question in the assessment strip, reader-subject rewrites in the hero explanation and approach steps, GitHub, Heroku, and Zapier named in the personal note with WorkOS deliberately omitted, action notes updated for name and turnaround, hero introduction and fifteen years unchanged — The homepage currently asks the reader nothing and never names the cost of inaction.
- Shared closing invitation: disqualifier or no-pitch line under the heading, action note carrying the name and turnaround; the component keeps its filename and renders on the Work page by inheritance — The invitation repeats at every depth, so it is the cheapest place to prevent the two unhandled objections.
- Tests: updated string assertions, a reader-focus ratio test, a reader-question count test, a new MarketingAnalyticsTest for conditional PostHog emission, and failOnEmptyTestSuite enabled in phpunit.xml — The project's pass/fail lives in these tests, and filtered runs must fail when the test they name does not exist.
- Records: update PRODUCT.md Approved Launch Copy and Open Decisions and the .ai/rules/resources.md marketing scope rule with the name, slug, turnaround, commitments, and paid-week sentence — Without this the next agent reverts the copy to the recorded constraints.
- Move the page to /walkthrough with a permanent redirect from /assessment, renaming the Folio page, route name, sitemap entry, and tests — Makes the URL match the name; deferred from MVP because it multiplies file churn and the site was indexed only days ago.
- booking_fallback_clicked event for the plain Cal.com link shown when the embed does not load, and a mode property (inline or modal) on booking_embed_opened — Separates embed failures from real intent and tells the funnel which surface opened.
- PostHog funnel insight from $pageview to booking_completed, created at the human gate, with the project-level internal-user filter as a fallback for any browser the owner forgets to opt out — The funnel is only readable once the insight exists; the opt-out is the primary exclusion mechanism.

### Out of Scope

- The Craft & Communicate work story and its proof on the Work page, and all Writing pages — The work story is blocked on quotes and results the owner has not collected; the Work page still inherits the renamed shared invitation.
- Session replay and section-visibility events — The owner chose funnel events only; replay is a PostHog project setting and can be switched on later without code.
- Consent banner — The owner chose standard cookies without a banner for a US audience; adding a banner would contradict that and the design critique.
- Capacity scarcity and booking deadlines — PRODUCT.md keeps capacity internal and forbids invented urgency; consequence copy supplies urgency instead.
- Prices, a paid discovery week page, and guarantees beyond the no-pitch promise — Report boundaries, prices, and guarantees remain open decisions in PRODUCT.md.
- A/B testing of copy variants — Traffic cannot reach significance; the before/after is read from the trailing booking rate instead.
- Server-side PostHog capture behaviour: exception capture and request context — Both already work and are unrelated to the funnel; the service gains only a read-only accessor.
- A custom booking flow replacing the Cal.com embed — PRODUCT.md rules out a custom integration for launch.
- Installing Cal.com PostHog analytics app for this project — It adds only automatic pageview and autocapture inside the booking iframe under a separate anonymous identity, cannot emit a named booking event, and its autocapture could sweep booking-form input; it would pollute the funnel without adding a step.

### Future Considerations

- A/B tests on the hero and assessment opening once monthly traffic supports them
- Session replay on the marketing host to watch the Cal.com handoff
- Craft & Communicate quotes and measured outcomes on the Work page
- Price anchoring for the paid discovery week once its boundaries and price are decided
- A video sales letter on the Walkthrough page
- Carrying The Walkthrough name into the Cal.com confirmation and reminder emails
- A separate PostHog project for local development if local checks become frequent
- Server-side completion source: Cal.com BOOKING_CREATED webhook with a payload template to a signed Laravel route, forwarding only uid, eventTypeId, type, startTime, and status plus a hidden booking field prefilled from the URL with the PostHog distinct id so the event joins the browser person; attendee fields excluded. Webhook payloads carry no UTM or query parameters by default.

## Decisions Considered and Rejected

- **Ship instrumentation and copy in one release and read booking rate over the following 30 days as a trailing metric** — rejected: A numeric booking-rate lift target as the project goal. No baseline exists, so a lift target would either be invented or delay the copy by weeks while one accrues.
- **Standard posthog-js persistence with no consent banner** — rejected: PostHog cookieless mode. The owner wants cross-visit identity for the funnel and the option of replay available without a code change, and accepts the EU exposure at this traffic level.
- **Urgency is consequence copy only: another hire, another subscription, another hour of your evening** — rejected: Publishing the real client capacity ceiling, and inventing a booking window. PRODUCT.md keeps capacity internal and forbids invented scarcity; consequence questions supply honest urgency without a deadline.
- **Commit to all four decision-gated copy elements: no-pitch promise, report turnaround, fit disqualifier, past employer names** — rejected: Writing the copy around the open decisions. Each element unlocks a specific fix from the review, and writing around them would leave the offer comparable to any free consultation.
- **Report turnaround of three business days** — rejected: Within one week. The owner accepts the workload risk of two bookings landing in one week for a stronger time-delay lever.
- **One sentence about the paid discovery week, without a price, in the after-report FAQ answer** — rejected: Keeping the paid week off the site, and giving it a priced section. Anchors the free hour as step one of a real process without committing to boundaries or prices that remain open.
- **Name the offer The Walkthrough in this project** — rejected: Keeping free assessment, The Untangling Hour, and The Tuesday Walkthrough. The container word is what the prospect does in the hour and ties to the Tuesday line and step two; Untangling Hour read cute and Tuesday Walkthrough implied a weekday constraint.
- **Move config and tests to the birdcar/walkthrough Cal.com slug, with the owner creating the new event in Cal.com before deploy and retiring the old one after** — rejected: Keeping the free-assessment slug and only retitling the event. The owner wants the slug to match the name and accepts that the old booking URL stops working.
- **Funnel events only** — rejected: Session replay, and section-visibility events. Four events read the whole funnel; replay is a project setting and section events add noise at this traffic level.
- **Load PostHog through posthog-js installed with bun and bundled into app.js** — rejected: PostHog's CDN loader snippet, and posthog-js-lite. A pinned, bundled dependency avoids an external script tag and keeps the Cal.com event forwarding in the same module as the embed loader; the full package supplies automatic pageview, pageleave, UTM and referrer person properties, and persisted opt-out, which the funnel goal needs today and the lite package would require hand-writing.
- **Implement the reader-focus ratio and reader-question count as Pest tests** — They keep running after this project instead of living only in the contract's acceptance checks.
- **Rename the report's third component to Where I'd start** — rejected: Keeping Something to work from. The old label restated the report itself rather than adding a component; the new one commits the report to include a first recommendation.
- **Keep the /assessment URL in MVP and place the /walkthrough move with a redirect in the Full tier** — rejected: Moving the URL in MVP. The URL move multiplies file churn across Folio, sitemap, and tests, and the site was indexed only days ago.
- **Enable failOnEmptyTestSuite in phpunit.xml so the filtered acceptance runs fail when the named test does not exist** — rejected: Relying on the filtered php artisan test runs as written. Success-criteria critic blocker: PHPUnit exits 0 on an empty filtered suite, so the ratio and question criteria passed vacuously before the tests were written.
- **Gate runbook makes the live test booking from a fresh private window first and opts out everyday browsers afterwards, and the analytics module gains a matching opt-in parameter** — rejected: The original order of opting out before the test booking, with no opt-in reversal. Hidden-dependency critic blocker: an opted-out browser emits nothing, so the live check would fail against working instrumentation and leave the browser stranded.
- **Sequential execution: instrumentation, then rename, then copy, then the human gate** — rejected: Running instrumentation and rename in parallel. Hidden-dependency critic: both phases write the marketing layout, and neither declared the other as a prerequisite.
- **Key CTA click capture on a dedicated data-booking-cta placement marker on every booking control** — rejected: Keying on the existing data-cal-link attribute. Hidden-dependency critic: the assessment hero anchor and the inline nav link carry no data-cal-link, so two controls would never register a click.
- **Drop the admin and customer host clause from the PostHog emission scope and criterion** — rejected: A host check in the layout and a test for it. Three critics: only the marketing layout renders the bundle, Folio is bound to the marketing host, and the discovery tests already prove those hosts return 404 for marketing pages.
- **Target the Full tier; the completion event comes from the parent-page embed forwarding, and Cal.com PostHog app is not used** — rejected: The Stretch tier webhook route, and installing Cal.com PostHog app to emit the completion event. The owner selected Full. Verification of calcom source on 2026-09-17 showed the Cal.com PostHog app only injects the stock posthog-js snippet inside the booking page with no capture call, no event-name or metadata fields, and no identify or distinct-id pass-through, so it cannot emit booking_completed and would create a second anonymous person per booking page load. The embed bookingSuccessfulV2 callback on the parent page is the only source that keeps the funnel on one person.
- **Execute directly on main with no isolation branch** — rejected: An ideation/2026-09-17-walkthrough-offer-and-funnel-signals isolation branch. Owner decision on 2026-09-17, consistent with the standing rule that marketing-site work commits directly to main with linear history; the express finish keeps its strict, fail-closed phase gates without the branch.

## Execution Plan

_Added during Phase 5 handoff. Pick up this contract cold and know exactly how to execute._

### Dependency Graph

```
Funnel instrumentation
  ├── Offer rename  (blocked by Funnel instrumentation)
        └── Conversion copy and records  (blocked by Offer rename)
  └── Cal.com event, deploy, live funnel check  (blocked by Funnel instrumentation, Offer rename, Conversion copy and records)
```

### Execution Steps

**Run the project** (recommended) — autopilot reads this contract, plans dependency waves, runs independent phases in parallel, and gates on failure:

```bash
/ideation:autopilot docs/ideation/2026-09-17-walkthrough-offer-and-funnel-signals/contract.md
```

**Or run it unattended** — a `/goal` is a durability wrapper around the same autopilot run: Claude re-checks the condition before it is allowed to stop, so failures get repaired and re-run. Generated by `contract-gen --print-goal`; this is the only copy of that string:

```
/goal Drive the Walkthrough Offer Copy and Funnel Signals contract (2026-09-17-walkthrough-offer-and-funnel-signals) to completion with /ideation:autopilot.

1. Run `/ideation:autopilot docs/ideation/2026-09-17-walkthrough-offer-and-funnel-signals/contract.md`.
2. It dispatches a BACKGROUND workflow. Wait for the completion notification — never start a second autopilot run while one is in flight.
3. Then run the ideation plugin's `scripts/verify.mjs` against `docs/ideation/2026-09-17-walkthrough-offer-and-funnel-signals/contract-data.json` and leave its VERIFY line in the conversation. Resolve the plugin's install directory first — `${CLAUDE_PLUGIN_ROOT}/scripts/verify.mjs` is a placeholder, not a shell variable, and bash will not expand it. That line is the only evidence this goal is judged on.
4. If anything failed, fix the spec or the implementation and go back to step 1. Autopilot skips phases that already have commits.

Done when the most recent VERIFY line reads fail=0 and commits=3/3 — or when two consecutive VERIFY lines are identical and still failing, in which case name the failing checks and stop, because a contract whose checks have rotted must not trap the run.
```

**Or run phases manually** in dependency order:

**Strategy**: Sequential

1. **Phase 1** — Funnel instrumentation _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-17-walkthrough-offer-and-funnel-signals/spec-phase-1.md
   ```

2. **Phase 2** — Offer rename _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-17-walkthrough-offer-and-funnel-signals/spec-phase-2.md
   ```

3. **Phase 3** — Conversion copy and records _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-17-walkthrough-offer-and-funnel-signals/spec-phase-3.md
   ```

4. **Phase 4** — Cal.com event, deploy, live funnel check _(blocking)_

   ```bash
   # Review: Cal.com event, deploy, live funnel check
   ```

---

_This contract was generated from brain dump input. Review and approve before proceeding to specification._
