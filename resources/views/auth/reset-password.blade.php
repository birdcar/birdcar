<x-admin.auth-layout title="Set password" heading="Set your Admin password">
    <flux:text>Create a password to finish setting up your Birdcar Admin access. If this link has expired, ask the operator to resend your invitation.</flux:text>
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <flux:input name="email" type="email" label="Email" :value="old('email', $request->query('email'))" autocomplete="email" required autofocus />
        <flux:input name="password" type="password" label="New password" autocomplete="new-password" required viewable />
        <flux:input name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" required viewable />
        <flux:button type="submit" variant="primary" class="w-full">Set password</flux:button>
    </form>
</x-admin.auth-layout>
