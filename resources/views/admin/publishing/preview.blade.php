<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Preview · {{ $metadata['title'] ?? $article->idea }}</title>
    @unless(app()->environment('testing')) @vite(['resources/css/app.css']) @endunless
</head>
<body class="marketing-page reading-page">
    <main>
        <article class="essay">
            <header class="essay-heading">
                <p class="back-link">Private preview · {{ $label }}</p>
                <h1>{{ $metadata['title'] ?? $article->idea }}</h1>
                @if (filled($metadata['description'] ?? null))<p class="essay-description">{{ $metadata['description'] }}</p>@endif
                <div class="essay-byline"><span>By Birdcar</span>@if (filled($metadata['date'] ?? null))<span>{{ $metadata['date'] }}</span>@endif</div>
            </header>
            <div class="article-prose">{!! $html !!}</div>
        </article>
    </main>
</body>
</html>
