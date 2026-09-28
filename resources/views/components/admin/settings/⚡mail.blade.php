<?php

use App\Settings\AdminMailSettings;
use App\Settings\MarketingMailSettings;
use App\Settings\Sections\MailSection;
use App\Settings\SurfaceMailSettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Settings\SettingsSections;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.settings'), Title('Mail settings')] class extends Component
{

    /**
     * Draft sender per surface. Values arrive untrimmed because Livewire skips the trimming middleware.
     *
     * @var array<string, array{from_name: string, from_address: string, reply_to: string}>
     */
    public array $senders = [];

    /**
     * Save failures by surface; validation errors live in the error bag.
     *
     * @var array<string, string>
     */
    public array $saveErrors = [];

    public function mount(): void
    {
        app(SettingsSections::class)->authorize(MailSection::class);

        foreach ($this->surfaces() as $surface => $definition) {
            $this->fillFromSaved($surface, app($definition['settings']));
        }
    }

    public function save(string $surface): void
    {
        app(SettingsSections::class)->authorize(MailSection::class);

        $definition = $this->definitionFor($surface);
        unset($this->saveErrors[$surface]);
        $input = $this->validateSurface($surface, $definition['settings']);

        try {
            $settings = app($definition['settings'])->refresh();
            $settings->updateSender($input['from_name'], $input['from_address'], $input['reply_to'] ?? null)->save();
        } catch (Throwable $exception) {
            report($exception);
            $this->saveErrors[$surface] = "Couldn't save. Your changes are still here.";

            return;
        }

        $this->fillFromSaved($surface, $settings);
        $this->dispatch('settings-saved', group: $surface);
    }

    public function discard(string $surface): void
    {
        app(SettingsSections::class)->authorize(MailSection::class);

        $definition = $this->definitionFor($surface);
        unset($this->saveErrors[$surface]);
        $this->resetValidation(array_map(fn (string $field): string => "senders.{$surface}.{$field}", ['from_name', 'from_address', 'reply_to']));
        $this->fillFromSaved($surface, app($definition['settings'])->refresh());
    }

    public function with(): array
    {
        $surfaces = [];

        foreach ($this->surfaces() as $surface => $definition) {
            $mailer = config($definition['mailerConfig']);
            $key = config("mail.mailers.{$definition['resendMailer']}.key");

            $surfaces[] = [
                ...$definition,
                'key' => $surface,
                'domains' => $this->domainList($definition['settings']),
                'mailer' => is_string($mailer) && trim($mailer) !== '' ? trim($mailer) : null,
                'keyConfigured' => is_string($key) && $key !== '',
                'errorCount' => count(Arr::where(
                    array_keys($this->getErrorBag()->messages()),
                    fn (string $field): bool => str_starts_with($field, "senders.{$surface}."),
                )),
            ];
        }

        return ['surfaces' => $surfaces];
    }

    /**
     * @return array{settings: class-string<SurfaceMailSettings>, label: string, purpose: string, mailerConfig: string, mailerVariable: string, resendMailer: string, keyVariable: string}
     */
    private function definitionFor(string $surface): array
    {
        return $this->surfaces()[$surface]
            ?? throw ValidationException::withMessages(['senders' => 'Choose Admin or Marketing mail to save.']);
    }

    /**
     * Validates only the saved surface and keeps the other surface's errors on screen.
     *
     * @param  class-string<SurfaceMailSettings>  $settingsClass
     * @return array{from_name: string, from_address: string, reply_to?: ?string}
     */
    private function validateSurface(string $surface, string $settingsClass): array
    {
        $prefix = "senders.{$surface}";
        $otherErrors = Arr::where(
            $this->getErrorBag()->messages(),
            fn (array $messages, string $key): bool => ! str_starts_with($key, "{$prefix}."),
        );
        $domains = $this->domainList($settingsClass);
        $invalidReplyTo = 'Enter a valid reply-to address, or leave it blank.';

        try {
            $validated = $this->validate([
                "{$prefix}.from_name" => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
                "{$prefix}.from_address" => [
                    'bail',
                    'required',
                    'string',
                    'email:filter',
                    function (string $attribute, mixed $value, Closure $fail) use ($settingsClass, $domains): void {
                        if (! is_string($value) || ! $settingsClass::allowsSenderAddress($value)) {
                            $fail($domains === null ? 'No sender domains are configured for this mail, so no address can be saved.' : "Use an address at {$domains}.");
                        }
                    },
                ],
                "{$prefix}.reply_to" => ['nullable', 'string', 'email:filter', 'max:254'],
            ], [
                "{$prefix}.from_name.required" => 'Enter the name recipients see.',
                "{$prefix}.from_name.string" => 'Enter the name recipients see.',
                "{$prefix}.from_name.max" => 'Keep the sender name to 100 characters or fewer.',
                "{$prefix}.from_name.not_regex" => 'Keep the sender name on one line.',
                "{$prefix}.from_address.required" => 'Enter the address mail is sent from.',
                "{$prefix}.from_address.string" => 'Enter a valid email address.',
                "{$prefix}.from_address.email" => 'Enter a valid email address.',
                "{$prefix}.reply_to.string" => $invalidReplyTo,
                "{$prefix}.reply_to.email" => $invalidReplyTo,
                "{$prefix}.reply_to.max" => $invalidReplyTo,
            ]);
        } catch (ValidationException $exception) {
            $exception->validator->errors()->merge($otherErrors);

            throw $exception;
        }

        foreach ($otherErrors as $key => $messages) {
            foreach ($messages as $message) {
                $this->addError($key, $message);
            }
        }

        return $validated['senders'][$surface];
    }

    /**
     * Fills from the raw stored values rather than sender(), which throws on a stored payload the page must still let the owner correct.
     */
    private function fillFromSaved(string $surface, SurfaceMailSettings $settings): void
    {
        $this->senders[$surface] = [
            'from_name' => $settings->from_name,
            'from_address' => $settings->from_address,
            'reply_to' => $settings->reply_to ?? '',
        ];
    }

    /**
     * @param  class-string<SurfaceMailSettings>  $settingsClass
     */
    private function domainList(string $settingsClass): ?string
    {
        $domains = $settingsClass::allowedSenderDomains();

        return $domains === [] ? null : Arr::join($domains, ', ', ' or ');
    }

    /**
     * @return array<string, array{settings: class-string<SurfaceMailSettings>, label: string, purpose: string, mailerConfig: string, mailerVariable: string, resendMailer: string, keyVariable: string}>
     */
    private function surfaces(): array
    {
        return [
            'admin' => [
                'settings' => AdminMailSettings::class,
                'label' => 'Admin',
                'purpose' => 'Invitations and password resets for Admin accounts.',
                'mailerConfig' => 'admin.mail.mailer',
                'mailerVariable' => 'BIRDCAR_ADMIN_MAIL_MAILER',
                'resendMailer' => 'resend_admin',
                'keyVariable' => 'BIRDCAR_ADMIN_RESEND_API_KEY',
            ],
            'marketing' => [
                'settings' => MarketingMailSettings::class,
                'label' => 'Marketing',
                'purpose' => 'Mail sent on behalf of the public site.',
                'mailerConfig' => 'marketing.mail.mailer',
                'mailerVariable' => 'BIRDCAR_MARKETING_MAIL_MAILER',
                'resendMailer' => 'resend_marketing',
                'keyVariable' => 'BIRDCAR_MARKETING_RESEND_API_KEY',
            ],
        ];
    }
};
?>

