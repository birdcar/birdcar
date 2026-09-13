<?php

use App\Actions\ReadWriting;
use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('public.article');

render(function (View $view, string $slug, ReadWriting $writing): View {
    $article = $writing->find($slug);
    abort_if($article === null, 404);

    return $view->with(['article' => $article, 'content' => $writing->render($article['body'])]);
});

?>
<x-marketing.layout :title="$article['title']" :description="$article['description']" active="writing" :article="true" :published-at="$article['date']" :canonical="route('public.article', ['slug' => $article['slug']]).'/'">
    <article class="essay">
        <header class="essay-heading">
            <a class="back-link" href="{{ route('public.writing') }}"><x-marketing.arrow /> All writing</a>
            <h1>{{ $article['title'] }}</h1>
            <p class="essay-description">{{ $article['description'] }}</p>
            <div class="essay-byline"><span>By Birdcar</span><time datetime="{{ $article['date']->format('Y-m-d') }}">{{ $article['date']->format('F j, Y') }}</time><span>{{ $article['readMinutes'] }} min read</span></div>
        </header>
        <div class="article-prose">{!! $content !!}</div>
        <footer class="essay-end"><a class="text-link" href="{{ route('public.writing') }}">Back to all writing <x-marketing.arrow /></a></footer>
    </article>
</x-marketing.layout>
