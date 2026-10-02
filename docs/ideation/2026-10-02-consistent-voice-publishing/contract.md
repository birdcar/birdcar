# Consistent Voice Publishing Contract

**Created**: 2026-10-02
**Readiness**: All 5 gates ready
**Status**: Approved
**Approval**: Express — single consolidated confirmation, no per-artifact review
**Supersedes**: None

## Problem Statement

The owner wants every Birdcar post to sound like him and to serve the same business goals: owners of established service businesses as the reader, one plain Walkthrough invitation, the outcome chain, and the claims and exclusion rules. Today the publishing workflow asks him to tick voice samples from previous posts for each article, and that mechanism barely works. Excerpts are cut to about 220 characters before the 1,200-character cap applies. The dashboard Develop path starts the interview with only the idea, so research, plan, draft and the automatic reviews receive an empty voice_context. No agent instruction tells a role how to use the samples. None of his style guide, public register, blog channel rules, narrative frameworks or PRODUCT.md positioning reaches any prompt, and BuyerReviewer is told not to invent buyer facts without being told who the buyer is.

The result is that voice and goals vary per post and the owner catches drift by hand. The pipeline also has structural gaps for producing marketable output: the raw idea becomes the article's slug and fallback title instead of a working summary with the real title set later as metadata; one idea can produce exactly one manuscript, with no distribution kit or sibling posts; the Planner has no notion of post type or narrative framework; mechanical rules such as the em dash ban, banned vocabulary, title and meta lengths and the single /walkthrough link are left to LLM attention; and there is no board showing where each piece sits across stages.

The owner's voice profile already exists in unusual detail (bat-kol style.md, registers/public.md, channels/blog.md, the new-post framework library and the marketingskills and Hormozi distillations), and the ten-agent pipeline with human angle, plan and release gates already exists. The work is to decompose that profile into what each role needs, freeze it into every activity, enforce what is mechanical deterministically, and refine the existing pipeline's stages so one idea reliably becomes several on-voice, on-goal pieces.

## Goals

1. G1 Inputs: every editorial activity kind freezes the active canon's version id and content hash, its own role rubric and the shared goals block into its input; Drafter and VoiceReviewer also receive the fixed exemplar canon labeled voice-only, and for blog pieces 2-4 retrieved exemplar sections; the per-post voice sample checklist no longer exists.
2. G2 Mechanical rules: every mechanical rule tagged in the canon has exactly one deterministic lint rule with a seeded-violation fixture that fires and a clean fixture that does not, every lint rule is tagged in the canon and stated in the Drafter's instructions, and title, SEO title, meta and slug rules are enforced once and shared by packaging and release.
3. G3 Drift detection: on a golden set of 10 real canon or site-copy passages and 10 slopped variants of the same passages, lint plus VoiceReviewer flag at least 9 of 10 distinct slopped passages at the injected span and raise a drift finding on no more than 2 of 10 distinct real ones, measured against the current canon hash.
4. G4 Pieces: one idea yields a flagship blog post plus LinkedIn, Bluesky and email pieces and any number of owner-requested sibling posts, each its own card with its own stage; the idea's summary title never becomes a piece's title or slug, and the real title and slug are set at packaging.
5. G5 Owner acceptance: the owner runs three real ideas end to end and judges each flagship and its kit publishable with light edits.
6. G6 Baseline: the edit distance between each agent-authored revision and the owner's next saved revision is recorded per piece, as a measured baseline with no target.
7. G7 Structure: every plan records a post type, a primary narrative framework from the blog channel's allowed list for that type and a runner-up with reasons, and the Drafter receives the chosen framework file in full.
8. G8 Canon ownership: the owner can change the canon in Admin without a deploy, the next activity freezes the new version, and a downloaded export committed to the repo keeps fresh environments and tests in step.

## Success Criteria

