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
    <h1 class="text-3xl font-semibold">Published</h1>
    <div class="rounded-xl border border-white/10 bg-white/5 p-6">
        @forelse ($articles as $article)
            <a class="block rounded bg-zinc-950 p-3 hover:bg-zinc-900" href="{{ route('admin.publishing.articles.show', $article) }}">{{ $article->idea }}</a>
        @empty
            <p class="text-sm text-zinc-400">No published releases yet.</p>
        @endforelse
    </div>
</section>
