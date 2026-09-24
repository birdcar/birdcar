<x-admin.auth-layout title="Two-factor challenge" heading="Two-factor challenge">
    <flux:text>Enter a code from your authenticator, or use one of your recovery codes.</flux:text>
    <form method="POST" action="{{ route('two-factor.login') }}" class="mt-6 space-y-6">
        @csrf
        <flux:input name="code" label="Authentication code" autocomplete="one-time-code" inputmode="numeric" autofocus />
        <flux:input name="recovery_code" label="Recovery code" autocomplete="off" />
        <flux:button type="submit" variant="primary" class="w-full">Continue</flux:button>
    </form>
</x-admin.auth-layout>
