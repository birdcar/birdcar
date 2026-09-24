<x-admin.auth-layout title="Sign in" heading="Sign in to Admin">
    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf
        <flux:field>
            <flux:label>Email</flux:label>
            <flux:input name="email" type="email" :value="old('email')" :invalid="$errors->has('email')" autocomplete="username" required autofocus />
            <flux:error name="email" />
        </flux:field>
        <flux:field>
            <flux:label>Password</flux:label>
            <flux:input name="password" type="password" :invalid="$errors->has('password')" autocomplete="current-password" required viewable />
            <flux:error name="password" />
        </flux:field>
        <flux:checkbox name="remember" value="1" label="Remember me" :checked="(bool) old('remember')" />
        <flux:button type="submit" variant="primary" class="w-full">Sign in</flux:button>
    </form>
</x-admin.auth-layout>
