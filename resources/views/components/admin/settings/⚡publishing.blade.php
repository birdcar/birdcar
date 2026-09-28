<?php

use App\Ai\Agents\EditorialAgent;
use App\Models\Publishing\EditorialActivityKind;
use App\Settings\PublishingAgentSettings;
use App\Settings\Sections\PublishingSection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Settings\SettingsSections;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.settings'), Title('Publishing settings')] class extends Component
{

    public bool $paused = true;

    /**
     * Draft model choice per agent role; an empty string follows the role's recommendation.
     *
     * @var array<string, string>
     */
    public array $models = [];

    /**
     * Save failures by group; validation errors live in the error bag.
     *
     * @var array<string, string>
     */
    public array $saveErrors = [];

    public ?string $feedback = null;

    public function mount(): void
    {
        app(SettingsSections::class)->authorize(PublishingSection::class);

        $settings = app(PublishingAgentSettings::class);
        $this->fillPauseFromSaved($settings);
        $this->fillModelsFromSaved($settings);
    }

    public function saveRequests(): void
    {
        app(SettingsSections::class)->authorize(PublishingSection::class);
        unset($this->saveErrors['requests']);
        $this->feedback = null;

        $validated = $this->validate([
            'paused' => ['required', 'boolean'],
        ], [
            'paused.required' => 'Choose whether agent requests are paused.',
            'paused.boolean' => 'Choose whether agent requests are paused.',
        ]);

        try {
            $settings = app(PublishingAgentSettings::class)->refresh();
            $settings->paused = (bool) $validated['paused'];
            $settings->save();
        } catch (Throwable $exception) {
            report($exception);
            $this->saveErrors['requests'] = "Couldn't save. Your changes are still here.";

            return;
        }

        $this->fillPauseFromSaved($settings);
        $this->dispatch('settings-saved', group: 'requests');
    }

    public function saveModels(): void
    {
        app(SettingsSections::class)->authorize(PublishingSection::class);
        unset($this->saveErrors['models']);
        $this->feedback = null;

        $roles = array_map(fn (EditorialActivityKind $kind): string => $kind->value, EditorialActivityKind::cases());
        $validated = $this->validate([
            'models' => ['required', 'array:'.implode(',', $roles), 'required_array_keys:'.implode(',', $roles)],
            'models.*' => ['nullable', 'string', Rule::in(array_keys($this->modelOptions()))],
        ], [
            'models.array' => 'Choose models only for the listed agent tasks.',
            'models.required' => 'Choose a model setting for every agent task.',
            'models.required_array_keys' => 'Choose a model setting for every agent task.',
            'models.*.string' => 'Choose one of the listed models.',
            'models.*.in' => 'Choose one of the listed models.',
        ]);

        try {
            $settings = app(PublishingAgentSettings::class)->refresh();
            foreach (EditorialActivityKind::cases() as $kind) {
                $model = $validated['models'][$kind->value] ?? null;
                is_string($model) && $model !== ''
                    ? $settings->overrideModel($kind, $model)
                    : $settings->resetModel($kind);
            }
            $settings->save();
        } catch (Throwable $exception) {
            report($exception);
            $this->saveErrors['models'] = "Couldn't save. Your changes are still here.";

            return;
        }

        $this->fillModelsFromSaved($settings);
        $this->dispatch('settings-saved', group: 'models');
    }

    public function discardRequests(): void
    {
        app(SettingsSections::class)->authorize(PublishingSection::class);
        unset($this->saveErrors['requests']);
        $this->resetValidation('paused');
        $this->fillPauseFromSaved(app(PublishingAgentSettings::class)->refresh());
    }

    public function discardModels(): void
    {
        app(SettingsSections::class)->authorize(PublishingSection::class);
        unset($this->saveErrors['models']);
        $this->resetValidation(['models', ...array_map(fn (EditorialActivityKind $kind): string => 'models.'.$kind->value, EditorialActivityKind::cases())]);
        $this->fillModelsFromSaved(app(PublishingAgentSettings::class)->refresh());
    }

    public function resetRole(string $role): void
    {
        app(SettingsSections::class)->authorize(PublishingSection::class);
        $this->feedback = null;

        $kind = EditorialActivityKind::tryFrom($role);
        if (! $kind instanceof EditorialActivityKind) {
            throw ValidationException::withMessages(['models' => 'Choose models only for the listed agent tasks.']);
        }

        app(PublishingAgentSettings::class)->refresh()->resetModel($kind)->save();

        $this->models[$kind->value] = '';
        $this->resetValidation('models.'.$kind->value);
        $this->feedback = $this->roleLabel($kind).' now follows its recommended model.';
    }

    public function with(): array
    {
        $settings = app(PublishingAgentSettings::class);
        $options = $this->modelOptions();
        $roles = [];

        foreach (EditorialActivityKind::cases() as $kind) {
            $recommended = EditorialAgent::recommendedModelFor($kind);
            $saved = $settings->model_overrides[$kind->value] ?? null;
            $saved = is_string($saved) && $saved !== '' ? $saved : null;

            $roles[] = [
                'key' => $kind->value,
                'label' => $this->roleLabel($kind),
                'purpose' => $this->rolePurpose($kind),
                'recommended' => $options[$recommended] ?? $recommended,
                'saved' => $saved,
                'savedLabel' => $saved !== null ? ($options[$saved] ?? null) : null,
            ];
        }

        $modelErrors = array_filter(
            array_keys($this->getErrorBag()->messages()),
            fn (string $field): bool => $field === 'models' || str_starts_with($field, 'models.'),
        );

        return [
            'savedPaused' => $settings->paused,
            'credentialsConfigured' => (string) config('ai.providers.openrouter.key', '') !== '',
            'modelOptions' => $options,
            'roles' => $roles,
            'modelErrorCount' => count($modelErrors),
            'pauseErrorCount' => $this->getErrorBag()->has('paused') ? 1 : 0,
        ];
    }

    private function fillPauseFromSaved(PublishingAgentSettings $settings): void
    {
        $this->paused = $settings->paused;
    }

    private function fillModelsFromSaved(PublishingAgentSettings $settings): void
    {
        $this->models = [];

        foreach (EditorialActivityKind::cases() as $kind) {
            $override = $settings->modelOverrideFor($kind);
            $this->models[$kind->value] = $override !== null && EditorialAgent::allowsModel($override) ? $override : '';
        }
    }

    /**
     * Model identifiers contain dots, so the allowlist is read as a whole rather than by dotted config keys.
     *
     * @return array<string, string>
     */
    private function modelOptions(): array
    {
        $options = [];
        $models = config('publishing_agents.models', []);

        foreach (is_array($models) ? $models : [] as $id => $definition) {
            if (is_string($id) && is_array($definition)) {
                $options[$id] = is_string($definition['label'] ?? null) ? $definition['label'] : $id;
            }
        }

        return $options;
    }

    private function roleLabel(EditorialActivityKind $kind): string
    {
        return match ($kind) {
            EditorialActivityKind::Interview => 'Interview',
            EditorialActivityKind::ResearchChallenge => 'Research challenge',
            EditorialActivityKind::Plan => 'Plan',
            EditorialActivityKind::Draft => 'Draft',
            EditorialActivityKind::ReviewFacts => 'Fact review',
            EditorialActivityKind::ReviewVoice => 'Voice review',
            EditorialActivityKind::ReviewBuyer => 'Buyer review',
            EditorialActivityKind::Reconciliation => 'Reconciliation',
            EditorialActivityKind::Recheck => 'Recheck',
        };
    }

    private function rolePurpose(EditorialActivityKind $kind): string
    {
        return match ($kind) {
            EditorialActivityKind::Interview => 'Builds the brief, asks you for missing answers, and proposes angles.',
            EditorialActivityKind::ResearchChallenge => 'Tests claims against public evidence and records sources and gaps.',
            EditorialActivityKind::Plan => 'Turns the approved angle and evidence into an outline for your approval.',
            EditorialActivityKind::Draft => 'Writes the manuscript from your approved plan.',
            EditorialActivityKind::ReviewFacts => 'Finds factual risks, unsupported claims, and needed corrections.',
            EditorialActivityKind::ReviewVoice => 'Checks voice, clarity, positioning, and editorial fit.',
            EditorialActivityKind::ReviewBuyer => 'Checks audience value, objections, and buyer risks.',
            EditorialActivityKind::Reconciliation => 'Groups review findings and surfaces conflicts to resolve.',
            EditorialActivityKind::Recheck => 'Confirms whether revisions resolved the findings.',
        };
    }
};
?>

