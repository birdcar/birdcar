const SAVED_DURATION = 2400;

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

export function hasUnsavedSettings(root = globalThis.document) {
    return root?.querySelector?.('[data-settings-save-bar][data-dirty="true"]') != null;
}

export function installSettings(Alpine = globalThis.Alpine, target = globalThis.window, root = globalThis.document) {
    Alpine?.data?.('settingsGroup', createSettingsGroup);
    target?.addEventListener?.('beforeunload', (event) => {
        if (!hasUnsavedSettings(root)) return;
        event.preventDefault();
        event.returnValue = '';
    });
    root?.addEventListener?.('livewire:navigate', (event) => {
        if (hasUnsavedSettings(root) && !target?.confirm?.('You have unsaved settings. Leave this page anyway?')) event.preventDefault();
    });
}
