<?php

use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Services\MarketingSite;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component
{
    use WithPagination;

    public function with(): array
    {
        return [
            'articles' => Article::query()
                ->where('author_id', auth()->id())
                ->whereNotNull('published_release_id')
                ->with(['publishedRelease', 'workingRevision', 'currentAttempt'])
                ->latest('first_published_at')
                ->latest('id')
                ->paginate(perPage: 12, pageName: 'publishedPage'),
        ];
    }

    public function liveTitleFor(Article $article): string
    {
        $title = data_get($article->publishedRelease?->payload, 'metadata.title');

        return is_string($title) && trim($title) !== '' ? $title : $article->idea;
    }

    public function originalDateFor(Article $article): ?CarbonImmutable
    {
        $release = $article->publishedRelease;
        $value = data_get($release?->payload, 'original_public_date')
            ?? $article->first_published_at
            ?? $release?->published_at;

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            return CarbonImmutable::parse($value);
        }

        return null;
    }

    public function hasDraftInProgress(Article $article): bool
    {
        $attempt = $article->currentAttempt;

        if (! $attempt instanceof PublishingAttempt || $attempt->abandoned_at !== null) {
            return false;
        }

        if (in_array($attempt->stage, [EditorialStage::Published, EditorialStage::Abandoned], true)) {
            return false;
        }

        return true;
    }

    public function publicUrlFor(Article $article): ?string
    {
        if (! Route::has('public.article')) {
            return null;
        }

        $release = $article->publishedRelease;
        if (! $release instanceof ArticleRelease || $release->status !== 'published' || $release->published_at === null) {
            return null;
        }

        $slug = data_get($release->payload, 'canonical_slug');
        $slug = is_string($slug) && trim($slug) !== '' ? $slug : (string) $article->slug;

        return app(MarketingSite::class)->url(route('public.article', ['slug' => $slug], absolute: false));
    }
};
?>

<section data-publishing-studio class="space-y-6">
    <div data-publishing-enter>
        <flux:heading level="1" size="xl">Published</flux:heading>
        <flux:text class="mt-2 max-w-3xl">The live library stays separate from new ideas and active writing. Drafts in progress are marked without hiding the published article.</flux:text>
    </div>

    <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5">
        @forelse ($articles as $article)
            <article wire:key="published-{{ $article->id }}" data-publishing-enter class="rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                <div class="flex min-w-0 flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 space-y-2">
                        <flux:heading size="sm" class="wrap-anywhere">{{ $this->liveTitleFor($article) }}</flux:heading>
                        <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            @if ($this->originalDateFor($article))
                                <span>Original date {{ $this->originalDateFor($article)->format('M j, Y') }}</span>
                            @else
                                <span>Original date unavailable</span>
                            @endif
                            @if ($this->hasDraftInProgress($article))
                                <flux:badge color="cyan" size="sm">Draft in progress</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Live</flux:badge>
                            @endif
                        </div>
                        @if ($this->liveTitleFor($article) !== $article->idea)
                            <flux:text class="wrap-anywhere">Idea: {{ $article->idea }}</flux:text>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <flux:button :href="route('admin.publishing.articles.show', $article)" size="sm">Open workspace</flux:button>
                        @if ($this->publicUrlFor($article))
                            <flux:button :href="$this->publicUrlFor($article)" size="sm" variant="ghost" target="_blank" rel="noopener">Public link</flux:button>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div data-publishing-enter class="rounded-lg border border-dashed border-zinc-300 p-6 text-center dark:border-white/15">
                <flux:heading size="sm">No published releases yet</flux:heading>
                <flux:text class="mt-1">Approved and delivered writing will appear here after it goes live.</flux:text>
            </div>
        @endforelse

        @if ($articles->hasPages())
            <div class="pt-2" data-publishing-enter>
                <flux:pagination :paginator="$articles" />
            </div>
        @endif
    </div>
</section>
