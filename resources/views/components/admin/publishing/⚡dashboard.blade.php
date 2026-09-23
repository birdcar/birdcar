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
            ->with('currentAttempt')
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
        <h1 class="text-3xl font-semibold">Publishing workspace</h1>
        <p class="mt-2 text-zinc-400">Capture ideas, start active writing, and keep published work separate.</p>
    </div>

    <form wire:submit="saveForLater" class="rounded-xl border border-white/10 bg-white/5 p-6">
        <label class="block text-sm font-medium" for="idea">New idea</label>
        <flux:composer id="idea" wire:model="idea" class="mt-2" placeholder="What should this piece explore?">
            <x-slot:actionsTrailing>
                @can(PublishingPermission::Develop->value)
                    <button type="button" wire:click="developIdea" wire:loading.attr="disabled" class="rounded bg-white px-4 py-2 font-medium text-zinc-950">Develop idea</button>
                @endcan
                @can(PublishingPermission::Write->value)
                    <button class="rounded border border-white/15 px-4 py-2">Save for later</button>
                @endcan
            </x-slot:actionsTrailing>
        </flux:composer>
        @error('idea') <p class="mt-2 text-sm text-red-300">{{ $message }}</p> @enderror
    </form>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-white/10 bg-white/5 p-6">
            <h2 class="text-xl font-semibold">Ideas</h2>
            <div class="mt-4 space-y-3">
                @forelse ($ideas as $article)
                    <a class="block rounded bg-zinc-950 p-3 hover:bg-zinc-900" href="{{ route('admin.publishing.articles.show', $article) }}">{{ $article->idea }}</a>
                @empty
                    <p class="text-sm text-zinc-400">No saved ideas yet.</p>
                @endforelse
            </div>
        </section>
        <section class="rounded-xl border border-white/10 bg-white/5 p-6">
            <h2 class="text-xl font-semibold">Active writing</h2>
            <div class="mt-4 space-y-3">
                @forelse ($active as $article)
                    <a class="block rounded bg-zinc-950 p-3 hover:bg-zinc-900" href="{{ route('admin.publishing.articles.show', $article) }}">
                        <span class="block">{{ $article->idea }}</span>
                        <span class="text-xs text-zinc-500">{{ $article->currentAttempt?->stage?->value ?? 'active' }}</span>
                    </a>
                @empty
                    <p class="text-sm text-zinc-400">No active writing yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</section>
