<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Preview · {{ $article->idea }}</title>@unless(app()->environment('testing')) @vite(['resources/css/app.css']) @endunless</head>
<body class="bg-white text-zinc-950">
    <main class="mx-auto max-w-3xl px-6 py-10">
        <p class="mb-4 text-sm text-zinc-500">Private preview · revision #{{ $revision->number }}</p>
        {!! $html !!}
    </main>
</body>
</html>