<div data-publishing-settings class="settings-section">
    <x-admin.settings.group
        name="requests"
        heading="Agent requests"
        description="Pausing stops new model requests. It doesn't touch publication or your approvals."
        :fields="['paused']"
        save="saveRequests"
        discard="discardRequests"
        :error-count="$pauseErrorCount"
        :save-error="$saveErrors['requests'] ?? null"
    >
        <x-admin.settings.row label="Pause agent requests" :help="'Saved: '.($savedPaused ? 'paused' : 'not paused').'.'" for="publishing-paused" data-saved-pause-state="{{ $savedPaused ? 'paused' : 'on' }}">
            <flux:switch id="publishing-paused" wire:model="paused" aria-describedby="publishing-paused-help" />
            <flux:error name="paused" />
            <details class="settings-disclosure">
                <summary><flux:icon.chevron-right variant="micro" />What pausing changes</summary>
                <ul class="settings-list">
                    <li>While paused, no new model requests start. A request already in progress may still finish.</li>
                    <li>Queued work waits in place, and author questions and answers are kept.</li>
                    <li>Scheduled publication and your approvals are separate controls and are not affected.</li>
                    <li>Turning requests back on changes no activity statuses and approves nothing. Queued work starts on the next recovery pass, within about five minutes while the scheduler runs; work you start afterward runs right away. Paused or failed activities still need your review.</li>
                </ul>
            </details>
        </x-admin.settings.row>

        <x-admin.settings.row
            label="OpenRouter API key"
            :help="$credentialsConfigured ? 'Set by the deployment.' : 'Set OPENROUTER_API_KEY in the deployment environment, then rebuild cached configuration and restart workers. Keys are never shown here.'"
            :data-credentials-status="$credentialsConfigured ? 'configured' : 'missing'"
        >
            @if ($credentialsConfigured)
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

    <x-admin.settings.group
        name="models"
        heading="Models by task"
        description="“Use recommended” follows the task’s recommendation, including future updates. Choosing a model pins it, even when it matches today’s recommendation. OpenRouter Auto Router lets OpenRouter choose a model for each request, so the model can vary between requests; work waiting on your answer continues on the model that asked."
        :fields="array_map(fn (array $role): string => 'models.'.$role['key'], $roles)"
        save="saveModels"
        discard="discardModels"
        :error-count="$modelErrorCount"
        :save-error="$saveErrors['models'] ?? null"
    >
        <flux:error name="models" />
        @if ($feedback)
            <p role="status" class="settings-feedback">{{ $feedback }}</p>
        @endif

        @foreach ($roles as $role)
            <x-admin.settings.row wire:key="settings-role-{{ $role['key'] }}" data-settings-role="{{ $role['key'] }}" :label="$role['label']" :help="$role['purpose']" :for="'publishing-model-'.$role['key']">
                <flux:select variant="listbox" :id="'publishing-model-'.$role['key']" wire:model="models.{{ $role['key'] }}" :placeholder="'Use recommended · '.$role['recommended']" :aria-describedby="'publishing-model-'.$role['key'].'-help publishing-model-'.$role['key'].'-state'">
                    <flux:select.option value="">Use recommended · {{ $role['recommended'] }}</flux:select.option>
                    @foreach ($modelOptions as $modelId => $modelLabel)
                        <flux:select.option :value="$modelId">{{ $modelLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="models.{{ $role['key'] }}" />

                <div id="publishing-model-{{ $role['key'] }}-state" class="settings-row-state">
                    @if ($role['saved'] !== null && $role['savedLabel'] === null)
                        <div class="settings-row-state-line is-warning" data-override-state="unsupported">
                            <flux:badge size="sm" color="amber">Reset required</flux:badge>
                            <span>The saved model <code>{{ $role['saved'] }}</code> is no longer supported, so this task pauses before its next request. Reset it or save to follow the recommendation.</span>
                        </div>
                    @elseif ($role['savedLabel'] !== null)
                        <div class="settings-row-state-line" data-override-state="pinned">
                            <flux:badge size="sm" color="teal">Pinned</flux:badge>
                            <span>Saved: {{ $role['savedLabel'] }}</span>
                        </div>
                    @else
                        <p class="settings-row-state-line" data-override-state="recommended">Saved: follows the recommendation</p>
                    @endif

                    @if ($role['saved'] !== null)
                        <flux:button type="button" size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="resetRole('{{ $role['key'] }}')" wire:loading.attr="disabled" wire:target="saveModels,resetRole">Reset {{ $role['label'] }} to recommended</flux:button>
                    @endif
                </div>
            </x-admin.settings.row>
        @endforeach
    </x-admin.settings.group>
</div>
