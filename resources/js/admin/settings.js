const SAVED_DURATION = 2400;
const COPIED_DURATION = 2000;

export function createSettingsGroup(fields = [], name = '') {
    return {
        saved: false,
        savedTimer: null,

        get count() {
            return fields.filter((field) => this.$wire?.$dirty?.(field) === true).length;
        },

        get dirty() {
            return this.count > 0;
        },

        init() {
            this.$wire?.$on?.('settings-saved', (detail) => {
                if ((detail?.group ?? detail?.[0]?.group) !== name) return;
                this.saved = true;
                clearTimeout(this.savedTimer);
                this.savedTimer = setTimeout(() => { this.saved = false; }, SAVED_DURATION);
            });
        },

        destroy() {
            clearTimeout(this.savedTimer);
        },
    };
}

export function createRecoveryCodes(codes = [], { clipboard = globalThis.navigator?.clipboard, root = globalThis.document, urls = globalThis.URL } = {}) {
    const text = codes.join('\n');

    return {
        copied: false,
        copiedTimer: null,

        async copy() {
            await clipboard.writeText(text);
            this.copied = true;
            clearTimeout(this.copiedTimer);
            this.copiedTimer = setTimeout(() => { this.copied = false; }, COPIED_DURATION);
        },

        download() {
            const url = urls.createObjectURL(new Blob([`${text}\n`], { type: 'text/plain' }));
            const link = Object.assign(root.createElement('a'), { href: url, download: 'birdcar-admin-recovery-codes.txt' });
            root.body.append(link);
            link.click();
            link.remove();
            setTimeout(() => urls.revokeObjectURL(url));
        },

        destroy() {
            clearTimeout(this.copiedTimer);
        },
    };
}

export function hasUnsavedSettings(root = globalThis.document) {
    return root?.querySelector?.('[data-settings-save-bar][data-dirty="true"]') != null;
}

export function installSettings(Alpine = globalThis.Alpine, target = globalThis.window, root = globalThis.document) {
    Alpine?.data?.('settingsGroup', createSettingsGroup);
    Alpine?.data?.('recoveryCodes', createRecoveryCodes);
    target?.addEventListener?.('beforeunload', (event) => {
        if (!hasUnsavedSettings(root)) return;
        event.preventDefault();
        event.returnValue = '';
    });
    root?.addEventListener?.('livewire:navigate', (event) => {
        if (hasUnsavedSettings(root) && !target?.confirm?.('You have unsaved settings. Leave this page anyway?')) event.preventDefault();
    });
}
