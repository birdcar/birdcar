<?php

use App\Models\User;
use App\Settings\PasswordConfirmation;
use App\Settings\Sections\SecuritySection;
use App\Settings\SettingsSections;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Support\WebAuthn;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;

new #[Layout('layouts.settings'), Title('Security settings')] class extends Component
{
    /**
     * Component methods that may resume once the password is confirmed.
     */
    private const array ConfirmableActions = ['enableTwoFactor', 'showRecoveryCodes', 'regenerateRecoveryCodes', 'disableTwoFactor', 'resumePasskeyRegistration', 'removePasskey'];

    public bool $confirmingPassword = false;

    public string $confirmablePassword = '';

    public ?string $confirmableAction = null;

    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public ?string $passwordStatus = null;

    public string $code = '';

    public bool $showingRecoveryCodes = false;

    public bool $confirmingTwoFactorDisable = false;

    public string $passkeyName = '';

    public ?string $passkeyStatus = null;

    public ?string $passkeyError = null;

    public ?int $passkeyPendingRemoval = null;

    public bool $confirmingPasskeyRemoval = false;

    public function mount(): void
    {
        app(SettingsSections::class)->authorize(SecuritySection::class);
    }

    public function confirmPassword(): void
    {
        app(SettingsSections::class)->authorize(SecuritySection::class);
        app(PasswordConfirmation::class)->confirm($this->user(), $this->confirmablePassword, 'confirmablePassword');

        $action = $this->confirmableAction;
        $this->cancelPasswordConfirmation();

        if ($action !== null && in_array($action, self::ConfirmableActions, true)) {
            $this->{$action}();
        }
    }

    public function cancelPasswordConfirmation(): void
    {
        $this->confirmingPassword = false;
        $this->confirmablePassword = '';
        $this->confirmableAction = null;
        $this->resetValidation('confirmablePassword');
    }

    public function updatePassword(): void
    {
        app(SettingsSections::class)->authorize(SecuritySection::class);
        $this->passwordStatus = null;

        app(UpdatesUserPasswords::class)->update($this->user(), [
            'current_password' => $this->currentPassword,
            'password' => $this->password,
            'password_confirmation' => $this->passwordConfirmation,
        ]);

        $this->reset('currentPassword', 'password', 'passwordConfirmation');
        $this->passwordStatus = 'Password updated. Use it the next time you sign in.';
    }

    public function enableTwoFactor(): void
    {
        app(SettingsSections::class)->authorize(SecuritySection::class);

        if (! $this->ensurePasswordIsConfirmed('enableTwoFactor')) {
            return;
        }

        app(EnableTwoFactorAuthentication::class)($this->user());
        $this->reset('code', 'showingRecoveryCodes');
    }

    public function confirmTwoFactor(): void
    {
        app(SettingsSections::class)->authorize(SecuritySection::class);

        $this->validate(['code' => ['required', 'string']], ['code.required' => 'Enter the six-digit code from your authenticator app.']);

        try {
            app(ConfirmTwoFactorAuthentication::class)($this->user(), trim($this->code));
        } catch (ValidationException) {
            throw ValidationException::withMessages(['code' => "That code didn't match. Check the time on your device and try the newest code."]);
        }

        $this->code = '';
        $this->showingRecoveryCodes = true;
    }

    public function cancelTwoFactorSetup(): void
    {
        app(SettingsSections::class)->authorize(SecuritySection::class);

        $user = $this->user();
        if ($user->two_factor_confirmed_at === null) {
            app(DisableTwoFactorAuthentication::class)($user);
        }

        $this->reset('code');
        $this->resetValidation('code');
    }

    public function showRecoveryCodes(): void
    {
        app(SettingsSections::class)->authorize(SecuritySection::class);

        if (! $this->ensurePasswordIsConfirmed('showRecoveryCodes')) {
            return;
        }

        $this->showingRecoveryCodes = true;
    }

    public function regenerateRecoveryCodes(): void
    {
        app(SettingsSections::class)->authorize(SecuritySection::class);

        if (! $this->ensurePasswordIsConfirmed('regenerateRecoveryCodes')) {
            return;
        }

        app(GenerateNewRecoveryCodes::class)($this->user());
        $this->showingRecoveryCodes = true;
    }

    public function disableTwoFactor(): void
    {
        app(SettingsSections::class)->authorize(SecuritySection::class);
        $this->confirmingTwoFactorDisable = false;

        if (! $this->ensurePasswordIsConfirmed('disableTwoFactor')) {
            return;
        }

        app(DisableTwoFactorAuthentication::class)($this->user());
        $this->reset('showingRecoveryCodes', 'code');
    }

    /**
     * Starts a WebAuthn registration; the browser completes it with finishPasskeyRegistration().
     *
     * @return array{options: array<array-key, mixed>}|null
     */
    public function beginPasskeyRegistration(): ?array
    {
        app(SettingsSections::class)->authorize(SecuritySection::class);
        $this->passkeyError = null;
        $this->passkeyStatus = null;

        $this->validate(
            ['passkeyName' => ['required', 'string', 'max:255']],
            ['passkeyName.required' => 'Name this passkey so you can recognize it later.', 'passkeyName.max' => 'Keep the name to 255 characters or fewer.'],
        );

        if (! $this->ensurePasswordIsConfirmed('resumePasskeyRegistration')) {
            return null;
        }

        $options = app(GenerateRegistrationOptions::class)($this->user());
        session()->put('passkey.registration_options', WebAuthn::toJson($options));

        return ['options' => WebAuthn::toBrowserArray($options)];
    }

    /**
     * @param  array<string, mixed>  $credential
     */
    public function finishPasskeyRegistration(array $credential): void
    {
        app(SettingsSections::class)->authorize(SecuritySection::class);

        $serialized = session()->pull('passkey.registration_options');

        if (! app(PasswordConfirmation::class)->recentlyConfirmed() || ! is_string($serialized)) {
            $this->passkeyError = 'Passkey setup expired. Choose Add passkey to try again.';

            return;
        }

        try {
            app(StorePasskey::class)(
                $this->user(),
                trim($this->passkeyName),
                WebAuthn::fromJson(json_encode($credential) ?: '{}', PublicKeyCredential::class),
                WebAuthn::fromJson($serialized, PublicKeyCredentialCreationOptions::class),
            );
        } catch (ValidationException) {
            $this->passkeyError = "Couldn't add that passkey. Choose Add passkey to try again.";

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->passkeyError = "Couldn't add that passkey. Choose Add passkey to try again.";

            return;
        }

        $this->passkeyStatus = trim($this->passkeyName).' was added. You can use it to sign in.';
        $this->passkeyName = '';
    }

    public function passkeyCeremonyFailed(string $reason): void
    {
        $this->passkeyError = match ($reason) {
            'NotAllowedError', 'AbortError' => 'Passkey setup was canceled or timed out. Choose Add passkey to try again.',
            'InvalidStateError' => 'This device already has a passkey for your account.',
            'unsupported' => "This browser can't create passkeys. Try a current version of Safari, Chrome, Edge, or Firefox.",
            default => "Couldn't add that passkey. Choose Add passkey to try again.",
        };
    }

    public function confirmPasskeyRemoval(int $passkeyId): void
    {
        app(SettingsSections::class)->authorize(SecuritySection::class);
        $this->passkeyPendingRemoval = $this->passkeyFor($passkeyId)->getKey();
        $this->confirmingPasskeyRemoval = true;
    }

    public function removePasskey(): void
    {
        app(SettingsSections::class)->authorize(SecuritySection::class);

        $this->confirmingPasskeyRemoval = false;

        if ($this->passkeyPendingRemoval === null) {
            return;
        }

        if (! $this->ensurePasswordIsConfirmed('removePasskey')) {
            return;
        }

        $passkey = $this->passkeyFor($this->passkeyPendingRemoval);
        app(DeletePasskey::class)($this->user(), $passkey);

        $this->passkeyPendingRemoval = null;
        $this->passkeyError = null;
        $this->passkeyStatus = $passkey->name.' was removed.';
    }

    public function with(): array
    {
        $user = $this->user();
        $pending = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;

        return [
            'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
            'twoFactorPending' => $pending,
            'twoFactorEnabledAt' => $user->two_factor_confirmed_at,
            'qrCode' => $pending ? $user->twoFactorQrCodeSvg() : null,
            'setupKey' => $pending ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret) : null,
            'recoveryCodes' => $this->showingRecoveryCodes && $user->two_factor_secret !== null ? $user->recoveryCodes() : [],
            'passkeys' => $user->passkeys()->latest()->get(['id', 'user_id', 'name', 'credential', 'last_used_at', 'created_at']),
            'pendingPasskeyName' => $this->passkeyPendingRemoval !== null ? $user->passkeys()->whereKey($this->passkeyPendingRemoval)->value('name') : null,
        ];
    }

    /**
     * Returns true when the password was confirmed recently; otherwise opens the dialog to resume $then.
     */
    private function ensurePasswordIsConfirmed(string $then): bool
    {
        if (app(PasswordConfirmation::class)->recentlyConfirmed()) {
            return true;
        }

        $this->confirmableAction = $then;
        $this->confirmablePassword = '';
        $this->resetValidation('confirmablePassword');
        $this->confirmingPassword = true;

        return false;
    }

    protected function resumePasskeyRegistration(): void
    {
        $this->dispatch('settings-passkey-confirmed');
    }

    private function passkeyFor(int $passkeyId): Passkey
    {
        $passkey = $this->user()->passkeys()->whereKey($passkeyId)->first(['id', 'user_id', 'name', 'credential']);

        return $passkey instanceof Passkey ? $passkey : abort(404);
    }

    private function user(): User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : abort(403);
    }
};
?>

