@props(['title', 'heading'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $title }} · Birdcar Admin</title>
    @fluxAppearance
    @unless(app()->environment('testing'))
        @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @endunless
    @livewireStyles
</head>
<body class="admin-shell min-h-screen bg-zinc-50 text-zinc-800 antialiased dark:bg-zinc-900 dark:text-zinc-100">
    <main class="mx-auto w-full max-w-md px-6 pb-12 pt-8 sm:pt-16">
        <flux:brand :href="route('login')" logo="/favicon.svg" name="Admin" aria-label="Admin sign in" class="mb-8" />
        <flux:heading size="xl" level="1">{{ $heading }}</flux:heading>
        @if (session('status'))
            <flux:callout variant="success" class="mt-6" role="status" :text="session('status')" />
        @endif
        <div class="mt-6">{{ $slot }}</div>
    </main>
    @livewireScriptConfig
    @fluxScripts
</body>
</html>
