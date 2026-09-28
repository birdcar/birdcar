<?php

namespace App\Settings;

use App\Authorization\Admin\Permission as AdminPermission;
use App\Settings\Contracts\SettingsSection;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * The registered Admin Settings sections, in rail order.
 */
final class SettingsSections
{
    public const string IndexRoute = 'admin.settings.index';

    /**
     * @return list<SettingsSection>
     */
    public function all(): array
    {
        $configured = config('admin.settings.sections', []);
        $sections = [];
        $keys = [];

        foreach (is_array($configured) ? $configured : [] as $class) {
            $section = is_string($class) ? app($class) : null;

            if (! $section instanceof SettingsSection) {
                throw new InvalidArgumentException('Configuration [admin.settings.sections] must list classes implementing '.SettingsSection::class.'.');
            }

            $path = $this->path($section);
            if (isset($keys[$path])) {
                throw new InvalidArgumentException("Settings section path [{$path}] is registered twice.");
            }

            $keys[$path] = true;
            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * @return list<SettingsSection>
     */
    public function visibleTo(Authorizable $user): array
    {
        return array_values(array_filter($this->all(), fn (SettingsSection $section): bool => $this->allows($user, $section)));
    }

    /**
     * Visible sections grouped for the rail, Application before Your account, empty groups omitted.
     *
     * @return list<array{group: SettingsGroup, sections: non-empty-list<SettingsSection>}>
     */
    public function groupedFor(Authorizable $user): array
    {
        $visible = $this->visibleTo($user);
        $groups = [];

        foreach (SettingsGroup::cases() as $group) {
            $sections = array_values(array_filter($visible, fn (SettingsSection $section): bool => $section->group() === $group));

            if ($sections !== []) {
                $groups[] = ['group' => $group, 'sections' => $sections];
            }
        }

        return $groups;
    }

    public function allows(Authorizable $user, SettingsSection $section): bool
    {
        $permission = $section->permission();

        return $permission === null || $user->can((string) $permission->value);
    }

    /**
     * Authorizes the current user for a section, so the rail, route middleware and every Livewire mutation check the same capability.
     *
     * @param  class-string<SettingsSection>  $section
     */
    public function authorize(string $section): void
    {
        Gate::authorize(AdminPermission::View->value);

        $permission = app($section)->permission();

        if ($permission !== null) {
            Gate::authorize((string) $permission->value);
        }
    }

    public function first(Authorizable $user): ?SettingsSection
    {
        return $this->groupedFor($user)[0]['sections'][0] ?? null;
    }

    /**
     * The section a request is showing: the named section's page, or the first visible section on the landing page.
     */
    public function current(?string $routeName, Authorizable $user): ?SettingsSection
    {
        if ($routeName === self::IndexRoute) {
            return $this->first($user);
        }

        foreach ($this->all() as $section) {
            if ($this->routeName($section) === $routeName) {
                return $section;
            }
        }

        return null;
    }

    public function path(SettingsSection $section): string
    {
        return match ($section->group()) {
            SettingsGroup::Application => $section->key(),
            SettingsGroup::Account => 'account/'.$section->key(),
        };
    }

    public function routeName(SettingsSection $section): string
    {
        return 'admin.settings.'.str_replace('/', '.', $this->path($section));
    }
}