<div data-security-settings class="settings-section">
    <x-admin.settings.group name="password" heading="Password" description="Use a long password you don't use anywhere else.">
        <form wire:submit="updatePassword" class="settings-rows-form" data-security-password>
            <x-admin.settings.row label="Current password" for="security-current-password">
                <flux:input type="password" id="security-current-password" wire:model="currentPassword" autocomplete="current-password" viewable />
                <flux:error name="current_password" />
            </x-admin.settings.row>

            <x-admin.settings.row label="New password" help="At least 8 characters." for="security-password">
                <flux:input type="password" id="security-password" wire:model="password" autocomplete="new-password" aria-describedby="security-password-help" viewable />
                <flux:error name="password" />
            </x-admin.settings.row>

            <x-admin.settings.row label="Confirm new password" for="security-password-confirmation">
                <flux:input type="password" id="security-password-confirmation" wire:model="passwordConfirmation" autocomplete="new-password" viewable />
            </x-admin.settings.row>

            <div class="settings-actions">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="updatePassword">Update password</flux:button>
                @if ($passwordStatus)
                    <p role="status" class="settings-feedback">{{ $passwordStatus }}</p>
                @endif
            </div>
        </form>
    </x-admin.settings.group>

    <x-admin.settings.group name="two-factor" heading="Two-factor authentication" description="Asks for a code from your authenticator app after your password when you sign in.">
        @if ($twoFactorPending)
            <form wire:submit="confirmTwoFactor" class="settings-rows-form" data-two-factor-state="pending">
                <x-admin.settings.row label="Scan this code" help="Use an authenticator app such as 1Password, Authy or Google Authenticator.">
                    <div class="settings-qr" role="img" aria-label="QR code for setting up two-factor authentication">{!! $qrCode !!}</div>
                </x-admin.settings.row>

                <x-admin.settings.row label="Or enter this key" help="For apps that can't scan.">
                    <code class="settings-environment-value settings-setup-key" data-two-factor-key>{{ $setupKey }}</code>
                </x-admin.settings.row>

                <x-admin.settings.row label="Enter the code" help="Type the six-digit code your app shows now." for="two-factor-code">
                    <flux:otp id="two-factor-code" wire:model="code" length="6" label="Authentication code" label:sr-only />
                    <flux:error name="code" />
                </x-admin.settings.row>

                <div class="settings-actions">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="confirmTwoFactor">Turn on two-factor</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelTwoFactorSetup">Cancel</flux:button>
                </div>
            </form>
        @elseif ($twoFactorEnabled)
            <x-admin.settings.row label="Status" :help="$twoFactorEnabledAt ? 'On since '.$twoFactorEnabledAt->timezone(auth()->user()->timezone ?? config('app.timezone'))->format('F j, Y').'.' : 'On.'" data-two-factor-state="enabled">
                <span class="settings-credential">
                    <flux:icon.check-circle variant="mini" />
                    On
                </span>
                <x-slot:trailing>
                    <flux:button type="button" size="sm" variant="ghost" wire:click="$set('confirmingTwoFactorDisable', true)">Turn off</flux:button>
                </x-slot:trailing>
            </x-admin.settings.row>

            <x-admin.settings.row label="Recovery codes" help="Each code signs you in once if you lose your device. Keep them somewhere safe.">
                @if ($recoveryCodes !== [])
                    <div class="settings-recovery" x-data="recoveryCodes(@js($recoveryCodes))" data-recovery-codes>
                        <ul class="settings-recovery-codes">
                            @foreach ($recoveryCodes as $recoveryCode)
                                <li>{{ $recoveryCode }}</li>
                            @endforeach
                        </ul>
                        <div class="settings-recovery-actions">
                            <flux:button type="button" size="sm" icon="clipboard" x-on:click="copy">
                                <span x-show="! copied">Copy</span>
                                <span x-show="copied" x-cloak>Copied</span>
                            </flux:button>
                            <flux:button type="button" size="sm" icon="arrow-down-tray" x-on:click="download">Download</flux:button>
                            <flux:button type="button" size="sm" variant="ghost" icon="arrow-path" wire:click="regenerateRecoveryCodes">Make new codes</flux:button>
                        </div>
                    </div>
                @else
                    <flux:button type="button" size="sm" wire:click="showRecoveryCodes">Show recovery codes</flux:button>
                @endif
            </x-admin.settings.row>
        @else
            <x-admin.settings.row label="Status" help="Off. Your password alone signs you in." data-two-factor-state="disabled">
                <flux:button type="button" wire:click="enableTwoFactor" wire:loading.attr="disabled" wire:target="enableTwoFactor">Set up two-factor</flux:button>
            </x-admin.settings.row>
        @endif
    </x-admin.settings.group>

    <x-admin.settings.group name="passkeys" heading="Passkeys" description="Sign in with Touch ID, Face ID, Windows Hello or a security key instead of typing your password.">
        @forelse ($passkeys as $passkey)
            <x-admin.settings.row
                wire:key="passkey-{{ $passkey->id }}"
                :label="$passkey->name"
                :help="collect([$passkey->authenticator, 'Added '.$passkey->created_at?->format('M j, Y'), $passkey->last_used_at ? 'last used '.$passkey->last_used_at->diffForHumans() : 'not used yet'])->filter()->implode(' · ')"
                data-passkey="{{ $passkey->id }}"
            >
                <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="confirmPasskeyRemoval({{ $passkey->id }})">Remove</flux:button>
            </x-admin.settings.row>
        @empty
            <x-admin.settings.row label="No passkeys yet" help="Add one on each device you use. Your password keeps working." data-passkeys-empty>
                <span></span>
            </x-admin.settings.row>
        @endforelse

        <form wire:submit="$js.addPasskey" class="settings-rows-form" data-passkey-add>
            <x-admin.settings.row label="Add a passkey" help="Name it after the device, like “MacBook” or “iPhone”." for="passkey-name">
                <div class="settings-inline-action">
                    <flux:input id="passkey-name" wire:model="passkeyName" maxlength="255" placeholder="MacBook" aria-describedby="passkey-name-help" />
                    <flux:button type="submit" icon="finger-print" wire:loading.attr="disabled" wire:target="beginPasskeyRegistration,finishPasskeyRegistration">Add passkey</flux:button>
                </div>
                <flux:error name="passkeyName" />
                @if ($passkeyError)
                    <p role="alert" class="settings-feedback is-error">{{ $passkeyError }}</p>
                @elseif ($passkeyStatus)
                    <p role="status" class="settings-feedback">{{ $passkeyStatus }}</p>
                @endif
            </x-admin.settings.row>
        </form>
    </x-admin.settings.group>

    <flux:modal wire:model.self="confirmingTwoFactorDisable" class="settings-dialog">
        <div class="settings-dialog-body">
            <div>
                <flux:heading level="2" size="lg">Turn off two-factor?</flux:heading>
                <flux:text class="mt-2">Your password alone will sign you in, and your recovery codes stop working.</flux:text>
            </div>
            <div class="settings-dialog-actions">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Keep it on</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="danger" wire:click="disableTwoFactor">Turn off</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="confirmingPasskeyRemoval" class="settings-dialog" data-passkey-remove-dialog>
        <div class="settings-dialog-body">
            <div>
                <flux:heading level="2" size="lg">Remove {{ $pendingPasskeyName ?? 'this passkey' }}?</flux:heading>
                <flux:text class="mt-2">You won't be able to sign in with it anymore. You can add it again later.</flux:text>
            </div>
            <div class="settings-dialog-actions">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Keep it</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="danger" wire:click="removePasskey">Remove passkey</flux:button>
            </div>
        </div>
    </flux:modal>

    <x-admin.settings.confirm-password />
</div>

<script>
    this.$js.addPasskey = async () => {
        if (! window.PublicKeyCredential?.parseCreationOptionsFromJSON || ! navigator.credentials?.create) {
            $wire.passkeyCeremonyFailed('unsupported');
            return;
        }

        const result = await $wire.beginPasskeyRegistration();
        if (! result?.options) return;

        try {
            const credential = await navigator.credentials.create({
                publicKey: PublicKeyCredential.parseCreationOptionsFromJSON(result.options),
            });
            await $wire.finishPasskeyRegistration(credential.toJSON());
        } catch (error) {
            $wire.passkeyCeremonyFailed(error?.name ?? 'Error');
        }
    };

    $wire.$on('settings-passkey-confirmed', () => $wire.$js.addPasskey());
</script>
