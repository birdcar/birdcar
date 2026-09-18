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
