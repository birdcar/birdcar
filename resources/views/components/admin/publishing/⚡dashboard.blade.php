<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Permission as AdminPermission;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component
{
    use WithPagination;

    public string $idea = '';

    public function saveForLater(WriteArticle $articles): void
    {
        Gate::authorize(AdminPermission::View->value);
        Gate::authorize(PublishingPermission::Write->value);

        $validated = $this->validate(['idea' => ['required', 'string', 'max:5000']]);
        $articles->capture(auth()->user(), $validated['idea']);

        $this->idea = '';
        $this->resetPage('ideasPage');
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
            $article = $articles->capture($actor, $validated['idea']);
            $attempt = $attempts->develop($actor, $article, null, ['idea' => $validated['idea']]);
            $activities->start($actor, $attempt, EditorialActivityKind::Interview, ['idea' => $validated['idea']], 'initial-interview');
        });

        $this->idea = '';
        session()->flash('status', 'Idea moved into active writing. The interview has been queued.');
        session()->flash('publishing-arrival', true);
        $this->redirectRoute('admin.publishing.articles.show', ['article' => $article], navigate: false);
    }

    public function with(): array
    {
        $ideas = Article::query()
            ->with(['workingRevision', 'currentAttempt'])
            ->where('author_id', auth()->id())
            ->whereNull('current_attempt_id')
            ->whereNull('published_release_id')
            ->latest()
            ->paginate(perPage: 12, pageName: 'ideasPage');

        $active = Article::query()
            ->with(['workingRevision', 'currentAttempt.editorialActivities'])
            ->where('author_id', auth()->id())
            ->whereNotNull('current_attempt_id')
            ->whereHas('currentAttempt', function ($query): void {
                $query->whereNull('abandoned_at')
                    ->whereNotIn('stage', [EditorialStage::Published->value, EditorialStage::Abandoned->value]);
            })
            ->latest()
            ->paginate(perPage: 12, pageName: 'activePage');

        return ['ideas' => $ideas, 'active' => $active];
    }

    public function titleFor(Article $article): string
    {
        $title = data_get($article->workingRevision?->metadata, 'title');

        return is_string($title) && trim($title) !== '' ? $title : Str::limit($article->idea, 100);
    }

    public function previewFor(string $value, int $limit = 220): string
    {
        return Str::limit(trim($value), $limit);
    }

    public function statusFor(?PublishingAttempt $attempt): string
    {
        if (! $attempt instanceof PublishingAttempt) {
            return 'Not started';
        }

        if ($attempt->paused_at !== null) {
            return 'Paused'.($attempt->pause_reason ? ': '.$attempt->pause_reason : '');
        }

        if ($attempt->parked_at !== null) {
            return 'Parked'.($attempt->parked_reason ? ': '.$attempt->parked_reason : '');
        }

        return match ($attempt->stage) {
            EditorialStage::Developing => 'Developing angle',
            EditorialStage::Drafting => 'Drafting plan',
            EditorialStage::InReview => 'In review',
            EditorialStage::Approved => 'Approved for release',
            EditorialStage::Scheduled => 'Scheduled',
            EditorialStage::Published => 'Published',
            EditorialStage::Abandoned => 'Abandoned',
            default => 'Active writing',
        };
    }

    public function activityFor(?PublishingAttempt $attempt): ?string
    {
        $activity = $attempt?->editorialActivities?->sortByDesc('id')->first();

        if ($activity === null) {
            return null;
        }

        $kind = match ($activity->kind) {
            EditorialActivityKind::Interview => 'Interview',
            EditorialActivityKind::ResearchChallenge => 'Research challenge',
            EditorialActivityKind::Plan => 'Plan',
            EditorialActivityKind::Draft => 'Draft',
            EditorialActivityKind::ReviewFacts => 'Fact review',
            EditorialActivityKind::ReviewVoice => 'Voice review',
            EditorialActivityKind::ReviewBuyer => 'Buyer review',
            EditorialActivityKind::Reconciliation => 'Reconciliation',
            EditorialActivityKind::Recheck => 'Recheck',
            default => 'Editorial activity',
        };

        $status = match ($activity->status) {
            EditorialActivityStatus::Pending => 'queued',
            EditorialActivityStatus::Running => 'running',
            EditorialActivityStatus::Paused => 'paused',
            EditorialActivityStatus::AwaitingApproval => 'awaiting approval',
            EditorialActivityStatus::Declined => 'declined',
            EditorialActivityStatus::Completed => 'completed',
            EditorialActivityStatus::Failed => 'needs attention',
            EditorialActivityStatus::Stale => 'stale',
            default => 'active',
        };

        return $kind.' '.$status;
    }
};
?>

