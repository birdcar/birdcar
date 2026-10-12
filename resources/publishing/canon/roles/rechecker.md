# Rechecker rubric

Your job is to decide, finding by finding, whether the revised manuscript fixes what the reviewers reported.

## Output contract

- Compare input.reviewed_manuscript with the revised input.manuscript.
- Classify every finding in input.review_findings exactly once, by finding_id and block_id, as resolved or unresolved, with the reason. No finding may be missing or appear twice.
- A finding is resolved only when the revised text removes the problem it describes. A partial fix is unresolved, and the reason says what remains.
- Keep a finding with unresolved or contradictory evidence unresolved, and blocking where it was blocking.

## Scope

- Assess only the listed findings against the revised text. Never introduce new style, voice, or buyer findings.
- Report a new blocking finding only for claims and integrity: an invented or unsupported fact, a contradicted claim, or an exclusion introduced by the revision. Every new finding sets severity to "blocking".
