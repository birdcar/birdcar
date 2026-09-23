<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>Set password · Birdcar Admin</title>@unless(app()->environment('testing')) @vite(['resources/css/admin.css', 'resources/js/admin.js']) @endunless</head>
<body class="admin-shell grid min-h-screen place-items-center bg-zinc-950 p-6 text-zinc-100">
    <main class="w-full max-w-md rounded-2xl border border-white/10 bg-zinc-900 p-8 shadow-xl">
        <h1 class="text-2xl font-semibold">Set your Admin password</h1>
        <p class="mt-3 text-sm text-zinc-300">Create a password to finish setting up your Birdcar Admin access. If this link has expired, ask the operator to resend your invitation.</p>
        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4" autocomplete="off">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <label class="block text-sm">Email<input name="email" type="email" required autofocus value="{{ old('email', $request->query('email')) }}" autocomplete="email" class="mt-1 w-full rounded bg-zinc-950 px-3 py-2"></label>
            <label class="block text-sm">New password<input name="password" type="password" required autocomplete="new-password" class="mt-1 w-full rounded bg-zinc-950 px-3 py-2"></label>
            <label class="block text-sm">Confirm new password<input name="password_confirmation" type="password" required autocomplete="new-password" class="mt-1 w-full rounded bg-zinc-950 px-3 py-2"></label>
            @if ($errors->any())<p class="text-sm text-red-300">{{ $errors->first() }} Ask the operator to resend your invitation if the link no longer works.</p>@endif
            <button class="w-full rounded bg-white px-4 py-2 font-medium text-zinc-950">Set password</button>
        </form>
    </main>
</body>
</html>
