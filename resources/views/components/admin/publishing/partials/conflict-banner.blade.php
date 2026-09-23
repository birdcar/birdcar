@props(['message'])
<div class="space-y-3 rounded border border-amber-300/40 bg-amber-300/10 p-4 text-sm text-amber-100" role="alert" data-conflict-banner>
    <p><strong>Save conflict.</strong> {{ $message }} Your local text was not overwritten.</p>
    <div class="flex flex-wrap gap-2" aria-label="Conflict recovery choices">
        <button type="button" class="rounded border border-amber-200/40 px-3 py-1.5 text-xs" data-conflict-action="recover-local">Recover local draft</button>
        <button type="button" class="rounded border border-amber-200/40 px-3 py-1.5 text-xs" data-conflict-action="copy-local">Copy local text</button>
        <a class="rounded border border-amber-200/40 px-3 py-1.5 text-xs" href="#preview" data-conflict-action="compare-latest">Compare with latest saved preview</a>
        <button type="button" class="rounded border border-red-300/40 px-3 py-1.5 text-xs text-red-100" data-conflict-action="restart-from-server">Restart from server revision</button>
    </div>
</div>