<div data-mail-settings class="settings-section">
    <flux:error name="senders" />

    @foreach ($surfaces as $surface)
        @php $field = fn (string $name): string => "mail-{$surface['key']}-{$name}"; @endphp
        <x-admin.settings.group
            wire:key="mail-surface-{{ $surface['key'] }}"
            :name="$surface['key']"
            :heading="$surface['label'].' mail'"
            :description="$surface['purpose']"
            :fields="['senders.'.$surface['key'].'.from_name', 'senders.'.$surface['key'].'.from_address', 'senders.'.$surface['key'].'.reply_to']"
            :save="'save(\''.$surface['key'].'\')'"
            :discard="'discard(\''.$surface['key'].'\')'"
            :error-count="$surface['errorCount']"
            :save-error="$saveErrors[$surface['key']] ?? null"
            data-mail-surface="{{ $surface['key'] }}"
        >
            <x-admin.settings.row label="From name" help="Shown as the sender." :for="$field('from-name')">
                <flux:input :id="$field('from-name')" wire:model="senders.{{ $surface['key'] }}.from_name" maxlength="100" :aria-describedby="$field('from-name').'-help'" />
                <flux:error name="senders.{{ $surface['key'] }}.from_name" />
            </x-admin.settings.row>

            <x-admin.settings.row
                label="From address"
                :help="$surface['domains'] !== null ? 'Must be at '.$surface['domains'].'.' : 'No sender domains are configured for '.Str::lower($surface['label']).' mail, so no address can be saved.'"
                :for="$field('from-address')"
            >
                <flux:input type="email" :id="$field('from-address')" wire:model="senders.{{ $surface['key'] }}.from_address" :aria-describedby="$field('from-address').'-help'" />
                <flux:error name="senders.{{ $surface['key'] }}.from_address" />
            </x-admin.settings.row>

            <x-admin.settings.row label="Reply-to" badge="Optional" help="Leave blank to use the From address." :for="$field('reply-to')">
                <flux:input type="email" :id="$field('reply-to')" wire:model="senders.{{ $surface['key'] }}.reply_to" placeholder="Uses the From address" :aria-describedby="$field('reply-to').'-help'" />
                <flux:error name="senders.{{ $surface['key'] }}.reply_to" />
            </x-admin.settings.row>

            <x-admin.settings.row label="Mailer" help="Set by the deployment." data-mailer>
                @if ($surface['mailer'] !== null)
                    <code class="settings-environment-value">{{ $surface['mailer'] }} · {{ $surface['mailerVariable'] }}</code>
                @else
                    <span class="settings-missing">Not chosen. Set <code>{{ $surface['mailerVariable'] }}</code>.</span>
                @endif
                <x-slot:trailing>
                    <flux:badge size="sm">Environment</flux:badge>
                </x-slot:trailing>
            </x-admin.settings.row>

            <x-admin.settings.row
                label="Resend API key"
                :help="$surface['keyConfigured'] ? 'Set by the deployment.' : 'Set '.$surface['keyVariable'].' in the deployment environment. Keys are never shown here.'"
                :data-key-status="$surface['keyConfigured'] ? 'configured' : 'missing'"
            >
                @if ($surface['keyConfigured'])
                    <span class="settings-credential">
                        <flux:icon.check-circle variant="mini" />
                        Configured
                    </span>
                @else
                    <span class="settings-credential is-missing">
                        <flux:icon.exclamation-triangle variant="mini" />
                        Not configured
                    </span>
                @endif
                <x-slot:trailing>
                    <flux:badge size="sm">Environment</flux:badge>
                </x-slot:trailing>
            </x-admin.settings.row>
        </x-admin.settings.group>
    @endforeach
</div>