- [ ] The whole test suite runs on Postgres (birdcar_test on Herd, same host and port as development) locally and on a pgvector-enabled Postgres service in CI, with no SQLite configuration left in phpunit.xml or the CI workflow. — check: `php artisan test --compact tests/Feature/Publishing/ContentIdeaTest.php && ! grep -nE 'sqlite|:memory:' phpunit.xml .github/workflows/tests.yml` → exits 0; the suite switch lands at the start of Phase 1, before any model change
- [ ] Capturing an idea creates a ContentIdea with a summary title and a flagship blog-post piece with a provisional slug; no piece title, label or slug is ever derived from the idea's summary; every imported archive essay keeps its published title, slug and URL. Phase 4 adds the case where packaging sets the real title and slug. — check: `php artisan test --compact tests/Feature/Publishing/ContentIdeaTest.php tests/Feature/Publishing/ArticleSlugTest.php tests/Feature/Publishing/ArchiveMigrationTest.php` → exits 0; ContentIdeaTest is created in Phase 1 and extended in Phase 4
- [ ] For every case of EditorialActivityKind, the outbound request body contains the active canon version id and content hash, that role's rubric and the shared goals block; Drafter and VoiceReviewer requests also contain the fixed exemplar canon labeled voice-only. — check: `php artisan test --compact tests/Feature/Publishing/EditorialInputContractTest.php` → exits 0; the dataset is built from EditorialActivityKind::cases(), so a new kind without a contract fails; created in Phase 2
- [ ] The per-post voice sample checklist, its state and its mentions in agent instructions and the manual model trial are gone. — check: `! grep -rniE 'voice[_ ]?samples?' app resources tests/Manual` → exits 0 (no matches)
- [ ] Canon version 1 is the repo rubric files plus a sha256 manifest; its hash is the version id frozen into inputs; no frozen input or canon file references a ~/.config path; approved homepage and Walkthrough copy are a dated snapshot inside the canon. — check: `php artisan test --compact tests/Feature/Publishing/VoiceCanonTest.php` → exits 0; created in Phase 2
- [ ] The brief records a post type and target query; the plan records a primary framework from the blog channel's allowed list for that type, a runner-up with reasons and an optional single-section secondary; disallowed or length-excluded frameworks fail validation; changing the framework at the plan gate re-runs planning; the Drafter receives the chosen framework file in full. — check: `php artisan test --compact tests/Feature/Publishing/NarrativeFrameworkTest.php` → exits 0; created in Phase 4
- [ ] The set of mechanical rule ids tagged in the canon equals the set of registered lint rule ids in both directions; each rule has a seeded-violation fixture that fires and a clean fixture that passes; every lint rule id is stated in the Drafter's instructions and schema description. — check: `php artisan test --compact tests/Feature/Publishing/VoiceLintTest.php` → exits 0; created in Phase 3
- [ ] After drafting, lint runs automatically and at most two self-repair passes touch only agent-authored blocks; owner-edited or protected blocks receive findings with proposed patches that are never auto-applied; release approval is blocked while unresolved lint findings remain unless each is overridden with a recorded reason. — check: `php artisan test --compact tests/Feature/Publishing/VoiceLintWorkflowTest.php tests/Feature/Publishing/ReleaseReadinessTest.php` → exits 0; repair cap, protected-prose, override-with-reason and release-block cases covered
- [ ] FactReviewer, VoiceReviewer and BuyerReviewer each freeze a distinct canon rubric file, pairwise different by hash, carrying its lens's section markers (claims and exclusions; voice, craft and framework adherence; buyer, commercial structure and discovery); reconciliation and recheck receive the findings and reviewed text they judge. — check: `php artisan test --compact tests/Feature/Publishing/ReviewRubricTest.php tests/Feature/Publishing/EditorialReviewTest.php` → exits 0; ReviewRubricTest is created in Phase 4
- [ ] The Packager activity proposes ten title options plus an H1, a 50-60 character SEO title, a 150-160 character meta description, a 4-6 word slug and a greatest-hits tag through the existing metadata proposal-acceptance path; the shared metadata lint rules (including archive-slug reuse) block release through CheckArticleRelease findings. — check: `php artisan test --compact tests/Feature/Publishing/PackagingTest.php` → exits 0; created in Phase 4
- [ ] Exemplar indexing chunks non-canon archive essays and pipeline-published blog pieces by section, embeds them with a request to OpenRouter's /api/v1/embeddings using openai/text-embedding-3-small at 1536 dimensions through the dedicated openai-compatible provider, and freezes 2-4 retrieved sections into Drafter and VoiceReviewer inputs for blog pieces; canon essays and site copy are never indexed; vector queries never receive raw strings. — check: `php artisan test --compact tests/Feature/Publishing/ExemplarRetrievalTest.php` → exits 0 on the Postgres suite; asserts the outbound embeddings request with Http::fake and Http::assertSent; created in Phase 5
- [ ] The vector migration enables pgvector and creates the vector column and HNSW index, and whereVectorSimilarTo returns the expected nearest sections. — check: `php artisan test --compact --fail-on-skipped tests/Feature/Publishing/ExemplarVectorSearchTest.php` → exits 0 with no skipped tests against birdcar_test on Herd Postgres; CI runs the same suite against a pgvector service; created in Phase 5
- [ ] Approving a flagship release freezes its approved claim manifest and spawns LinkedIn, Bluesky and email kit pieces drafted from the approved post; kit lint enforces channel limits; kit checks reject claims outside the flagship's claim manifest; a kit piece can be marked Posted only after its flagship is Published; sibling posts created from an idea run the full post path including their own angle gate. — check: `php artisan test --compact tests/Feature/Publishing/DistributionKitTest.php tests/Feature/Publishing/SiblingPieceTest.php` → exits 0; created in Phase 6
- [ ] The publishing board shows columns derived from each piece's stage, allows only the single-action drags (Ideas to Shaping starts development, Ready to Scheduled opens the release dialog, any column to Parked or Killed with a reason), refuses drags across angle, plan or release gates, nests kit cards under their flagship, and shows each card's live agent status. — check: `php artisan test --compact tests/Feature/Publishing/PublishingBoardTest.php` → exits 0; created in Phase 7
- [ ] The board renders and behaves correctly in the browser at desktop and mobile widths in light and dark mode, including drag feedback, refused gate drags and status chips. — judgment call: The owner reviews the board at admin.birdcar.test in desktop and mobile widths, light and dark, after Phase 7; source and Livewire assertions do not count as this check.
- [ ] Canon versions in the database are immutable; saving an Admin edit creates and activates a new version and re-activating an older one is the rollback; activities keep the version they froze; the deploy-time sync creates a version only when the repo hash is new, and auto-activates it only when the active version is the previous repo-sourced one; the editor rejects a save that drops a tagged lint rule id from the Drafter rubric; the Admin download reproduces the active version as the repo file tree. — check: `php artisan test --compact tests/Feature/Publishing/CanonEditorTest.php` → exits 0; created in Phase 8
- [ ] Each agent-authored revision followed by an owner-saved revision records a word-level edit distance on the piece. — check: `php artisan test --compact tests/Feature/Publishing/EditDistanceTest.php` → exits 0; created in Phase 9
- [ ] The paid golden-set drift trial, run against the current canon, meets G3's thresholds computed from its recorded attempts. — check: `jq -e --arg h "$(php artisan publishing:canon-hash)" '.canon_hash == $h and ([.attempts[].passage_id] | unique | length) == 20 and ([.attempts[] | select(.variant == "slopped" and .caught_at_injected_span) | .passage_id] | unique | length) >= 9 and ([.attempts[] | select(.variant == "real" and .drift_flagged) | .passage_id] | unique | length) <= 2' docs/ideation/2026-10-02-consistent-voice-publishing/drift-validation.json` → exits 0; the file is written only by DRIFT_TRIAL=1 vendor/bin/pest tests/Manual/VoiceDriftTrialTest.php against the local Postgres test database, run by the owner at the acceptance gate
- [ ] Three real ideas each produced a flagship and full kit that the owner recorded as publishable, with an edit distance captured for every piece. — check: `jq -e '(.ideas | length) >= 3 and all(.ideas[]; .verdict == "publishable" and ([.pieces[].kind] | sort) == ["blog_post","bluesky_thread","email_blurb","linkedin_post"] and all(.pieces[]; .edit_distance != null))' docs/ideation/2026-10-02-consistent-voice-publishing/acceptance.json` → exits 0 once the owner records acceptance.json at the gate
- [ ] The flagships and kits read as the owner's voice and serve the stated goals. — judgment call: The owner reads all three flagships and their kits in Admin and records the verdict and notes in acceptance.json at the acceptance gate.
- [ ] PRODUCT.md's Written Voice section defers to or matches the canon and no longer contradicts it. — judgment call: The owner reads PRODUCT.md § Written Voice against the canon after Phase 2.
- [ ] The test suite, Pint and static analysis pass. — check: `composer test` → exits 0

