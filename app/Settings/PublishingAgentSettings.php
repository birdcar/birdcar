<?php

namespace App\Settings;

use App\Ai\Agents\EditorialAgent;
use App\Models\Publishing\EditorialActivityKind;
use InvalidArgumentException;
use Spatie\LaravelSettings\Settings;

class PublishingAgentSettings extends Settings
{
    public bool $paused;

    /**
     * Optional per-role overrides keyed by editorial activity kind. Stored values are validated again before execution.
     *
     * @var array<string, string>
     */
    public array $model_overrides;

    public static function group(): string
    {
        return 'publishing_agents';
    }

    public function modelOverrideFor(EditorialActivityKind $kind): ?string
    {
        $override = $this->model_overrides[$kind->value] ?? null;

        return is_string($override) && $override !== '' ? $override : null;
    }

    public function overrideModel(EditorialActivityKind $kind, string $model): self
    {
        if (! EditorialAgent::allowsModel($model)) {
            throw new InvalidArgumentException('The selected publishing agent model is not supported.');
        }

        $overrides = $this->validOverrides();
        $overrides[$kind->value] = $model;
        $this->model_overrides = $overrides;

        return $this;
    }

    public function resetModel(EditorialActivityKind $kind): self
    {
        $overrides = $this->validOverrides();
        unset($overrides[$kind->value]);
        $this->model_overrides = $overrides;

        return $this;
    }

    /** @return array<string, string> */
    private function validOverrides(): array
    {
        $overrides = [];
        foreach ($this->model_overrides as $role => $model) {
            if (EditorialActivityKind::tryFrom($role) instanceof EditorialActivityKind && self::isSupportedModel($model)) {
                $overrides[$role] = $model;
            }
        }

        return $overrides;
    }

    /** Stored payloads are not type-checked on load, so a malformed value must be rejected rather than trusted. */
    private static function isSupportedModel(mixed $model): bool
    {
        return is_string($model) && EditorialAgent::allowsModel($model);
    }
}
