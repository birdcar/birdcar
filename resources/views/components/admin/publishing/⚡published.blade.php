<?php

use App\Models\Article;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.admin')] class extends Component
{
    public function with(): array
    {
        return [
            'articles' => Article::query()
                ->where('author_id', auth()->id())
                ->whereNotNull('published_release_id')
                ->with('publishedRelease')
                ->latest('first_published_at')
                ->get(),
        ];
    }
};
?>

<section class="space-y-6">
    <flux:heading level="1" size="xl">Published</flux:heading>
    <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
        @forelse ($articles as $article)
            <flux:button class="w-full min-w-0 justify-start whitespace-normal" href="{{ route('admin.publishing.articles.show', $article) }}">{{ $article->idea }}</flux:button>
        @empty
            <flux:text>No published releases yet.</flux:text>
        @endforelse
    </div>
</section>