## Scope Boundaries

### In Scope

- ContentIdea model owning pieces: an idea has a summary title and notes; Article becomes the piece with a kind and optional parent; pieces use a provisional slug until packaging; no title or slug derives from the idea — Fixes the idea-becomes-title bug, and doing the data model now means existing articles migrate once instead of again when kit and sibling pieces arrive
- Voice-and-goals canon version 1: style, public register, blog channel, allowed narrative frameworks and framework guide, humanizer and editor passes, marketingskills sweeps, Hormozi structure, PRODUCT.md positioning and the goals block decomposed into per-role rubric files in the repo with mechanical rules tagged by id, frozen into every activity by sha256 manifest hash — The core ask: the same voice and goals reach every role on every post
- Fixed exemplar canon of four essays (Just build it twice, Your AI wrote a bug, Six months of talking to a machine, The tools that build the tools) plus a dated snapshot of approved homepage and Walkthrough copy, labeled voice-only — Replaces per-post sample picking with a stable reference set; research shows few-shot gains saturate after a handful
- Remove the voice sample checklist and its state, update the manual model trial, and reconcile PRODUCT.md's Written Voice section with the canon — The checklist is the mechanism being replaced, and contradictory voice records mislead future work
- Post type and narrative framework selection: brief records post type and target query; Planner proposes a primary framework from the channel's allowed list plus a runner-up; owner chooses at the plan gate; Drafter receives the framework file — G7; post-type templates and frameworks are bat-kol's structural lever for consistent essays
- Deterministic PHP voice linter with at most two agent self-repair passes and a release block with per-finding override — G2; self-repair is what keeps G5's light edits light, since the owner otherwise fixes every mechanical hit by hand
- Sharper three-lens review with distinct rubrics, plus a Packager activity that proposes titles, SEO title, meta, slug and greatest-hits tag through the existing metadata acceptance path, with the approved claim manifest frozen at release — Keeps the agreed three-lens batch while giving each reviewer a rubric it can apply consistently, and sets the real title late as metadata (G4); the greatest-hits tag is deliberately packaging metadata
- Retrieved exemplars: 2-4 sections per blog brief from non-canon archive essays and pipeline-published pieces, through the SDK's Embeddings, a dedicated OpenRouter openai-compatible provider and pgvector, with an owner-run backfill command — G1; the owner chose canon plus retrieval and wants the SDK's built-in embeddings used rather than avoided
- Distribution kit pieces (LinkedIn, Bluesky, email blurb) drafted from the approved flagship by a KitDrafter role using the fixed canon's short-form examples, plus sibling posts from the same idea — G4; the end goal is one idea becoming several marketable pieces
- Kanban publishing board with stage-derived columns, single-action drags, Parked and Killed lanes, nested kit cards and live status chips — Owner asked to rethink the flow around phases on a board; blogbot's users repeatedly complained when work looked hung
- Admin canon editor with immutable database versions, save-activates and rollback, a hash-deduplicated deploy-time sync from repo files, and a download of the active version for committing — G8; the owner chose Admin-edited versions winning over repo files
- Golden-set drift trial (manual, paid, owner-run against local Postgres) and edit-distance capture — G3 and G6
- Canon version diff view in Admin — Useful when editing the canon, not needed to edit it
- Edit-distance trend per piece kind on the board — Only meaningful once several pieces have accumulated
- Retrieved short-form exemplars for kit pieces — No published kit pieces exist to retrieve until the kit has been used for a while

