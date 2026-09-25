@php
    use App\Authorization\Publishing\Permission as PublishingPermission;
@endphp
@if (request()->routeIs('admin.publishing.*'))
    @can(PublishingPermission::View->value)
        <flux:navbar class="min-w-0 gap-1 sm:ml-4 sm:gap-3" aria-label="Publishing navigation">
            <flux:navbar.item :href="route('admin.publishing.dashboard')" :current="request()->routeIs('admin.publishing.dashboard', 'admin.publishing.articles.*')" :aria-current="request()->routeIs('admin.publishing.dashboard', 'admin.publishing.articles.*') ? 'page' : null">Workspace</flux:navbar.item>
            <flux:navbar.item :href="route('admin.publishing.published')" :current="request()->routeIs('admin.publishing.published')" :aria-current="request()->routeIs('admin.publishing.published') ? 'page' : null">Published</flux:navbar.item>
            @can(PublishingPermission::ConfigureAgents->value)
                <flux:navbar.item :href="route('admin.publishing.settings')" :current="request()->routeIs('admin.publishing.settings')" :aria-current="request()->routeIs('admin.publishing.settings') ? 'page' : null">Settings</flux:navbar.item>
            @endcan
        </flux:navbar>
    @endcan
@endif
