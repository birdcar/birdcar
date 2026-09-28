<flux:modal wire:model.self="confirmingPassword" wire:close="cancelPasswordConfirmation" class="settings-dialog" data-settings-confirm-password>
    <form wire:submit="confirmPassword" class="settings-dialog-body">
        <div>
            <flux:heading level="2" size="lg">Confirm it's you</flux:heading>
            <flux:text class="mt-2">Enter your password to continue. You won't be asked again for a few hours.</flux:text>
        </div>

        <flux:field>
            <flux:label>Password</flux:label>
            <flux:input type="password" wire:model="confirmablePassword" autocomplete="current-password" viewable />
            <flux:error name="confirmablePassword" />
        </flux:field>

        <div class="settings-dialog-actions">
            <flux:modal.close>
                <flux:button type="button" variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">Confirm</flux:button>
        </div>
    </form>
</flux:modal>
