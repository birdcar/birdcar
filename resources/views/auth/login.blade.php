<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in · Birdcar Admin</title>@unless(app()->environment('testing')) @vite(['resources/css/admin.css', 'resources/js/admin.js']) @endunless</head>
<body class="admin-shell grid min-h-screen place-items-center bg-zinc-950 p-6 text-zinc-100">
    <main class="w-full max-w-md rounded-2xl border border-white/10 bg-zinc-900 p-8 shadow-xl">
        <h1 class="text-2xl font-semibold">Sign in to Admin</h1>
        @if (session('status'))<p class="mt-4 rounded border border-emerald-400/30 bg-emerald-400/10 px-3 py-2 text-sm text-emerald-200">{{ session('status') }}</p>@endif
        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
            @csrf
            <label class="block text-sm">Email<input name="email" type="email" required autofocus value="{{ old('email') }}" class="mt-1 w-full rounded bg-zinc-950 px-3 py-2"></label>
            <label class="block text-sm">Password<input name="password" type="password" required class="mt-1 w-full rounded bg-zinc-950 px-3 py-2"></label>
            <label class="flex items-center gap-2 text-sm"><input name="remember" type="checkbox" value="1"> Remember me</label>
            @if ($errors->any())<p class="text-sm text-red-300">{{ $errors->first() }}</p>@endif
            <button class="w-full rounded bg-white px-4 py-2 font-medium text-zinc-950">Sign in</button>
        </form>
    </main>
</body>
</html>
