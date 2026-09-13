@props(['title' => 'Make room for better work.', 'description' => 'I help businesses untangle work that’s become harder to keep up with. Start with a free assessment and a practical report you keep.', 'active' => '', 'article' => false, 'canonical' => null, 'home' => false, 'publishedAt' => null])
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} — Birdcar</title>
        <meta name="description" content="{{ $description }}">
        <meta name="theme-color" content="#291e2e">
        <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
        <meta property="og:title" content="{{ $title }} — Birdcar">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:type" content="{{ $article ? 'article' : 'website' }}">
        <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
        @if ($publishedAt)
            <meta property="article:published_time" content="{{ $publishedAt->toIso8601String() }}">
        @endif
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="alternate" type="application/rss+xml" title="Birdcar Writing" href="{{ route('public.feed') }}">
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body @class(['reading-page' => $article, 'home-page' => $home])>
        <a class="skip-link" href="#main">Skip to content</a>
        <header class="site-header">
            <a class="wordmark" href="{{ route('public.index') }}" aria-label="Birdcar home">Birdcar</a>
            <nav class="desktop-nav" aria-label="Main navigation">
                <a href="{{ route('public.index') }}#how-i-work">How I work</a>
                <a href="{{ route('public.work') }}" @if ($active === 'work') aria-current="page" @endif>Selected work</a>
                <a href="{{ route('public.writing') }}" @if ($active === 'writing') aria-current="page" @endif>Writing</a>
                <a class="nav-booking" href="{{ route('public.assessment') }}" @if ($active === 'assessment') aria-current="page" @endif>Book a free assessment <x-marketing.arrow /></a>
            </nav>
            <details class="mobile-menu">
                <summary>Menu <span class="menu-icon" aria-hidden="true"></span></summary>
                <nav aria-label="Mobile navigation">
                    <a href="{{ route('public.index') }}#how-i-work">How I work</a>
                    <a href="{{ route('public.work') }}">Selected work</a>
                    <a href="{{ route('public.writing') }}">Writing</a>
                    <a href="{{ route('public.assessment') }}">Book a free assessment <x-marketing.arrow /></a>
                </nav>
            </details>
        </header>
        <main id="main" tabindex="-1">{{ $slot }}</main>
        <footer class="site-footer">
            <p>Thoughtful systems.<br>Room for better work.</p>
            <nav aria-label="Footer navigation">
                <a href="{{ route('public.work') }}">Selected work</a>
                <a href="{{ route('public.writing') }}">Writing</a>
                <a href="{{ route('public.assessment') }}">Free assessment</a>
            </nav>
            <span class="copyright">© {{ date('Y') }} Birdcar</span>
        </footer>
    </body>
</html>
