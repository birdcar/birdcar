<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\MovePublishingPiece;
use App\Actions\Publishing\MoveResult;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Permission as AdminPermission;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\Publishing\EditorialActivityKind;
use App\Services\Publishing\BoardColumn;
use App\Services\Publishing\PieceStatus;
use App\Settings\PublishingAgentSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.admin')] class extends Component
{
    public string $idea = '';

    public bool $confirmingMove = false;

    public ?int $movePieceId = null;

    public string $moveFrom = '';

    public string $moveTo = '';

    public string $moveReason = '';

    public ?string $moveMessage = null;

    public bool $moveRefused = false;

    public function saveForLater(WriteArticle $articles): void
    {
        Gate::authorize(AdminPermission::View->value);
        Gate::authorize(PublishingPermission::Write->value);

        $validated = $this->validate(['idea' => ['required', 'string', 'max:5000']]);
        $articles->captureIdea(auth()->user(), $validated['idea']);

        $this->idea = '';
        session()->flash('status', 'Idea saved for later.');
    }

    public function developIdea(WriteArticle $articles, AdvancePublishingAttempt $attempts, StartEditorialActivity $activities): void
    {
        Gate::authorize(AdminPermission::View->value);
        Gate::authorize(PublishingPermission::Write->value);
        Gate::authorize(PublishingPermission::Develop->value);

        $validated = $this->validate(['idea' => ['required', 'string', 'max:5000']]);
        $article = null;

        DB::transaction(function () use ($articles, $attempts, $activities, $validated, &$article): void {
            $actor = auth()->user();
            $article = $articles->captureIdea($actor, $validated['idea']);
            $body = $article->contentIdea->body;
            $attempt = $attempts->develop($actor, $article, null, ['idea' => $body]);
            $activities->start($actor, $attempt, EditorialActivityKind::Interview, ['idea' => $body], 'initial-interview');
        });

        $this->idea = '';
        session()->flash('status', 'Idea moved into active writing. The interview has been queued.');
        session()->flash('publishing-arrival', true);
        $this->redirectRoute('admin.publishing.articles.show', ['article' => $article], navigate: false);
    }

    /**
     * The wire:sort handler. Only the receiving column calls it, and each card's key carries the column the owner saw it
     * in, so the server can refuse a move made on a stale board.
     */
    public function moveCard(?string $item = null, ?int $position = null, ?string $to = null): void
    {
        [$pieceId, $from] = array_pad(explode(':', (string) $item, 2), 2, '');

        $this->movePiece((int) $pieceId, $from, (string) $to);
    }

    public function movePiece(int $pieceId, string $from, string $to): void
    {
        $this->authorizeBoard();
        $this->closeMoveDialog();
        $this->moveMessage = null;

        $fromColumn = BoardColumn::tryFrom($from);
        $toColumn = BoardColumn::tryFrom($to);
        $piece = Article::query()->select('id')->find($pieceId);

        if (! $fromColumn instanceof BoardColumn || ! $toColumn instanceof BoardColumn || ! $piece instanceof Article) {
            $this->showMoveResult(MoveResult::refused(MovePublishingPiece::NOT_ON_BOARD));

            return;
        }

        $result = app(MovePublishingPiece::class)->move(auth()->user(), $piece, $fromColumn, $toColumn);

        if ($result->needs('reason')) {
            $this->movePieceId = $pieceId;
            $this->moveFrom = $fromColumn->value;
            $this->moveTo = $toColumn->value;
            $this->confirmingMove = true;

            return;
        }

        if ($result->needs('release') && $result->piece instanceof Article) {
            session()->flash('status', 'Schedule the release from its checklist: prepare it with a publish time, then approve it.');
            $this->redirectRoute('admin.publishing.articles.show', ['article' => $result->piece], navigate: true);

            return;
        }

        $this->showMoveResult($result);
    }

    public function confirmMove(MovePublishingPiece $moves): void
    {
        $this->authorizeBoard();
        $this->validate(
            ['moveReason' => ['required', 'string', 'max:'.MovePublishingPiece::REASON_LIMIT]],
            ['moveReason.required' => $this->moveTo === BoardColumn::Killed->value ? 'Say why you’re killing this piece.' : 'Say why you’re parking this piece.'],
        );

        $from = BoardColumn::tryFrom($this->moveFrom);
        $to = BoardColumn::tryFrom($this->moveTo);
        $piece = $this->movePieceId === null ? null : Article::query()->select('id')->find($this->movePieceId);

        if (! $from instanceof BoardColumn || ! $to instanceof BoardColumn || ! $piece instanceof Article) {
            $this->closeMoveDialog();
            $this->showMoveResult(MoveResult::refused(MovePublishingPiece::NOT_ON_BOARD));

            return;
        }

        $result = $moves->move(auth()->user(), $piece, $from, $to, $this->moveReason);

        $this->closeMoveDialog();
        $this->showMoveResult($result);
    }

    public function cancelMove(): void
    {
        $this->closeMoveDialog();
    }

    public function dismissMoveMessage(): void
    {
        $this->moveMessage = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $this->authorizeBoard();

        $actor = auth()->user();
        $agentsPaused = app(PublishingAgentSettings::class)->paused;
        $active = BoardColumn::wherePublished(BoardColumn::pieces($actor), published: false)
            ->latest('updated_at')
            ->orderByDesc('id')
            ->get();
        $published = BoardColumn::wherePublished(BoardColumn::pieces($actor))
            ->orderByRaw('first_published_at is null')
            ->orderByDesc('first_published_at')
            ->orderByDesc('id')
            ->limit(BoardColumn::PUBLISHED_LIMIT)
            ->get();

        $cards = array_fill_keys(array_map(fn (BoardColumn $column): string => $column->value, BoardColumn::cases()), []);
        foreach ($active->concat($published) as $piece) {
            $column = BoardColumn::forPiece($piece);
            $cards[$column->value][] = $this->card($piece, $column, (int) $actor->id, $agentsPaused);
        }

        return [
            'cards' => $cards,
            'publishedCount' => BoardColumn::wherePublished(BoardColumn::pieces($actor))->count(),
        ];
    }

    /**
     * @return array{piece: Article, title: ?string, status: PieceStatus, targets: list<BoardColumn>, note: ?string, kits: list<array{piece: Article, stage: string, status: PieceStatus}>}
     */
    private function card(Article $piece, BoardColumn $column, int $viewerId, bool $agentsPaused): array
    {
        $attempt = $piece->currentAttempt;
        $targets = array_values(array_filter(
            $column->menuTargets($attempt),
            fn (BoardColumn $target): bool => Gate::allows($target === BoardColumn::Scheduled ? 'publish' : 'develop', $piece),
        ));

        return [
            'piece' => $piece,
            'title' => $this->titleFor($piece),
            'status' => PieceStatus::for($piece, $viewerId, $agentsPaused),
            'targets' => $targets,
            'note' => match ($column) {
                BoardColumn::Killed => $attempt?->abandoned_reason,
                BoardColumn::Parked => $attempt?->parked_reason,
                BoardColumn::Published => null,
                default => $piece->published_release_id !== null ? 'Draft in progress on published work' : null,
            },
            'kits' => $piece->children->map(fn (Article $kit): array => [
                'piece' => $kit,
                'stage' => BoardColumn::forPiece($kit)->label($kit->kind),
                'status' => PieceStatus::for($kit, $viewerId, $agentsPaused),
            ])->values()->all(),
        ];
    }

    public function titleFor(Article $article): ?string
    {
        $title = data_get($article->workingRevision?->metadata, 'title');

        return is_string($title) && trim($title) !== '' ? $title : null;
    }

    public function ideaHasMoreThanItsSummary(Article $article): bool
    {
        return trim($article->contentIdea->body) !== $article->contentIdea->summary_title;
    }

    public function targetLabel(BoardColumn $from, BoardColumn $target): string
    {
        return match (true) {
            $target === BoardColumn::Shaping && $from === BoardColumn::Ideas => 'Develop this idea',
            $target === BoardColumn::Scheduled => 'Schedule the release',
            $target === BoardColumn::Parked => 'Park…',
            $target === BoardColumn::Killed => 'Kill…',
            default => 'Unpark to '.$target->label(),
        };
    }

    private function showMoveResult(MoveResult $result): void
    {
        $this->moveRefused = $result->wasRefused();
        $this->moveMessage = $result->message;
    }

    private function closeMoveDialog(): void
    {
        $this->confirmingMove = false;
        $this->movePieceId = null;
        $this->moveFrom = '';
        $this->moveTo = '';
        $this->moveReason = '';
        $this->resetErrorBag('moveReason');
    }

    private function authorizeBoard(): void
    {
        Gate::authorize(AdminPermission::View->value);
        Gate::authorize(PublishingPermission::View->value);
    }
};
?>

