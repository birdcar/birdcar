# Implementation Spec: Consistent Voice Publishing - Phase 7

**Contract**: ./contract.md
**Estimated Effort**: L

## Technical Approach

This phase replaces the publishing dashboard's two lists with a kanban board of pieces, keeping the idea composer at the top. Columns are derived from each piece's existing stage and activities. No new stage values are introduced:

| Column | Derived from |
| --- | --- |
| Ideas | No current attempt and not published |
| Shaping | Attempt stage `developing` |
| Planning | Attempt stage `drafting` (research and plan) |
| Writing | Stage `in_review` with no completed draft on the current revision, or a `draft`, `lint_repair` or `draft_kit` activity pending or running |
| Review | Stage `in_review` after the draft, through review, decisions, recheck and packaging |
| Ready | Stage `approved` (shown as "Ready to post" on kit cards) |
| Scheduled | Stage `scheduled` |
| Published | Stage `published` (shown as "Posted" on kit cards); the column shows the 12 most recent and links to the published library |
| Parked | `parked_at` set, any stage |
| Killed | Stage `abandoned` or `abandoned_at` set; collapsed by default |

Cards are blog-post pieces (flagships and siblings). Kit pieces nest inside their flagship's card with their own stage chip, so the board shows one card per post rather than four. Every card shows:

- the piece's metadata title, or the idea summary labeled "Idea";
- the kind;
- the live agent status: running, waiting on you, or failed (with the reason).

The board polls every ten seconds, the same pattern the workspace session uses.

Drags use Livewire 4 `wire:sort` with one sort group spanning all columns. Only single-action moves are allowed, and each is validated server-side in a `MovePublishingPiece` action, not just in the UI:

- Ideas to Shaping starts development and the interview.
- Ready to Scheduled opens the existing release scheduling dialog for that piece.
- Any column to Parked or Killed asks for a reason, then parks or abandons.
- Parked back to its stage column unparks.

Every other move is refused with a message naming the gate ("Open the workspace to approve the plan"), and the card snaps back. Clicking a card opens the workspace.

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **Board columns follow stage; drags are allowed only for single human actions; crossing a gate opens the workspace**: rejected dragging across a gate to approve it, and a read-only board. The owner approves the angle, plan and exact release text where he can read them.
- **Refine the existing stages (Ideas column, lint, packaging step, kit spawn); keep every current stage and gate**: rejected merging research into planning or dropping the angle gate.
- **One idea owns many pieces, each with its own stage**: kit cards nest under the flagship; siblings are their own cards.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/PublishingBoardTest.php`

**Playground**: `Livewire::test('admin.publishing.board')` with factories for each stage, plus the dev site at `admin.birdcar.test/publishing` (served by Herd; build assets with `bun run build`).

**Why this approach**: Column mapping and move rules are server logic best covered by Livewire tests. The visual drag behavior gets the owner's browser review.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `resources/views/components/admin/publishing/⚡board.blade.php` | Board SFC: composer, columns, cards, nested kit cards, move handling, park/kill reason modal, polling |
| `app/Services/Publishing/BoardColumn.php` | Enum of columns with `forPiece(Article $piece): self` and `label()` |
| `app/Services/Publishing/PieceStatus.php` | Readonly value: `state` (`running`, `waiting`, `failed`, `idle`), `label`, `reason`, built from the current attempt and its latest activities |
| `app/Actions/Publishing/MovePublishingPiece.php` | Server-side move rules; dispatches to `AdvancePublishingAttempt` and `StartEditorialActivity` for each allowed move |
| `tests/Feature/Publishing/PublishingBoardTest.php` | Column mapping, allowed and refused moves, nesting, status, authorization, query count |

### Modified Files

| File Path | Changes |
| --- | --- |
| `routes/admin.php` | `/publishing` renders `admin.publishing.board`; keep the route name `admin.publishing.dashboard` so existing links work |
| `resources/views/components/admin/⚡index.blade.php` | Link to the board; titles via metadata or the labeled idea summary |
| `resources/css/admin.css` (or the existing admin publishing stylesheet) | Board layout: horizontal scroll on narrow screens, column widths, nested kit card styles, reduced-motion handling for drag feedback |
| `resources/js/admin/publishing/motion.js` | Only if needed for drag feedback; respect `prefers-reduced-motion` |

### Deleted Files

| File Path | Reason |
| --- | --- |
| `resources/views/components/admin/publishing/⚡dashboard.blade.php` | Replaced by the board; its capture actions move into the board unchanged |

## Implementation Details

### Column and status derivation

```php
enum BoardColumn: string
{
    case Ideas = 'ideas'; case Shaping = 'shaping'; case Planning = 'planning'; case Writing = 'writing';
    case Review = 'review'; case Ready = 'ready'; case Scheduled = 'scheduled'; case Published = 'published';
    case Parked = 'parked'; case Killed = 'killed';