### Out of Scope

- Automated posting to LinkedIn, Bluesky or an email service — Owner choice; the app drafts kit pieces and the owner copies them out and marks them Posted
- Idea-generation feeds such as daily proposals or source sweeps — Owner choice; ideas come from the owner
- Serving the canon to other tools (bat-kol Slack, email or GitHub drafting, MCP exposure) — Owner choice for now; the canon replacing bat-kol is the stated long-term direction
- Learning rules from the owner's edits or automatic canon updates — Owner choice; edit distance is measured only, and earlier contracts rejected silent voice learning
- Vale or any external prose-linting binary — Adds a binary to Laravel Cloud and a dependency; PHP rules run in the existing queue job and get Pest fixtures
- Fine-tuning a model on the owner's corpus — Corpus is small and fine-tuning adds model lock-in; exemplars plus rubrics come first
- Changing archived essays' titles, slugs, dates, URLs or text — The blog channel and PRODUCT.md require the archive to stay exactly as published
- Closing the 2026-09-22 three-piece pilot — Its criteria (piece-type mix, archive fidelity, spend) differ from G5; close or supersede it separately

### Future Considerations

- The canon replaces bat-kol as the source of truth for every channel and is exposed to other tools over MCP
- Cluster agent-to-owner correction pairs into owner-approved canon rule proposals
- Rank ideas by search and AI-visibility gap signals
- Automated distribution once kit quality is proven

