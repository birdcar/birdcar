import { expect, test } from 'bun:test';
import { createRecoveryCodes, createSettingsGroup, hasUnsavedSettings, installSettings } from './settings.js';

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
    expect(registered.recoveryCodes).toBe(createRecoveryCodes);
    expect(typeof events.beforeunload).toBe('function');
});

test('in-app navigation away from unsaved settings asks before leaving', () => {
    let dirty = true;
    let answer = false;
    const prompts = [];
    const events = {};
    const root = {
        querySelector: (selector) => (dirty && selector.includes('data-dirty="true"') ? {} : null),
        addEventListener: (event, handler) => { events[event] = handler; },
    };
    installSettings(undefined, { addEventListener: () => {}, confirm: (message) => { prompts.push(message); return answer; } }, root);
    const navigate = () => {
        const event = { prevented: false, preventDefault() { this.prevented = true; } };
        events['livewire:navigate'](event);
        return event.prevented;
    };

    expect(navigate()).toBe(true);
    answer = true;
    expect(navigate()).toBe(false);
    dirty = false;
    expect(navigate()).toBe(false);
    expect(prompts).toHaveLength(2);
});

test('copying recovery codes puts one code per line on the clipboard and confirms it', async () => {
    const written = [];
    const recovery = createRecoveryCodes(['AbCdE-12345', 'FgHiJ-67890'], { clipboard: { writeText: async (text) => { written.push(text); } } });

    await recovery.copy();

    expect(written).toEqual(['AbCdE-12345\nFgHiJ-67890']);
    expect(recovery.copied).toBe(true);
    recovery.destroy();
});

test('a failed copy does not claim the codes were copied', async () => {
    const recovery = createRecoveryCodes(['AbCdE-12345'], { clipboard: { writeText: async () => { throw new Error('denied'); } } });

    await expect(recovery.copy()).rejects.toThrow('denied');
    expect(recovery.copied).toBe(false);
});

test('downloading recovery codes saves a text file from an attached link and releases it afterwards', async () => {
    const blobs = [];
    const revoked = [];
    const events = [];
    const link = { click: () => events.push('click'), remove: () => events.push('remove') };
    const root = {
        createElement: (tag) => (tag === 'a' ? link : null),
        body: { append: (element) => events.push(element === link ? 'append' : 'append-other') },
    };
    const urls = {
        createObjectURL: (blob) => { blobs.push(blob); return 'blob:recovery'; },
        revokeObjectURL: (url) => revoked.push(url),
    };
    const recovery = createRecoveryCodes(['AbCdE-12345', 'FgHiJ-67890'], { root, urls });

    recovery.download();

    expect(events).toEqual(['append', 'click', 'remove']);
    expect(link.href).toBe('blob:recovery');
    expect(link.download).toBe('birdcar-admin-recovery-codes.txt');
    expect(await blobs[0].text()).toBe('AbCdE-12345\nFgHiJ-67890\n');
    expect(blobs[0].type).toStartWith('text/plain');
    expect(revoked).toEqual([]);
    await new Promise((resolve) => setTimeout(resolve));
    expect(revoked).toEqual(['blob:recovery']);
});
