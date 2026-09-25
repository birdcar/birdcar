<?php

use App\Ai\Agents\EditorialAgent;
use App\Authorization\Admin\Permission as AdminPermission;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Publishing\EditorialActivityKind;
use App\Settings\PublishingAgentSettings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Publishing settings')] class extends Component
{
    public bool $paused = true;

    /**
     * Draft model choice per agent role; an empty string follows the role's recommendation.
     *
     * @var array<string, string>
     */
    public array $models = [];

    public ?string $feedback = null;

    public ?string $saveError = null;

    public function mount(): void
    {
        $this->authorizeConfiguration();
        $this->fillFromSaved(app(PublishingAgentSettings::class));
    }

    public function save(): void
    {
        $this->authorizeConfiguration();
        $this->feedback = null;
        $this->saveError = null;

        $roles = array_map(fn (EditorialActivityKind $kind): string => $kind->value, EditorialActivityKind::cases());
        $validated = $this->validate([
            'paused' => ['required', 'boolean'],
            'models' => ['required', 'array:'.implode(',', $roles), 'required_array_keys:'.implode(',', $roles)],
            'models.*' => ['nullable', 'string', Rule::in(array_keys($this->modelOptions()))],
        ], [
            'models.array' => 'Choose models only for the listed agent tasks.',
            'models.required' => 'Choose a model setting for every agent task.',
            'models.required_array_keys' => 'Choose a model setting for every agent task.',
            'models.*.string' => 'Choose one of the listed models.',
            'models.*.in' => 'Choose one of the listed models.',
            'paused.required' => 'Choose whether agent requests are paused.',
            'paused.boolean' => 'Choose whether agent requests are paused.',
        ]);

        try {
            $settings = app(PublishingAgentSettings::class)->refresh();
            foreach (EditorialActivityKind::cases() as $kind) {
                $model = $validated['models'][$kind->value] ?? null;
                is_string($model) && $model !== ''
                    ? $settings->overrideModel($kind, $model)
                    : $settings->resetModel($kind);
            }
            $settings->paused = (bool) $validated['paused'];
            $settings->save();
        } catch (Throwable $exception) {
            report($exception);
            $this->saveError = 'Reload the page to see the saved settings, then try again.';

            return;
        }

        $this->fillFromSaved($settings);
        $this->feedback = $settings->paused
            ? 'Settings saved. Agent requests are paused.'
            : 'Settings saved. Agent requests are on.';
    }

    public function resetRole(string $role): void
    {
        $this->authorizeConfiguration();
        $this->feedback = null;
        $this->saveError = null;

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

        return [
            'savedPaused' => $settings->paused,
            'credentialsConfigured' => (string) config('ai.providers.openrouter.key', '') !== '',
            'modelOptions' => $options,
            'roles' => $roles,
        ];
    }

    private function authorizeConfiguration(): void
    {
        Gate::authorize(AdminPermission::View->value);
        Gate::authorize(PublishingPermission::ConfigureAgents->value);
    }

    private function fillFromSaved(PublishingAgentSettings $settings): void
    {
        $this->paused = $settings->paused;
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

<section data-publishing-settings class="space-y-8">
    <div class="max-w-3xl">
        <flux:heading level="1" size="xl">Publishing agent settings</flux:heading>
        <flux:text class="mt-2">Pause agent requests, or pin a different model for one task. Every task uses its recommended model unless you pin another. Saved changes apply to work that has not started yet, with no restart needed.</flux:text>
    </div>

    <form wire:submit="save" class="space-y-10">
        <section aria-labelledby="agent-requests-heading" class="max-w-3xl border-t border-zinc-200 pt-6 dark:border-white/10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading level="2" size="lg" id="agent-requests-heading">Agent requests</flux:heading>
                <flux:badge size="sm" :color="$savedPaused ? 'amber' : 'teal'" data-saved-pause-state>Saved: {{ $savedPaused ? 'Paused' : 'On' }}</flux:badge>
            </div>

            <flux:field variant="inline" class="mt-4">
                <flux:switch wire:model="paused" />
                <flux:label>Pause agent requests</flux:label>
                <flux:error name="paused" />
            </flux:field>
            <p wire:dirty wire:target="paused" class="mt-2 text-sm font-medium text-amber-800 dark:text-amber-200">Not saved yet. Agent requests stay {{ $savedPaused ? 'paused' : 'on' }} until you save.</p>

            <ul class="mt-4 list-disc space-y-1.5 ps-5 text-sm text-zinc-600 dark:text-zinc-300">
                <li>While paused, no new model requests start. A request already in progress may still finish.</li>
                <li>Queued work waits in place, and author questions and answers are kept.</li>
                <li>Scheduled publication and your approvals are separate controls and are not affected.</li>
                <li>Turning requests back on changes no activity statuses and approves nothing. Queued work starts on the next recovery pass, within about five minutes while the scheduler runs; work you start afterward runs right away. Paused or failed activities still need your review.</li>
            </ul>

            <div class="mt-5">
                @if ($credentialsConfigured)
                    <p class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300" data-credentials-status="configured">
                        <flux:icon.check-circle variant="mini" class="shrink-0 text-teal-700 dark:text-cyan-300" />
                        An OpenRouter API key is configured for this deployment.
                    </p>
                @else
                    <flux:callout variant="warning" icon="exclamation-triangle" data-credentials-status="missing">
                        <flux:callout.heading>No OpenRouter API key is configured</flux:callout.heading>
                        <flux:callout.text>Agent requests stop with a credentials error until the deployment sets <code>OPENROUTER_API_KEY</code> in its environment or secret manager. Keys are never entered or shown here. After setting it, rebuild cached configuration and restart workers.</flux:callout.text>
                    </flux:callout>
                @endif
            </div>
        </section>

        <section aria-labelledby="models-heading" class="border-t border-zinc-200 pt-6 dark:border-white/10">
            <div class="max-w-3xl">
                <flux:heading level="2" size="lg" id="models-heading">Models by task</flux:heading>
                <flux:text class="mt-1">“Use recommended” follows the task’s recommendation, including future updates. Choosing a model pins it, even when it matches today’s recommendation. OpenRouter Auto Router lets OpenRouter choose a model for each request, so the model can vary between requests; work waiting on your answer continues on the model that asked.</flux:text>
                <flux:error name="models" />
            </div>

            <ul class="mt-4" role="list">
                @foreach ($roles as $role)
                    <li wire:key="settings-role-{{ $role['key'] }}" data-settings-role="{{ $role['key'] }}" class="border-t border-zinc-200 py-5 first:border-t-0 dark:border-white/10">
                        <div class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,24rem)] lg:items-start lg:gap-8">
                            <div class="min-w-0 space-y-1.5">
                                <flux:heading level="3" size="sm" class="wrap-anywhere">{{ $role['label'] }}</flux:heading>
                                <flux:text class="wrap-anywhere">{{ $role['purpose'] }}</flux:text>
                                <p class="text-sm text-zinc-600 dark:text-zinc-300">Recommended: <span class="font-medium text-zinc-800 wrap-anywhere dark:text-zinc-100">{{ $role['recommended'] }}</span></p>
                                @if ($role['saved'] !== null && $role['savedLabel'] === null)
                                    <div class="flex flex-wrap items-center gap-2 text-sm text-amber-800 dark:text-amber-200" data-override-state="unsupported">
                                        <flux:badge size="sm" color="amber">Reset required</flux:badge>
                                        <span class="min-w-0 wrap-anywhere">The saved model <code>{{ $role['saved'] }}</code> is no longer supported, so this task pauses before its next request. Reset it or save to follow the recommendation.</span>
                                    </div>
                                @elseif ($role['savedLabel'] !== null)
                                    <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300" data-override-state="pinned">
                                        <flux:badge size="sm" color="teal">Pinned</flux:badge>
                                        <span class="min-w-0 wrap-anywhere">Saved: {{ $role['savedLabel'] }}</span>
                                    </div>
                                @else
                                    <p class="text-sm text-zinc-600 dark:text-zinc-300" data-override-state="recommended">Saved: follows the recommendation</p>
                                @endif
                            </div>

                            <div class="min-w-0 space-y-2">
                                <flux:field>
                                    <flux:label><span class="sr-only">{{ $role['label'] }}&nbsp;</span>Model</flux:label>
                                    <flux:select wire:model="models.{{ $role['key'] }}">
                                        <flux:select.option value="">Use recommended</flux:select.option>
                                        @foreach ($modelOptions as $modelId => $modelLabel)
                                            <flux:select.option :value="$modelId">{{ $modelLabel }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="models.{{ $role['key'] }}" />
                                </flux:field>
                                <p wire:dirty wire:target="models.{{ $role['key'] }}" class="text-sm text-amber-800 dark:text-amber-200">Not saved yet.</p>
                                @if ($role['saved'] !== null)
                                    <flux:button type="button" size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="resetRole('{{ $role['key'] }}')" wire:loading.attr="disabled" wire:target="save,resetRole">Reset {{ $role['label'] }} to recommended</flux:button>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        <div class="flex flex-col gap-3 border-t border-zinc-200 pt-6 sm:flex-row sm:flex-wrap sm:items-center dark:border-white/10">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save,resetRole" class="self-start">Save settings</flux:button>
            <span wire:loading wire:target="save" class="text-sm text-zinc-600 dark:text-zinc-300">Saving…</span>
            <span wire:dirty class="text-sm font-medium text-amber-800 dark:text-amber-200">You have unsaved changes.</span>
            @if ($feedback)
                <p role="status" class="text-sm text-teal-800 dark:text-cyan-200">{{ $feedback }}</p>
            @endif
            @if ($errors->any())
                <p role="alert" class="text-sm font-medium text-red-600 dark:text-red-400">Nothing was saved. Review the highlighted choices.</p>
            @endif
        </div>

        @if ($saveError)
            <flux:callout variant="danger" heading="Settings were not saved" :text="$saveError" role="alert" />
        @endif
    </form>
</section>
