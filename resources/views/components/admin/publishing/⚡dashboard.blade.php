<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Permission as AdminPermission;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\Publishing\EditorialStage;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.admin')] class extends Component
{
    public string $idea = '';

    public function saveForLater(WriteArticle $articles): void
    {
        Gate::authorize(AdminPermission::View->value);
        Gate::authorize(PublishingPermission::Write->value);
        $validated = $this->validate(['idea' => ['required', 'string', 'max:5000']]);
        $article = $articles->capture(auth()->user(), $validated['idea']);
        $this->idea = '';
        session()->flash('status', 'Idea saved for later.');
        $this->redirectRoute('admin.publishing.articles.show', ['article' => $article], navigate: false);
    }

    public function developIdea(WriteArticle $articles, AdvancePublishingAttempt $attempts): void
    {
        Gate::authorize(AdminPermission::View->value);
        Gate::authorize(PublishingPermission::Write->value);
        Gate::authorize(PublishingPermission::Develop->value);
        $validated = $this->validate(['idea' => ['required', 'string', 'max:5000']]);
        $article = $articles->capture(auth()->user(), $validated['idea']);
        $attempts->develop(auth()->user(), $article, null, ['idea' => $validated['idea']]);
        $this->idea = '';
        session()->flash('status', 'Idea moved into active writing.');
        $this->redirectRoute('admin.publishing.articles.show', ['article' => $article], navigate: false);
    }

    public function with(): array
    {
        $ideas = Article::query()
            ->where('author_id', auth()->id())
            ->whereNull('current_attempt_id')
            ->whereNull('published_release_id')
            ->latest()
            ->limit(25)
            ->get();

        $active = Article::query()
            ->with(['currentAttempt.editorialActivities'])
            ->where('author_id', auth()->id())
            ->whereNotNull('current_attempt_id')
            ->whereNull('published_release_id')
            ->latest()
            ->limit(25)
            ->get();

        return ['ideas' => $ideas, 'active' => $active];
    }
};
?>

<section class="space-y-8">
    <div>
        <flux:heading level="1" size="xl">Publishing workspace</flux:heading>
        <flux:text class="mt-2">Capture ideas, start active writing, and keep published work separate.</flux:text>
    </div>

    <form wire:submit="saveForLater" class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
        <flux:field>
            <flux:label>New idea</flux:label>
            <flux:composer id="idea" wire:model="idea" placeholder="What should this piece explore?">
                <x-slot:actionsTrailing>
                    @can(PublishingPermission::Develop->value)
                        <flux:button type="button" wire:click="developIdea" variant="primary">Develop idea</flux:button>
                    @endcan
                    @can(PublishingPermission::Write->value)
                        <flux:button type="submit">Save for later</flux:button>
                    @endcan
                </x-slot:actionsTrailing>
            </flux:composer>
            <flux:error name="idea" />
        </flux:field>
    </form>

    <div class="grid min-w-0 gap-6 lg:grid-cols-2">
        <section class="min-w-0 rounded-xl border border-zinc-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
            <flux:heading size="lg">Ideas</flux:heading>
            <div class="mt-4 space-y-3">
                @forelse ($ideas as $article)
                    <flux:button class="w-full min-w-0 justify-start whitespace-normal" href="{{ route('admin.publishing.articles.show', $article) }}">{{ $article->idea }}</flux:button>
                @empty
                    <flux:text>No saved ideas yet.</flux:text>
                @endforelse
            </div>
        </section>
        <section class="min-w-0 rounded-xl border border-zinc-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
            <flux:heading size="lg">Active writing</flux:heading>
            <div class="mt-4 space-y-3">
                @forelse ($active as $article)
                    <flux:button class="h-auto w-full min-w-0 justify-start whitespace-normal py-3" href="{{ route('admin.publishing.articles.show', $article) }}">
                        <span class="block min-w-0 text-left">
                            <span class="block">{{ $article->idea }}</span>
                            @php($latestActivity = $article->currentAttempt?->editorialActivities->sortByDesc('id')->first())
                            <span class="block text-xs text-zinc-500">{{ $article->currentAttempt?->stage?->value ?? 'active' }} @if($article->currentAttempt?->paused_at) · blocked: {{ $article->currentAttempt?->pause_reason }} @elseif($latestActivity) · {{ $latestActivity->kind->value }} {{ $latestActivity->status->value }} @endif</span>
                        </span>
                    </flux:button>
                @empty
                    <flux:text>No active writing yet.</flux:text>
                @endforelse
            </div>
        </section>
    </div>
</section>