## Decisions Considered and Rejected

- **One idea owns many pieces: a flagship blog post, LinkedIn, Bluesky and email kit pieces, and owner-requested sibling blog posts, each with its own stage** — rejected: A flagship plus kit only, or sibling posts only; and the 2026-09-22 contract's exclusion of derivative generation. The owner's end goal is one idea becoming potentially several marketable pieces
- **Keep Article as the piece model with a kind and parent link, and add a ContentIdea model that owns the summary title** — rejected: Renaming Article to a new Piece model. Reuses revisions, releases, attempts and their tests; the rename would churn every publishing file for no behavior change
- **Decompose bat-kol into repo-owned per-role rubric files as canon version 1; Admin-edited database versions win once the editor exists; a download of the active version is committed back to the repo** — rejected: Syncing from bat-kol by heading manifest, the repo always winning, or Admin-only storage after import. Production cannot read ~/.config, role decomposition is the point, and the owner wants the app's canon to eventually replace bat-kol
- **A fixed exemplar canon of four 2026 essays plus approved site copy, with 2-4 sections retrieved per blog brief by the SDK's built-in embeddings from non-canon archive essays and pipeline-published pieces** — rejected: A fixed canon only; per-post picking; the 2026-09-22 contract's rejection of an embeddings index; and indexing the canon essays and site copy already frozen in full. The owner chose canon plus retrieval and noted the earlier avoidance of built-in embeddings had to be fixed afterward; the over-engineering critic showed indexing frozen material retrieves what every input already holds
- **Embeddings use a dedicated openai-compatible provider entry keyed to the OpenRouter key and base URL, with openai/text-embedding-3-small at 1536 dimensions, set as the embeddings default; vector queries always receive precomputed embeddings** — rejected: OpenAI direct or Gemini embeddings, and the shipped default_for_embeddings of openai. Keeps OpenRouter as the only key and spending authority, as the 2026-09-24 contract decided; passing raw strings would silently embed through the default provider
- **Several reviewers: keep the three lenses with sharper, distinct role rubrics, plus deterministic lint ahead of them** — rejected: One general reviewer, or blogbot's single agent with one advisory critic. Mechanical, judgment and reconciliation checks fail differently; research supports decomposed criteria and external grounding over one holistic critic
- **Refine the existing stages: add an Ideas column, lint with at most two self-repair passes, a packaging step and kit pieces spawned at release approval; keep every current stage and gate** — rejected: Merging research into planning, or dropping the angle gate. The owner wants the pipeline refined into a seamless workflow, not replaced
- **Packaging runs as a Packager activity whose metadata goes through the existing proposal-acceptance path, with metadata rules implemented once as lint rules that CheckArticleRelease enforces** — rejected: A separate accepted-package gate and a new EditorialStage value, with the length rules validated twice (the pre-critic plan). The over-engineering critic showed CheckArticleRelease and metadata proposal acceptance already guard these fields; the owner's Package step survives as an activity on the board
- **Deterministic lint findings block release unless each is overridden with a recorded reason; LLM style findings stay advisory** — rejected: Lint as advisory only, matching the 2026-09-22 decision exactly, or a hard block with no override. Mechanical rules like the em dash ban are absolute in the voice profile, but quoted sources can legitimately contain a banned word
- **Implement the linter as native PHP rules, with every mechanical canon rule tagged by id and checked against registered lint rules in both directions** — rejected: Vale, and a lint dataset that only lists implemented rules. No external binary or dependency; the two-way id check means a canon rule cannot silently lack enforcement
- **Self-repair rewrites only agent-authored blocks; owner-edited blocks get findings with proposed patches that are never auto-applied** — Preserves the 2026-09-22 rule that agents never silently change the owner's prose
- **Release approval freezes the flagship's approved claim manifest; kit pieces are drafted from the approved flagship, may not add claims outside that manifest, and can be marked Posted only after the flagship is Published** — Keeps facts tied to checked claims and follows the blog channel rule against linking a post before it is published; the hidden-dependency critic found no claim-set artifact existed
- **The Planner selects a primary narrative framework and a runner-up from the blog channel's allowed list for the post type; the owner chooses at the plan gate; PHP validates the allowed list and length exclusions** — rejected: Leaving structure implicit in the outline, or scoring with framework-guide's topic tables as written. Mirrors bat-kol's top-two selection; the topic tables were tuned for the old practitioner audience and the channel's per-type list supersedes them
- **Board columns follow stage; drags are allowed only for single human actions; crossing a gate opens the workspace** — rejected: Dragging across a gate approves it, or a read-only board. The owner should approve the angle, plan and exact release text where he can read them
- **Measure drift with 10 real and 10 slopped passages, passing at 9 of 10 caught and at most 2 of 10 real flagged, recomputed from recorded attempts and pinned to the current canon hash** — rejected: A pairwise which-is-Nick judge, owner acceptance alone, or trusting the harness's own summary fields. Tests the pipeline's own checks and goes stale automatically when the canon changes
- **The MVP is the data model, canon, frameworks, lint, sharper reviewers and packaging; the board, kit, siblings, retrieval, canon editor and drift trial are Full** — rejected: Everything as MVP, or the board first. Owner choice; voice and goals consistency is the core and the board is a view over stages that must exist first
- **The whole test suite moves from SQLite in memory to Postgres: birdcar_test on Herd locally (same host and port as development) and a pgvector-enabled Postgres service in CI; the vector migration calls Schema::ensureVectorExtensionExists() with no driver guard; the drift trial uses the same database** — rejected: One retrieval test on the SQLite suite with embeddings faked (the pre-critic plan), a pgsql-guarded vector migration with only the vector tests on Postgres (the critic-fix plan), or a PHP-side similarity fallback. Both the hidden-dependency and success-criteria critics showed SQLite throws on vector columns; the owner chose production parity over keeping the suite on SQLite
- **Canon version 1 in Phases 2-7 is the repo files frozen by sha256 manifest hash; database versions, the Admin editor and a deploy-time sync arrive in Phase 8 after every rubric exists, deduplicating by hash and auto-activating a repo version only when the active version is the previous repo-sourced one** — rejected: Seeding immutable database versions in Phase 2 (the pre-critic plan), which would strand the Phase 4 and Phase 6 rubrics outside already-seeded environments. The hidden-dependency critic showed no path carried later rubrics into existing environments, and the over-engineering critic showed a versions table with one unchangeable row does no work before the editor exists

