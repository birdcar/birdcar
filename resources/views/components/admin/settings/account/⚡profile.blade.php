<?php

use App\Settings\Sections\ProfileSection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use App\Settings\SettingsSections;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.settings'), Title('Profile settings')] class extends Component
{

    public string $name = '';

    public string $email = '';

    public string $currentPassword = '';

    public ?string $saveError = null;

    public function mount(): void
    {
        app(SettingsSections::class)->authorize(ProfileSection::class);
        $this->fillFromSaved();
    }

    public function save(): void
    {
        app(SettingsSections::class)->authorize(ProfileSection::class);
        $this->saveError = null;

        $user = Auth::user() ?? abort(403);
        $name = trim($this->name);
        $email = trim($this->email);

        if ($email !== $user->email) {
            $this->validate(
                ['currentPassword' => ['required', 'string', 'current_password:web']],
                [
                    'currentPassword.required' => 'Enter your current password to change your email.',
                    'currentPassword.current_password' => "That password isn't right.",
                ],
            );
        }

        try {
            app(UpdatesUserProfileInformation::class)->update($user, ['name' => $name, 'email' => $email]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->saveError = "Couldn't save. Your changes are still here.";

            return;
        }

        $this->fillFromSaved();
        $this->dispatch('settings-saved', group: 'profile');
    }

    public function discard(): void
    {
        app(SettingsSections::class)->authorize(ProfileSection::class);
        $this->saveError = null;
        $this->resetValidation();
        $this->fillFromSaved();
    }

    public function with(): array
    {
        return [
            'savedEmail' => Auth::user()?->email,
            'errorCount' => count($this->getErrorBag()->keys()),
        ];
    }

    private function fillFromSaved(): void
    {
        $user = Auth::user()?->refresh() ?? abort(403);

        $this->name = $user->name;
        $this->email = $user->email;
        $this->currentPassword = '';
    }
};
?>

<div data-profile-settings class="settings-section">
    <x-admin.settings.group
        name="profile"
        heading="Name and email"
        :fields="['name', 'email', 'currentPassword']"
        save="save"
        discard="discard"
        :error-count="$errorCount"
        :save-error="$saveError"
    >
        <x-admin.settings.row label="Name" help="How you appear in Admin." for="profile-name">
            <flux:input id="profile-name" wire:model="name" autocomplete="name" maxlength="255" aria-describedby="profile-name-help" />
            <flux:error name="name" />
        </x-admin.settings.row>

        <x-admin.settings.row label="Email" help="You sign in with this address. Password resets go here." for="profile-email">
            <flux:input type="email" id="profile-email" wire:model="email" autocomplete="email" aria-describedby="profile-email-help" />
            <flux:error name="email" />
        </x-admin.settings.row>

        <div x-show="$wire.$dirty('email') || $wire.currentPassword !== '' || {{ $errors->has('currentPassword') ? 'true' : 'false' }}" x-cloak>
            <x-admin.settings.row label="Current password" help="Changing your email needs your password." for="profile-current-password" data-profile-password>
                <flux:input type="password" id="profile-current-password" wire:model="currentPassword" autocomplete="current-password" aria-describedby="profile-current-password-help" viewable />
                <flux:error name="currentPassword" />
            </x-admin.settings.row>
        </div>
    </x-admin.settings.group>
</div>
