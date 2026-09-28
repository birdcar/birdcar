<?php

use App\Authorization\Admin\Permission as AdminPermission;
use App\Settings\SettingsSections;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.settings'), Title('Settings')] class extends Component
{
    public function mount(): void
    {
        Gate::authorize(AdminPermission::View->value);
    }

    public function with(): array
    {
        $user = auth()->user();

        return [
            'firstSection' => $user ? app(SettingsSections::class)->first($user)?->component() : null,
        ];
    }
};
?>

<div data-settings-index>
    @if ($firstSection)
        <livewire:is :component="$firstSection" :wire:key="'settings-first-'.$firstSection" />
    @endif
</div>