<section data-publishing-studio class="publishing-studio space-y-3" x-data="publishingArrivals">
    <div data-publishing-enter>
        <flux:heading level="1" size="xl">Publishing workspace</flux:heading>
        <flux:text class="mt-2 max-w-3xl">A rough thought is enough. Let’s find the article in it.</flux:text>
    </div>

    <form wire:submit="{{ auth()->user()->can(PublishingPermission::Develop->value) ? 'developIdea' : 'saveForLater' }}" class="publishing-idea-composer" data-publishing-enter>
        <flux:field>
            <flux:label class="sr-only">New idea</flux:label>
            <flux:composer id="idea" wire:model="idea" maxlength="5000" placeholder="What have you been thinking about?" data-publishing-composer>
                <x-slot:actionsTrailing>
                    @can(PublishingPermission::Write->value)
                        <flux:button type="button" wire:click="saveForLater" wire:loading.attr="disabled" wire:target="saveForLater,developIdea" variant="ghost" icon="bookmark" tooltip="Save for later" aria-label="Save for later" />
                    @endcan
                    @can(PublishingPermission::Develop->value)
                        <flux:button type="submit" wire:loading.attr="disabled" wire:target="developIdea,saveForLater" variant="primary" icon="paper-airplane" tooltip="Develop idea" aria-label="Develop idea" data-publishing-capture />
                    @endcan
                </x-slot:actionsTrailing>
            </flux:composer>
            <flux:error name="idea" />
            @if (session('status'))<p role="status" class="mt-3 text-sm text-teal-800 dark:text-cyan-200">{{ session('status') }}</p>@endif
            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-zinc-600 dark:text-zinc-300">
                <span wire:loading wire:target="saveForLater">Saving idea…</span>
                <span wire:loading wire:target="developIdea">Opening your next piece…</span>
                <span>Send to develop with your editorial agents. Bookmark to keep it for later.</span>
            </div>
        </flux:field>
    </form>

    <div class="publishing-board" data-publishing-enter data-publishing-board>
        @if ($moveMessage)
            <flux:callout :variant="$moveRefused ? 'warning' : 'success'" :icon="$moveRefused ? 'hand-raised' : 'check-circle'" inline :text="$moveMessage" :role="$moveRefused ? 'alert' : 'status'" class="mb-4" data-board-message>
                <x-slot name="controls">
                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="dismissMoveMessage" aria-label="Dismiss message" />
                </x-slot>
            </flux:callout>
        @endif

        <div class="publishing-board-scroll" role="region" aria-label="Publishing board" tabindex="0" wire:poll.10s.visible>
            <flux:kanban class="publishing-board-columns">
                @foreach (BoardColumn::cases() as $column)
                    <div wire:key="board-column-{{ $column->value }}" class="publishing-board-column" data-board-column="{{ $column->value }}" @if ($column === BoardColumn::Killed) x-data="{ open: false }" x-bind:class="{ 'is-collapsed': ! open }" @endif>
                        <flux:kanban.column>
                            <flux:kanban.column.header :heading="$column->label()" :count="$column === BoardColumn::Published ? $publishedCount : count($cards[$column->value])" :subheading="$column->description()">
                                @if ($column === BoardColumn::Published)
                                    <x-slot:actions>
                                        <flux:button :href="route('admin.publishing.published')" wire:navigate size="xs" variant="ghost" icon:trailing="arrow-right" data-board-library>Library</flux:button>
                                    </x-slot:actions>
                                @elseif ($column === BoardColumn::Killed)
                                    <x-slot:actions>
                                        <flux:button size="xs" variant="ghost" icon="chevron-up-down" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'" x-text="open ? 'Hide' : 'Show'" aria-controls="board-killed-cards">Show</flux:button>
                                    </x-slot:actions>
                                @endif
                            </flux:kanban.column.header>

                            <div
                                @if ($column === BoardColumn::Killed) id="board-killed-cards" @endif
                                class="publishing-board-cards"
                                wire:sort.ghost="moveCard"
                                wire:sort:group="board"
                                wire:sort:group-id="{{ $column->value }}"
                                wire:sort:config="{ animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 150, draggable: '[wire\\:sort\\:item]' }"
                                data-flux-kanban-column-cards
                            >
                                @foreach ($cards[$column->value] as $card)
                                    @php($piece = $card['piece'])
                                    <div wire:key="board-card-{{ $piece->id }}" wire:sort:item="{{ $piece->id }}:{{ $column->value }}" class="publishing-board-card" data-board-card="{{ $piece->id }}">
                                        <flux:kanban.card>
                                            <div class="flex items-start justify-between gap-2">
                                                <a href="{{ route('admin.publishing.articles.show', $piece) }}" wire:navigate class="publishing-board-card-link min-w-0 flex-1">
                                                    <x-admin.publishing.piece-heading :title="$card['title']" :summary="$piece->contentIdea->summary_title" />
                                                </a>
                                                <div wire:sort:ignore class="-mr-1 -mt-1 shrink-0">
                                                    <flux:dropdown position="bottom" align="end">
                                                        <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" aria-label="Piece actions" />
                                                        <flux:menu>
                                                            @foreach ($card['targets'] as $target)
                                                                <flux:menu.item wire:click="movePiece({{ $piece->id }}, '{{ $column->value }}', '{{ $target->value }}')" data-board-move="{{ $target->value }}">{{ $this->targetLabel($column, $target) }}</flux:menu.item>
                                                            @endforeach
                                                            @if ($card['targets'] !== [])
                                                                <flux:menu.separator />
                                                            @endif
                                                            <flux:menu.item :href="route('admin.publishing.articles.show', $piece)" wire:navigate icon="arrow-up-right">Open workspace</flux:menu.item>
                                                        </flux:menu>
                                                    </flux:dropdown>
                                                </div>
                                            </div>

                                            <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                <span>{{ $piece->kind->label() }}</span>
                                                @if ($card['note'])
                                                    <span aria-hidden="true">·</span>
                                                    <span class="wrap-anywhere" data-board-note>{{ $card['note'] }}</span>
                                                @endif
                                            </div>

                                            @if ($column === BoardColumn::Ideas && $this->ideaHasMoreThanItsSummary($piece))
                                                <details wire:sort:ignore class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                                                    <summary class="cursor-pointer font-medium text-teal-700 dark:text-cyan-300">View full idea</summary>
                                                    <p class="mt-2 whitespace-pre-wrap wrap-anywhere">{{ $piece->contentIdea->body }}</p>
                                                </details>
                                            @endif

                                            @unless ($card['status']->isIdle())
                                                <div class="mt-3 space-y-1" data-piece-status="{{ $card['status']->state }}">
                                                    <flux:badge size="sm" :color="match ($card['status']->state) { 'running' => 'sky', 'waiting' => 'amber', default => 'red' }" :icon="match ($card['status']->state) { 'running' => 'arrow-path', 'waiting' => 'hand-raised', default => 'exclamation-triangle' }">{{ $card['status']->label }}</flux:badge>
                                                    @if ($card['status']->reason)
                                                        <p class="text-xs text-zinc-600 wrap-anywhere dark:text-zinc-300">{{ $card['status']->reason }}</p>
                                                    @endif
                                                </div>
                                            @endunless

                                            @if ($card['kits'] !== [])
                                                <ul class="publishing-board-kits" aria-label="Distribution kit" wire:sort:ignore>
                                                    @foreach ($card['kits'] as $kit)
                                                        <li wire:key="board-kit-{{ $kit['piece']->id }}" data-board-kit="{{ $kit['piece']->kind->value }}">
                                                            <a href="{{ route('admin.publishing.articles.show', $kit['piece']) }}" wire:navigate class="publishing-board-kit" data-kit-status="{{ $kit['status']->state }}" @if ($kit['status']->reason) title="{{ $kit['status']->reason }}" @endif>
                                                                <span class="font-medium">{{ $kit['piece']->kind->label() }}</span>
                                                                <span>{{ $kit['stage'] }}</span>
                                                                @unless ($kit['status']->isIdle())
                                                                    <span class="publishing-board-kit-status">{{ $kit['status']->label }}</span>
                                                                @endunless
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </flux:kanban.card>
                                    </div>
                                @endforeach

                                @if ($cards[$column->value] === [])
                                    <p class="publishing-board-empty">{{ $column === BoardColumn::Ideas ? 'Use the composer to keep an idea for later.' : 'Nothing here.' }}</p>
                                @elseif ($column === BoardColumn::Killed)
                                    <p class="publishing-board-collapsed-note" x-show="! open">{{ trans_choice('{1} One killed piece is hidden.|[2,*] :count killed pieces are hidden.', count($cards[$column->value])) }}</p>
                                @endif
                            </div>

                            @if ($column === BoardColumn::Published && $publishedCount > BoardColumn::PUBLISHED_LIMIT)
                                <flux:kanban.column.footer>
                                    <flux:link :href="route('admin.publishing.published')" wire:navigate class="px-3 text-sm">See all {{ $publishedCount }} in the published library</flux:link>
                                </flux:kanban.column.footer>
                            @endif
                        </flux:kanban.column>
                    </div>
                @endforeach
            </flux:kanban>
        </div>
    </div>

    <flux:modal wire:model.self="confirmingMove" class="settings-dialog">
        <form wire:submit="confirmMove" class="settings-dialog-body">
            <div>
                <flux:heading size="lg">{{ $moveTo === 'killed' ? 'Kill this piece?' : 'Park this piece?' }}</flux:heading>
                <flux:text class="mt-2">{{ $moveTo === 'killed' ? 'Killing sets the piece aside for good. Agents stop picking up its work, and a scheduled release is withdrawn.' : 'Parking pauses the piece on purpose. Agents stop picking up its work until you drag it back to its column.' }}</flux:text>
            </div>
            <flux:textarea wire:model="moveReason" :label="$moveTo === 'killed' ? 'Why kill it?' : 'Why park it?'" rows="3" maxlength="{{ MovePublishingPiece::REASON_LIMIT }}" />
            <div class="settings-dialog-actions">
                <flux:button type="button" variant="ghost" wire:click="cancelMove">Cancel</flux:button>
                <flux:button type="submit" :variant="$moveTo === 'killed' ? 'danger' : 'primary'">{{ $moveTo === 'killed' ? 'Kill piece' : 'Park piece' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

<script>
    this.$intercept(({ action }) => {
        if (action.metadata?.type === 'poll' && document.body.classList.contains('sorting')) {
            action.cancel();
        }
    });
</script>
