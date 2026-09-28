<?php

use App\Models\User;
use App\Settings\Sections\PreferencesSection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Settings\SettingsSections;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.settings'), Title('Preferences settings')] class extends Component
{

    /**
     * IANA timezone identifier; an empty string uses Admin's default.
     */
    public string $timezone = '';

    public ?string $saveError = null;

    public function mount(): void
    {
        app(SettingsSections::class)->authorize(PreferencesSection::class);
        $this->fillFromSaved();
    }

    public function save(): void
    {
        app(SettingsSections::class)->authorize(PreferencesSection::class);
        $this->saveError = null;

        $validated = $this->validate(
            ['timezone' => ['nullable', 'string', Rule::in(DateTimeZone::listIdentifiers())]],
            ['timezone.in' => 'Choose a timezone from the list.'],
        );

        try {
            $this->user()->forceFill(['timezone' => $validated['timezone'] !== '' ? $validated['timezone'] : null])->save();
        } catch (Throwable $exception) {
            report($exception);
            $this->saveError = "Couldn't save. Your changes are still here.";

            return;
        }

        $this->fillFromSaved();
        $this->dispatch('settings-saved', group: 'time');
    }

    public function discard(): void
    {
        app(SettingsSections::class)->authorize(PreferencesSection::class);
        $this->saveError = null;
        $this->resetValidation();
        $this->fillFromSaved();
    }

    public function with(): array
    {
        return [
            'timezones' => DateTimeZone::listIdentifiers(),
            'defaultTimezone' => (string) config('app.timezone', 'UTC'),
            'errorCount' => $this->getErrorBag()->has('timezone') ? 1 : 0,
        ];
    }

    private function fillFromSaved(): void
    {
        $this->timezone = $this->user()->refresh()->timezone ?? '';
    }

    private function user(): User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : abort(403);
    }
};
?>

<div data-preferences-settings class="settings-section">
    <x-admin.settings.group name="appearance" heading="Appearance" description="Applies right away, on this device only.">
        <x-admin.settings.row label="Theme" help="System follows your device's light or dark setting.">
            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" aria-label="Theme on this device">
                <flux:radio value="light" icon="sun">Light</flux:radio>
                <flux:radio value="dark" icon="moon">Dark</flux:radio>
                <flux:radio value="system" icon="computer-desktop">System</flux:radio>
            </flux:radio.group>
        </x-admin.settings.row>
    </x-admin.settings.group>

    <x-admin.settings.group
        name="time"
        heading="Time"
        description="Your timezone, wherever you sign in."
        :fields="['timezone']"
        save="save"
        discard="discard"
        :error-count="$errorCount"
        :save-error="$saveError"
    >
        <x-admin.settings.row label="Timezone" help="The default when you schedule a release." for="preferences-timezone">
            <flux:select id="preferences-timezone" wire:model="timezone" variant="listbox" searchable placeholder="Choose a timezone" aria-describedby="preferences-timezone-help">
                <flux:select.option value="">Admin default ({{ $defaultTimezone }})</flux:select.option>
                @foreach ($timezones as $zone)
                    <flux:select.option :value="$zone">{{ str_replace('_', ' ', $zone) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="timezone" />
        </x-admin.settings.row>
    </x-admin.settings.group>
</div>
