<?php

use App\Authorization\Admin\Permission as AdminPermission;
use App\Authorization\Mail\Permission as MailPermission;
use App\Settings\AdminMailSettings;
use App\Settings\MarketingMailSettings;
use App\Settings\SurfaceMailSettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Mail settings')] class extends Component
{
    /**
     * Draft sender per surface. Values arrive untrimmed because Livewire skips the trimming middleware.
     *
     * @var array<string, array{from_name: string, from_address: string, reply_to: string}>
     */
    public array $senders = [];

    public ?string $feedback = null;

    public ?string $saveError = null;

    /**
     * The surface the current feedback or save error belongs to.
     */
    public ?string $feedbackSurface = null;

    public function mount(): void
    {
        $this->authorizeConfiguration();

        foreach ($this->surfaces() as $surface => $definition) {
            $this->fillFromSaved($surface, app($definition['settings']));
        }
    }

    public function save(string $surface): void
    {
        $this->authorizeConfiguration();
        $this->feedback = null;
        $this->saveError = null;
        $this->feedbackSurface = null;

        $definition = $this->surfaces()[$surface] ?? null;
        if ($definition === null) {
            throw ValidationException::withMessages(['senders' => 'Choose Admin or Marketing mail to save.']);
        }

        $this->feedbackSurface = $surface;
        $input = $this->validateSurface($surface, $definition['settings']);

        try {
            $settings = app($definition['settings'])->refresh();
            $settings->updateSender($input['from_name'], $input['from_address'], $input['reply_to'] ?? null)->save();
        } catch (Throwable $exception) {
            report($exception);
            $this->saveError = 'Reload the page to see the saved settings, then try again.';

            return;
        }

        $this->fillFromSaved($surface, $settings);
        $this->feedback = $definition['label'].' sender saved. The next '.Str::lower($definition['label']).' email uses it.';
    }

    public function with(): array
    {
        $surfaces = [];

        foreach ($this->surfaces() as $surface => $definition) {
            $settings = app($definition['settings']);
            $mailer = config($definition['mailerConfig']);
            $key = config("mail.mailers.{$definition['resendMailer']}.key");

            $surfaces[] = [
                ...$definition,
                'key' => $surface,
                'domains' => $this->domainList($definition['settings']),
                'mailer' => is_string($mailer) && trim($mailer) !== '' ? trim($mailer) : null,
                'keyConfigured' => is_string($key) && $key !== '',
                'savedName' => $settings->from_name,
                'savedAddress' => $settings->from_address,
                'savedReplyTo' => $settings->reply_to,
            ];
        }

        return ['surfaces' => $surfaces];
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

    private function authorizeConfiguration(): void
    {
        Gate::authorize(AdminPermission::View->value);
        Gate::authorize(MailPermission::ConfigureSenders->value);
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

<section data-mail-settings class="space-y-8">
    <div class="max-w-3xl">
        <flux:heading level="1" size="xl">Mail settings</flux:heading>
        <flux:text class="mt-2">Choose who each kind of mail comes from. A saved change applies to the next email sent, with no restart needed. Mailers and API keys belong to the deployment environment and can't be changed here.</flux:text>
        <flux:error name="senders" class="mt-3" />
    </div>

    @foreach ($surfaces as $surface)
        <form wire:key="mail-surface-{{ $surface['key'] }}" wire:submit="save('{{ $surface['key'] }}')" data-mail-surface="{{ $surface['key'] }}" aria-labelledby="mail-{{ $surface['key'] }}-heading" class="max-w-3xl border-t border-zinc-200 pt-6 dark:border-white/10">
            <flux:heading level="2" size="lg" id="mail-{{ $surface['key'] }}-heading">{{ $surface['label'] }} mail</flux:heading>
            <flux:text class="mt-1">{{ $surface['purpose'] }}</flux:text>

            <p class="mt-3 text-sm text-zinc-600 wrap-anywhere dark:text-zinc-300" data-saved-sender>
                Saved: <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $surface['savedName'] }}</span>, from <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $surface['savedAddress'] }}</span>.
                @if ($surface['savedReplyTo'])
                    Replies go to <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $surface['savedReplyTo'] }}</span>.
                @else
                    Replies go to the From address.
                @endif
            </p>

            <div class="mt-5 space-y-5">
                <flux:field>
                    <flux:label>From name</flux:label>
                    <flux:input wire:model="senders.{{ $surface['key'] }}.from_name" maxlength="100" />
                    <flux:error name="senders.{{ $surface['key'] }}.from_name" />
                </flux:field>

                <flux:field>
                    <flux:label>From address</flux:label>
                    <flux:description>
                        @if ($surface['domains'] !== null)
                            {{ $surface['label'] }} mail is sent from this address. It must be at {{ $surface['domains'] }}.
                        @else
                            No sender domains are configured for {{ Str::lower($surface['label']) }} mail, so no address can be saved.
                        @endif
                    </flux:description>
                    <flux:input type="email" wire:model="senders.{{ $surface['key'] }}.from_address" />
                    <flux:error name="senders.{{ $surface['key'] }}.from_address" />
                </flux:field>

                <flux:field>
                    <flux:label badge="Optional">Reply-to</flux:label>
                    <flux:description>Replies go here instead of the From address. Leave blank to use the From address.</flux:description>
                    <flux:input type="email" wire:model="senders.{{ $surface['key'] }}.reply_to" />
                    <flux:error name="senders.{{ $surface['key'] }}.reply_to" />
                </flux:field>
            </div>

            <div class="mt-5 space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                <p class="wrap-anywhere" data-mailer>
                    @if ($surface['mailer'] !== null)
                        Sent through the <code>{{ $surface['mailer'] }}</code> mailer, chosen by <code>{{ $surface['mailerVariable'] }}</code>.
                    @else
                        No mailer is chosen. Set <code>{{ $surface['mailerVariable'] }}</code> in the deployment environment.
                    @endif
                </p>

                @if ($surface['keyConfigured'])
                    <p class="flex items-center gap-2" data-key-status="configured">
                        <flux:icon.check-circle variant="mini" class="shrink-0 text-teal-700 dark:text-cyan-300" />
                        A Resend API key is configured for the <code>{{ $surface['resendMailer'] }}</code> mailer.
                    </p>
                @else
                    <flux:callout variant="warning" icon="exclamation-triangle" data-key-status="missing">
                        <flux:callout.heading>No Resend API key is configured for {{ Str::lower($surface['label']) }} mail</flux:callout.heading>
                        <flux:callout.text>The <code>{{ $surface['resendMailer'] }}</code> mailer can't send until the deployment sets <code>{{ $surface['keyVariable'] }}</code> in its environment or secret manager. Keys are never entered or shown here.</flux:callout.text>
                    </flux:callout>
                @endif
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save" class="self-start">Save {{ Str::lower($surface['label']) }} sender</flux:button>
                <span wire:loading wire:target="save('{{ $surface['key'] }}')" class="text-sm text-zinc-600 dark:text-zinc-300">Saving…</span>
                <span wire:dirty wire:target="senders.{{ $surface['key'] }}" class="text-sm font-medium text-amber-800 dark:text-amber-200">Not saved yet.</span>
                @if ($feedback && $feedbackSurface === $surface['key'])
                    <p role="status" class="text-sm text-teal-800 dark:text-cyan-200">{{ $feedback }}</p>
                @endif
                @if ($errors->has('senders.'.$surface['key'].'.*'))
                    <p role="alert" class="text-sm font-medium text-red-600 dark:text-red-400">Nothing was saved. Review the highlighted fields.</p>
                @endif
            </div>

            @if ($saveError && $feedbackSurface === $surface['key'])
                <flux:callout variant="danger" heading="{{ $surface['label'] }} sender was not saved" :text="$saveError" role="alert" class="mt-4" />
            @endif
        </form>
    @endforeach
</section>
