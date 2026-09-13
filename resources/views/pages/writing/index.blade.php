<?php

use App\Actions\ReadWriting;
use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('public.writing');

render(fn (View $view, ReadWriting $writing): View => $view->with('articles', $writing->all()));

?>
<x-marketing.layout title="Writing" active="writing" :canonical="route('public.writing').'/'" description="Ideas about making a business work better: the systems, tools, and decisions that shape how people spend their days.">
    <header class="page-title writing-title"><h1>Writing.</h1><div><p>Better ways to run the work.</p><p>I write about the systems, tools, and decisions that shape how a business runs. What helps people do good work, what gets in their way, and what’s worth changing.</p></div></header>
    <section class="writing-archive section-space" aria-label="Essay archive">
        <div class="archive-heading"><span>All writing</span><a href="{{ route('public.feed') }}">Subscribe via RSS <x-marketing.arrow /></a></div>
        @forelse ($articles->groupBy(fn (array $article): string => $article['date']->format('Y')) as $year => $yearArticles)
            <div class="archive-year"><h2>{{ $year }}</h2><ol>@foreach ($yearArticles as $article)<li><a class="essay-row" href="{{ route('public.article', ['slug' => $article['slug']]) }}/"><div><h3>{{ $article['title'] }}</h3><p>{{ $article['description'] }}</p><span class="essay-meta"><time datetime="{{ $article['date']->format('Y-m-d') }}">{{ $article['date']->format('F j, Y') }}</time><span>{{ $article['readMinutes'] }} min read</span></span></div><x-marketing.arrow /></a></li>@endforeach</ol></div>
        @empty
            <div class="archive-empty"><h2>More room for thinking.</h2><p>I’ll be sharing new writing here. In the meantime, take a look at how I work.</p><a class="text-link" href="{{ route('public.index') }}#how-i-work">How I work <x-marketing.arrow /></a></div>
        @endforelse
    </section>
</x-marketing.layout>