## Execution Plan

_Added during Phase 5 handoff. Pick up this contract cold and know exactly how to execute._

### Dependency Graph

```
Content ideas and pieces
  └── Voice and goals canon in every prompt  (blocked by Content ideas and pieces)
        ├── Deterministic voice lint  (blocked by Voice and goals canon in every prompt)
              ├── Frameworks, sharper reviewers and packaging  (blocked by Deterministic voice lint)
                    ├── Distribution kit and sibling pieces  (blocked by Frameworks, sharper reviewers and packaging)
                          └── Publishing board  (blocked by Distribution kit and sibling pieces)
                                └── Owner voice acceptance  (blocked by Publishing board, Canon editor, Drift trial harness and edit distance)
                    └── Drift trial harness and edit distance  (blocked by Frameworks, sharper reviewers and packaging, Exemplar retrieval)
              └── Canon editor  (blocked by Deterministic voice lint, Frameworks, sharper reviewers and packaging, Distribution kit and sibling pieces)
        └── Exemplar retrieval  (blocked by Voice and goals canon in every prompt)
```

### Execution Steps

**Run the project** (recommended) — autopilot reads this contract, plans dependency waves, runs independent phases in parallel, and gates on failure:

```bash
/ideation:autopilot docs/ideation/2026-10-02-consistent-voice-publishing/contract.md
```

