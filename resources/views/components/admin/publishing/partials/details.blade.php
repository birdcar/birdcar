<form wire:submit="saveDetails" class="space-y-4" data-publishing-editor-action>
    <flux:heading level="2" size="lg">Article details</flux:heading>
    <flux:input wire:model="details.title" label="Title" maxlength="240" placeholder="Give the argument a name" />
    <flux:textarea wire:model="details.description" label="Description" maxlength="1000" rows="3" placeholder="What will the reader take away?" />
    <flux:input wire:model="details.date" label="Original publication date" type="date" />
    @can('update', $article)<flux:button type="submit" size="sm" data-publishing-editor-action>Save details</flux:button>@endcan
    <p class="text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">Changes create a new working revision. An approved or scheduled release must be prepared and approved again.</p>
</form>
