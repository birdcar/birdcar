@php
    use App\Settings\SettingsSections;

    $sections = app(SettingsSections::class);
    $settingsUser = auth()->user();
    $currentSection = $settingsUser ? $sections->current(request()->route()?->getName(), $settingsUser) : null;
    $currentSectionRoute = $currentSection ? $sections->routeName($currentSection) : null;
    $isSettingsIndex = request()->routeIs(SettingsSections::IndexRoute);
@endphp
@component('layouts.admin', ['title' => $title ?? ($currentSection ? $currentSection->label().' settings' : 'Settings'), 'flush' => true])
    <div @class(['settings-shell', 'is-index' => $isSettingsIndex]) data-settings-shell>
        <nav class="settings-rail" aria-label="Settings sections">
            @foreach ($settingsUser ? $sections->groupedFor($settingsUser) : [] as $entry)
                <div class="settings-rail-group">
                    <h2 class="settings-rail-heading">{{ $entry['group']->label() }}</h2>
                    <flux:navlist>
                        @foreach ($entry['sections'] as $section)
                            @php $sectionRoute = $sections->routeName($section); @endphp
                            <flux:navlist.item :href="route($sectionRoute)" :icon="$section->icon()" icon:trailing="chevron-right" :current="$sectionRoute === $currentSectionRoute" :aria-current="$sectionRoute === $currentSectionRoute ? 'page' : null" data-settings-section="{{ $sections->path($section) }}">{{ $section->label() }}</flux:navlist.item>
                        @endforeach
                    </flux:navlist>
                </div>
            @endforeach
        </nav>

        <div class="settings-content">
            <a href="{{ route(SettingsSections::IndexRoute) }}" class="settings-back">
                <flux:icon.chevron-left variant="micro" />
                Settings
            </a>

            @if ($currentSection)
                <header class="settings-section-header">
                    <h1>{{ $currentSection->label() }}</h1>
                    <p>{{ $currentSection->description() }}</p>
                </header>
            @endif

            {{ $slot }}
        </div>

        <div id="settings-save-bars" class="settings-save-bars"></div>
    </div>
@endcomponent
