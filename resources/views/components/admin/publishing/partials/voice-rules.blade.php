@if ($voiceRuleFindings->isNotEmpty())
    <section class="mb-8" data-voice-rules aria-labelledby="voice-rules-heading">
        <flux:heading level="3" id="voice-rules-heading">Voice rules</flux:heading>
        <p class="mt-2 mb-5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">Mechanical rules from your voice canon. Each one holds {{ $article->isKit() ? 'approval' : 'the release' }} until the passage is fixed or you override the rule with a reason.</p>
        <div class="space-y-6">
            @foreach ($voiceRuleFindings as $finding)
                <article wire:key="voice-rule-{{ $finding->id }}" class="publishing-finding" data-voice-rule-finding="{{ $finding->id }}">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <flux:badge size="sm" :color="$finding->disposition === 'false_positive' ? 'zinc' : 'amber'">{{ $finding->disposition === 'false_positive' ? 'Overridden' : 'Resolve before release' }}</flux:badge>
                        <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ str($finding->kind)->after('lint:')->replace('-', ' ')->ucfirst() }}</span>
                    </div>
                    <p class="text-sm font-medium leading-relaxed">{{ $finding->statement }}</p>
                    @if ($finding->block_id)<p class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">Passage <code>{{ $finding->block_id }}</code></p>@endif
                    @if ($finding->proposed_patch)
                        <details class="publishing-disclosure mt-4"><summary>Suggested fix</summary><p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed">{{ $proposalTexts[$finding->id] ?? '' }}</p></details>
                    @endif
                    @if ($finding->disposition === 'false_positive')
                        <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">Your reason: {{ $finding->disposition_reason }}</p>
                    @elseif ($finding->disposition === null)
                        <div class="mt-4 space-y-3">
                            @if ($finding->proposed_patch)
                                @can('update', $article)<flux:button size="sm" wire:click="applyProposal({{ $finding->id }})" data-publishing-editor-action>Accept fix</flux:button>@endcan
                            @endif
                            @can('approve', $article)
                                <details class="publishing-disclosure"><summary>Override with reason</summary><div class="mt-3 space-y-3"><flux:textarea wire:model="findingReasons.{{ $finding->id }}" label="Why does this rule not apply here?" rows="2" /><flux:error name="findingReasons.{{ $finding->id }}" /><flux:button size="sm" wire:click="overrideVoiceRule({{ $finding->id }})">Record override</flux:button></div></details>
                            @endcan
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