**Or run it unattended** — a `/goal` is a durability wrapper around the same autopilot run: Claude re-checks the condition before it is allowed to stop, so failures get repaired and re-run. Generated by `contract-gen --print-goal`; this is the only copy of that string:

```
/goal Drive the Consistent Voice Publishing contract (2026-10-02-consistent-voice-publishing) to completion with /ideation:autopilot.

1. Run `/ideation:autopilot docs/ideation/2026-10-02-consistent-voice-publishing/contract.md`.
2. It dispatches a BACKGROUND workflow. Wait for the completion notification — never start a second autopilot run while one is in flight.
3. Then run the ideation plugin's `scripts/verify.mjs` against `docs/ideation/2026-10-02-consistent-voice-publishing/contract-data.json` and leave its VERIFY line in the conversation. Resolve the plugin's install directory first — `${CLAUDE_PLUGIN_ROOT}/scripts/verify.mjs` is a placeholder, not a shell variable, and bash will not expand it. That line is the only evidence this goal is judged on.
4. If anything failed, fix the spec or the implementation and go back to step 1. Autopilot skips phases that already have commits.

Done when the most recent VERIFY line reads fail=0 and commits=9/9 — or when two consecutive VERIFY lines are identical and still failing, in which case name the failing checks and stop, because a contract whose checks have rotted must not trap the run.
```

**Or run phases manually** in dependency order:

**Strategy**: Hybrid (artifacts generated; execution waits for the owner): a sequential spine (pieces, canon, lint, frameworks with reviewers and packaging) followed by Full-tier phases that branch off it, then a human acceptance gate. Commits go directly on main with linear history and no isolation branch; push only when the owner asks. Agent work stays queued and quality beats latency, so token limits and timeouts are runaway guards. Every phase that adds or changes an agent role runs a validator-to-schema audit and keeps EditorialInputContractTest green over EditorialActivityKind::cases(). The paid drift trial and the exemplar backfill never run automatically.

1. **Phase 1** — Content ideas and pieces _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-10-02-consistent-voice-publishing/spec-phase-1.md
   ```

2. **Phase 2** — Voice and goals canon in every prompt _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-10-02-consistent-voice-publishing/spec-phase-2.md
   ```

3. **Phase 3** — Deterministic voice lint _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-10-02-consistent-voice-publishing/spec-phase-3.md
   ```

4. **Phase 4** — Frameworks, sharper reviewers and packaging _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-10-02-consistent-voice-publishing/spec-phase-4.md
   ```

5. **Phase 5** — Exemplar retrieval _(blocked by Voice and goals canon in every prompt)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-10-02-consistent-voice-publishing/spec-phase-5.md
   ```

6. **Phase 6** — Distribution kit and sibling pieces _(blocked by Frameworks, sharper reviewers and packaging)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-10-02-consistent-voice-publishing/spec-phase-6.md
   ```

7. **Phase 7** — Publishing board _(blocked by Distribution kit and sibling pieces)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-10-02-consistent-voice-publishing/spec-phase-7.md
   ```

8. **Phase 8** — Canon editor _(blocked by Deterministic voice lint, Frameworks, sharper reviewers and packaging, Distribution kit and sibling pieces)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-10-02-consistent-voice-publishing/spec-phase-8.md
   ```

9. **Phase 9** — Drift trial harness and edit distance _(blocked by Frameworks, sharper reviewers and packaging, Exemplar retrieval)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-10-02-consistent-voice-publishing/spec-phase-9.md
   ```

10. **Phase 10** — Owner voice acceptance _(blocked by Publishing board, Canon editor, Drift trial harness and edit distance)_

   ```bash
   # Review: Owner voice acceptance
   ```

---

_This contract was generated from brain dump input. Review and approve before proceeding to specification._
