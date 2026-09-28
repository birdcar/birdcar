<?php

namespace App\Settings\Contracts;

use App\Settings\SettingsGroup;
use BackedEnum;

/**
 * One page of Admin Settings. List implementations in config/admin.php under settings.sections;
 * the rail, routes and landing page are all derived from that list.
 */
interface SettingsSection
{
    /**
     * URL segment and route-name suffix, unique within its group.
     */
    public function key(): string;

    public function group(): SettingsGroup;

    public function label(): string;

    /**
     * One line naming what the section controls and when changes apply.
     */
    public function description(): string;

    /**
     * Heroicon name shown beside the label in the rail.
     */
    public function icon(): string;

    /**
     * Domain permission required beyond Admin access, or null for sections every Admin user manages for themselves.
     */
    public function permission(): ?BackedEnum;

    /**
     * Livewire component name that renders the section.
     */
    public function component(): string;
}
