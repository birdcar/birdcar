import { expect, test } from 'bun:test';
import { createSettingsGroup, hasUnsavedSettings, installSettings } from './settings.js';

function groupWith(dirtyFields, fields = ['senders.admin.from_name', 'senders.admin.reply_to'], name = 'admin') {
    const listeners = {};
    const group = createSettingsGroup(fields, name);
    group.$wire = {
        $dirty: (field) => dirtyFields.includes(field),
        $on: (event, callback) => { listeners[event] = callback; },
    };
    group.init();
    return { group, listeners };
}

test('a settings group counts only its own dirty fields', () => {
    const { group } = groupWith(['senders.admin.reply_to', 'senders.marketing.from_name']);

    expect(group.count).toBe(1);
    expect(group.dirty).toBe(true);
});

test('a clean group is not dirty', () => {
    const { group } = groupWith([]);

    expect(group.count).toBe(0);
    expect(group.dirty).toBe(false);
});

test('only the saved group shows its saved confirmation', () => {
    const admin = groupWith([], undefined, 'admin');
    const marketing = groupWith([], undefined, 'marketing');

    admin.listeners['settings-saved']({ group: 'admin' });
    marketing.listeners['settings-saved']({ group: 'admin' });

    expect(admin.group.saved).toBe(true);
    expect(marketing.group.saved).toBe(false);
    admin.group.destroy();
});

test('leaving is guarded only while a save bar reports unsaved changes', () => {
    const dirtyRoot = { querySelector: (selector) => (selector.includes('data-dirty="true"') ? {} : null) };
    const cleanRoot = { querySelector: () => null };

    expect(hasUnsavedSettings(dirtyRoot)).toBe(true);
    expect(hasUnsavedSettings(cleanRoot)).toBe(false);
});

test('installing registers the group component and a leave guard', () => {
    const registered = {};
    const events = {};
    installSettings(
        { data: (name, factory) => { registered[name] = factory; } },
        { addEventListener: (event, handler) => { events[event] = handler; } },
    );

    expect(registered.settingsGroup).toBe(createSettingsGroup);
    expect(typeof events.beforeunload).toBe('function');
});
