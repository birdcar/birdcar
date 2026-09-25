<?php

use App\Actions\Publishing\ReadPublishedWriting;
use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('public.writing');

render(fn (View $view, ReadPublishedWriting $writing): View => $view->with('articles', $writing->all()));

?>
<x-marketing.layout title="Writing" active="writing" :canonical="route('public.writing').'/'" description="Ideas about making a business work better: the systems, tools, and decisions that shape how people spend their days.">
    <header class="wr-hero">
        <h1>Writing.</h1>
        <div class="wr-intro">
            <p class="wr-lead">Better work, fewer workarounds.</p>
            <p>A report rebuilt by hand. A follow-up someone has to remember. I write about how those small demands shape a business, what software can and can’t help with, and how to make the work better for the people doing it.</p>
            <div class="wr-tools">
                <label class="wr-find" hidden data-find-essay>
                    <span class="visually-hidden">Find an essay</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2" /><path d="m15.5 15.5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    <input type="search" placeholder="Find an essay" autocomplete="off">
                </label>
                <a class="wr-rss" href="{{ route('public.feed') }}">Subscribe via RSS</a>
            </div>
            <p class="wr-find-status" role="status" data-find-status></p>
        </div>
    </header>

    <section class="wr-archive" aria-label="Essay archive">
        <picture>
            <source type="image/webp" srcset="{{ asset('images/writing/writing-canary.webp') }}?v=2">
            <img class="wr-canary" src="{{ asset('images/writing/writing-canary.png') }}?v=2" width="200" height="199" alt="">
        </picture>
        @forelse ($articles->groupBy(fn (array $article): string => $article['date']->format('Y')) as $year => $yearArticles)
            <section class="wr-year" aria-labelledby="year-{{ $year }}" data-year>
                <h2 id="year-{{ $year }}">{{ $year }}</h2>
                <ol role="list">
                    @foreach ($yearArticles as $article)
                        <li data-essay data-search="{{ mb_strtolower($article['title'].' '.$article['description']) }}">
                            <a class="wr-essay" href="{{ route('public.article', ['slug' => $article['slug']]) }}/">
                                <h3>{{ $article['title'] }}</h3>
                                <span class="wr-meta"><time datetime="{{ $article['date']->format('Y-m-d') }}">{{ $article['date']->format('F j, Y') }}</time> <span class="wr-sep" aria-hidden="true">·</span> {{ $article['readMinutes'] }} min read</span>
                                <p>{{ $article['description'] }}</p>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </section>
        @empty
            <div class="wr-empty-archive"><h2>More room for thinking.</h2><p>I’ll be sharing new writing here. In the meantime, take a look at how I work.</p><a class="studio-link" href="{{ route('public.index') }}#how-i-work">How I work <x-marketing.arrow /></a></div>
        @endforelse
        <p class="wr-no-match" hidden data-find-empty>No essays match that yet. Try a different word, or browse the whole list.</p>
    </section>
</x-marketing.layout>
