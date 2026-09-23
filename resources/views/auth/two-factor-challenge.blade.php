<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Two-factor challenge · Birdcar Admin</title>@unless(app()->environment('testing')) @vite(['resources/css/admin.css', 'resources/js/admin.js']) @endunless</head>
<body class="admin-shell grid min-h-screen place-items-center bg-zinc-950 p-6 text-zinc-100">
    <main class="w-full max-w-md rounded-2xl border border-white/10 bg-zinc-900 p-8 shadow-xl">
        <h1 class="text-2xl font-semibold">Two-factor challenge</h1>
        <form method="POST" action="{{ route('two-factor.login') }}" class="mt-6 space-y-4">
            @csrf
            <label class="block text-sm">Authentication code<input name="code" autocomplete="one-time-code" class="mt-1 w-full rounded bg-zinc-950 px-3 py-2"></label>
            <label class="block text-sm">Recovery code<input name="recovery_code" class="mt-1 w-full rounded bg-zinc-950 px-3 py-2"></label>
            @if ($errors->any())<p class="text-sm text-red-300">{{ $errors->first() }}</p>@endif
            <button class="w-full rounded bg-white px-4 py-2 font-medium text-zinc-950">Continue</button>
        </form>
    </main>
</body>
</html>
