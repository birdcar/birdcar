# Ideation Learnings

Generalizable spec-gap and interview patterns captured from completed
ideation projects. Intake reads this file so recurring gaps inform future
questioning and spec generation. Each entry is dated and cites its
evidence; treat entries as hints, never as a substitute for gate evidence.

## 2026-09-18 — Walkthrough offer and funnel signals

- **Pattern**: Specs must distinguish markup and build checks from browser and human review; passing one does not verify the other.
  **Evidence**: Phase 1 implementation notes left live booking and analytics behavior unverified; phase 3 notes left voice review and FAQ arrow behavior unverified despite passing automated checks.
  **Spec/interview implication**: Name rendered-behavior checks and their reviewer explicitly for booking, animation, visual explanations, and print output; do not treat source assertions as evidence of those experiences.

- **Pattern**: A changed design or offer requires reconciling its authoritative records, not merely appending a new decision.
  **Evidence**: Phase 3 implementation notes required edits beyond the planned append-and-prune to remove contradictory offer names, booking destinations, and turnaround statements in PRODUCT.md and shared rules.
  **Spec/interview implication**: Include relevant product records, design records, surface briefs, and shared rules in the affected-file review, and resolve contradictory guidance as part of the change.

## 2026-09-25 — Publishing Agent Simplification

- **Pattern**: Agent specs must state every rule the application's output validators enforce in the model-facing schema or instructions; fake-provider tests cannot reveal an unstated contract.
  **Evidence**: The Phase 3 live trial failed repeatedly on enforced but unstated rules (contiguous quotations, retained-evidence IDs, blocking contradictions, field sizes, reviewer quotation scope, recheck coverage) until an audit documented them in the schemas.
  **Spec/interview implication**: Include a validator-to-schema audit in agent specs, and capture the provider's raw reply for failed stages from the first paid run so each failure is diagnosable without another spend.

- **Pattern**: Multi-agent pipelines need a check that each downstream role's frozen input contains the data that role judges.
  **Evidence**: Reconciliation and recheck never received the review findings (or the reviewed text); fake-provider tests passed because fakes ignore their input, and the live reconciler looped to its token limit.
  **Spec/interview implication**: For every agent role, add a test asserting its frozen input includes the context it must evaluate, not only that its fake output is applied.

- **Pattern**: Request timeouts, token allowances and provider routing were sized for latency and cost by default even though the AI work was queued and the owner prioritised quality.
  **Evidence**: A 50-second timeout, 12k-token allowances and OpenRouter's price-first routing caused timeouts, truncated answers and 37- to 298-second swings, and prompted a model downgrade the owner later reversed.
  **Spec/interview implication**: Ask during the interview whether AI work is queued and whether quality or latency wins, then size limits as runaway guards below the worker timeout and choose provider routing deliberately.

## 2026-10-13 — Consistent Voice Publishing

- **Pattern**: Specs told builders to reuse or follow code that did not exist, so each phase improvised a substitute.
  **Evidence**: Phase 7 notes (no release scheduling dialog and no toast in the admin layout), Phase 4 notes (an avoid pair naming a kishotenketsu framework with no file), Phase 1 notes (the CI step calls a non-existent ReadWriting::all()).
  **Spec/interview implication**: When a spec says "reuse X" or "follow Y", grep for X while writing it, and name the fallback when X may not exist.

- **Pattern**: The publishing editor autosaves into a new ArticleRevision about every 250 ms, so logic keyed on "the current revision" or "the owner's next save" misreads ordinary typing.
  **Evidence**: Phase 7 notes (a literal Writing-column rule would move a reviewed piece back on the first autosave), Phase 9 notes (edit distance to "the next owner save" measures the first burst of typing).
  **Spec/interview implication**: Specs that touch revisions compare document content or a defined editing window, never revision ids or the next save.

- **Pattern**: A spec that restates rules a model-facing artifact already defines creates a second source that drifts from the one the agent obeys.
  **Evidence**: Phase 3 notes ("canon over spec": bold-limit per section vs per piece, exclamation scope), Phase 4 notes (metadata rules had to reach the Drafter too), and a Phase 8 guard string that tripped the contract's own absence grep.
  **Spec/interview implication**: Define each rule's semantics once in the artifact the agent reads, and have specs, lint rules and tests reference it instead of restating it.
