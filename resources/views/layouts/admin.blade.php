<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Admin' }} · Birdcar</title>
    @unless(app()->environment('testing'))
        @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @endunless
    @livewireStyles
    @fluxAppearance
</head>
<body class="admin-shell min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    <a href="#admin-main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:text-zinc-950">Skip to content</a>

    <div class="min-h-screen lg:grid lg:grid-cols-[16rem_1fr]">
        <aside class="border-b border-white/10 bg-zinc-900/80 p-4 lg:border-b-0 lg:border-r">
            <div class="mb-6 text-lg font-semibold">Birdcar Admin</div>
            <x-admin.navigation />
        </aside>

        <div class="min-w-0">
            <header class="flex items-center justify-between border-b border-white/10 px-6 py-4">
                <div class="text-sm text-zinc-400">{{ config('admin.host') }}</div>
                <form method="POST" action="{{ route('logout') }}" data-admin-logout data-user-id="{{ auth()->id() }}">
                    @csrf
                    <button type="submit" class="rounded border border-white/15 px-3 py-1 text-sm hover:bg-white/10">Log out</button>
                </form>
            </header>
            <main id="admin-main" class="mx-auto max-w-7xl p-6">
                @if (session('status'))
                    <div class="mb-4 rounded border border-emerald-400/40 bg-emerald-400/10 p-3 text-sm text-emerald-100">{{ session('status') }}</div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScriptConfig
    @fluxScripts
</body>
</html>