    public static function forPiece(Article $piece): self; // uses eager-loaded currentAttempt and its activities only
}
```

`PieceStatus` rules:

- `running`: any activity pending or running.
- `failed`: the latest activity is failed or paused (reason from `error_reason` or `pause_reason`).
- `waiting`: the owner owes an action. That means one of:
  - angle options ready and the angle not approved;
  - plan ready and not approved;
  - undecided blocking findings;
  - a package proposal not accepted;
  - a release check ready;
  - a kit piece drafted and not approved.
- `idle`: none of the above.

**Key decisions**:

- The query eager-loads everything the derivation reads (`contentIdea`, `workingRevision`, `currentAttempt`, the latest activities per attempt through a constrained eager load, `children.currentAttempt`). Strict mode throws on lazy loads in tests, which enforces this.
- Archive imports appear in Published. That column is capped at 12 items, newest first, with a link to the published library.

### Moves

**Pattern to follow**: the capture and develop actions in the deleted `⚡dashboard.blade.php`; `AdvancePublishingAttempt` for park and abandon.

```php
final class MovePublishingPiece
{
    public function move(User $actor, Article $piece, BoardColumn $from, BoardColumn $to, ?string $reason = null): MoveResult;
    // MoveResult: moved | needsDialog(name) | refused(message)
}
```

**Implementation steps**:

1. Look up the Livewire 4 `wire:sort` docs (`search-docs` with `["wire:sort", "sortable groups"]`) and follow the `livewire-development` skill. Use one group across columns so cross-column drags call a single handler with the card id and target column.
2. Implement `BoardColumn`, `PieceStatus`, and `MovePublishingPiece` with full authorization: develop for Ideas to Shaping, publish for scheduling, write for park and kill. Authorize inside the Livewire method too, because `Livewire::test` skips route middleware.
3. Build the board SFC with `flux:kanban`, `flux:kanban.column` and `flux:kanban.card`. The composer and its two actions move over from the dashboard.
4. Add the reason modal (`flux:modal`) for Parked and Killed, and reuse the workspace's release scheduling dialog for Ready to Scheduled (open the workspace's release panel if the dialog cannot be shared cleanly; say which in the commit).
5. Add `wire:poll.10s` limited to the cards region.
6. Update the Admin index links and remove the dashboard component.

**Feedback loop**:

- **Playground**: `tests/Feature/Publishing/PublishingBoardTest.php`, then the browser at `admin.birdcar.test` resolved through Boost `get-absolute-url`.
- **Experiment**: Create one piece per column via factories, then assert each lands in its column. Attempt every refused move (Shaping to Planning, Planning to Writing, Review to Ready) and assert the refusal message and an unchanged stage. Drag Ideas to Shaping and assert that an interview activity was created. Park with an empty reason and assert a validation error. Give a flagship three kit pieces and assert one top-level card with three nested chips.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/PublishingBoardTest.php`

## Testing Requirements

| Test File | Coverage |
| --- | --- |
| `tests/Feature/Publishing/PublishingBoardTest.php` | Column mapping for every stage and flag; allowed and refused moves (server-side); nested kit cards; status chips for running, waiting and failed; a viewer without `publishing.develop` cannot drag to Shaping; query count stays flat with 30 pieces (assert no lazy loading and fewer than N queries) |
| `tests/Feature/Publishing/AdminPublishingWorkspaceTest.php` and others referencing the dashboard | Updated to the board component name |

### Manual Testing (owner, judgment criterion)

- [ ] Desktop and mobile widths, light and dark: columns scroll horizontally on mobile without page overflow.
- [ ] Drag feedback is visible. A refused drag snaps back with the message.
- [ ] Status chips update within about ten seconds while an agent runs.
- [ ] Reduced motion: no animated drag transitions.

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| Move to a column whose action fails (e.g., agents paused) | Toast with the action's error; card snaps back |
| Concurrent change (stage advanced by an agent during the drag) | `MovePublishingPiece` re-derives `from` server-side; mismatch is refused with "This piece moved; refresh" |
| Unauthorized move | Refused with a message; no state change |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Derivation | A piece in two columns or none | Missing rule for a stage/flag combination | Lost card | Exhaustive `match` with a test row per combination |
| Polling | Query cost grows with archive size | Published column unbounded | Slow board | Published capped at 12; eager loads constrained |
| Drag | UI allows a refused move to appear applied | Optimistic DOM move | Owner confusion | Handler returns refusal and re-renders the column order from server state |
| Mobile | Cross-column drag unusable on touch | Narrow screens | Owner cannot move on phone | Card menu exposes the same allowed moves as buttons |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/PublishingBoardTest.php
php artisan test --compact tests/Feature/Publishing
bun run build
vendor/bin/pint --dirty --format agent
env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress
composer test
```

## Rollout Considerations

- **Feature flag**: none.
- **Rollback plan**: revert; the dashboard component returns from history.
- **Rules in force**: `.ai/rules/resources.md` (Admin uses Flux UI Pro with Inter), `.ai/rules/app.md` (eager loading), `.ai/rules/general.md` (Herd serves the site; never run `artisan serve`; build assets with bun), and the `fluxui-development` and `livewire-development` skills.

## Open Items

- [ ] None.