<section data-publishing-studio class="publishing-studio space-y-3" x-data="publishingSession('develop')">
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

    <div class="grid min-w-0 gap-10 xl:grid-cols-2">
        <section class="min-w-0">
            <div data-publishing-enter class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">Ideas</flux:heading>
                    <flux:text class="mt-1">Worth keeping. Not on the clock.</flux:text>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                @forelse ($ideas as $article)
                    <article wire:key="idea-{{ $article->id }}" data-publishing-enter class="border-t border-zinc-200 py-5 dark:border-white/10">
                        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 space-y-2">
                                <flux:heading size="sm" class="wrap-anywhere">{{ $this->titleFor($article) }}</flux:heading>
                                @if (filled(data_get($article->workingRevision?->metadata, 'title')))<flux:text class="wrap-anywhere">{{ $this->previewFor($article->idea) }}</flux:text>@endif
                                @if (mb_strlen($article->idea) > 100)
                                    <details class="text-sm text-zinc-600 dark:text-zinc-300">
                                        <summary class="cursor-pointer font-medium text-teal-700 dark:text-cyan-300">View full idea</summary>
                                        <p class="mt-2 whitespace-pre-wrap wrap-anywhere">{{ $article->idea }}</p>
                                    </details>
                                @endif
                            </div>
                            <flux:button :href="route('admin.publishing.articles.show', $article)" size="sm" class="shrink-0">Open workspace</flux:button>
                        </div>
                    </article>
                @empty
                    <div data-publishing-enter class="border-t border-zinc-200 py-7 dark:border-white/10">
                        <flux:heading size="sm">No saved ideas yet</flux:heading>
                        <flux:text class="mt-1">Use the composer when an idea is worth keeping but not ready to develop.</flux:text>
                    </div>
                @endforelse
            </div>

            @if ($ideas->hasPages())
                <div class="mt-5" data-publishing-enter>
                    <flux:pagination :paginator="$ideas" />
                </div>
            @endif
        </section>

        <section class="min-w-0">
            <div data-publishing-enter>
                <flux:heading size="lg">Active writing</flux:heading>
                <flux:text class="mt-1">Pick up the thread.</flux:text>
            </div>

            <div class="mt-4 space-y-3">
                @forelse ($active as $article)
                    <article wire:key="active-{{ $article->id }}" data-publishing-enter class="border-t border-zinc-200 py-5 dark:border-white/10">
                        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 space-y-2">
                                <flux:heading size="sm" class="wrap-anywhere">{{ $this->titleFor($article) }}</flux:heading>
                                <flux:text class="wrap-anywhere">{{ $this->previewFor($article->idea) }}</flux:text>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                    <flux:badge color="teal" size="sm">{{ $this->statusFor($article->currentAttempt) }}</flux:badge>
                                    @if ($this->activityFor($article->currentAttempt))
                                        <span>{{ $this->activityFor($article->currentAttempt) }}</span>
                                    @endif
                                    @if ($article->published_release_id !== null)
                                        <span>Draft in progress on published work</span>
                                    @endif
                                </div>
                            </div>
                            <flux:button :href="route('admin.publishing.articles.show', $article)" size="sm" class="shrink-0">Open workspace</flux:button>
                        </div>
                    </article>
                @empty
                    <div data-publishing-enter class="border-t border-zinc-200 py-7 dark:border-white/10">
                        <flux:heading size="sm">No active writing yet</flux:heading>
                        <flux:text class="mt-1">Develop an idea when you are ready to shape it into an article.</flux:text>
                    </div>
                @endforelse
            </div>

            @if ($active->hasPages())
                <div class="mt-5" data-publishing-enter>
                    <flux:pagination :paginator="$active" />
                </div>
            @endif
        </section>
    </div>
</section>
