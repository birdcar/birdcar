@php
    use App\Authorization\Publishing\Permission as PublishingPermission;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Admin' }} · Birdcar</title>
    @fluxAppearance
    @unless(app()->environment('testing'))
        @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @endunless
    @livewireStyles
</head>
<body class="admin-shell min-h-screen bg-white text-zinc-800 antialiased dark:bg-zinc-800 dark:text-zinc-100">
    <a href="#admin-main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:text-zinc-950">Skip to content</a>

    <flux:sidebar sticky collapsible class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <flux:sidebar.brand :href="route('admin.index')" logo="/favicon.svg" name="Admin" aria-label="Admin home" />
            <flux:sidebar.collapse tooltip="Toggle Admin navigation" />
        </flux:sidebar.header>
        <flux:sidebar.nav aria-label="Admin modules">
            <flux:sidebar.item :href="route('admin.index')" :current="request()->routeIs('admin.index')" :aria-current="request()->routeIs('admin.index') ? 'page' : null" aria-label="Home" icon="home">Home</flux:sidebar.item>
            @can(PublishingPermission::View->value)
                <flux:sidebar.item :href="route('admin.publishing.dashboard')" :current="request()->routeIs('admin.publishing.*')" :aria-current="request()->routeIs('admin.publishing.*') ? 'page' : null" aria-label="Publishing" icon="document-text">Publishing</flux:sidebar.item>
            @endcan
        </flux:sidebar.nav>
        <flux:sidebar.spacer />
        <flux:dropdown position="top" align="start">
            <flux:sidebar.profile :initials="auth()->user()->initials()" :name="auth()->user()->name" aria-label="Account menu" />
            <flux:menu>
                <flux:menu.heading>{{ auth()->user()->name }}</flux:menu.heading>
                <flux:menu.separator />
                <form method="POST" action="{{ route('logout') }}" data-admin-logout data-user-id="{{ auth()->id() }}">
                    @csrf
                    <flux:menu.item type="submit" icon="arrow-right-start-on-rectangle">Log out</flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:sidebar>

    <flux:header sticky class="min-w-0 gap-3 border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
        <flux:sidebar.toggle icon="bars-2" class="lg:hidden" aria-label="Open Admin navigation" />
        <span @class(['shrink-0 text-sm font-medium', 'max-sm:sr-only' => request()->routeIs('admin.publishing.*')])>{{ request()->routeIs('admin.publishing.*') ? 'Publishing' : 'Home' }}</span>
        <x-admin.navigation />
    </flux:header>

    <flux:main class="min-w-0">
        <main id="admin-main" class="mx-auto w-full min-w-0 max-w-[100rem]" tabindex="-1">
            @if (session('status'))
                <flux:callout variant="success" class="mb-6" role="status" :text="session('status')" />
            @endif
            {{ $slot }}
        </main>
    </flux:main>

    @livewireScriptConfig
    @fluxScripts
</body>
</html>
