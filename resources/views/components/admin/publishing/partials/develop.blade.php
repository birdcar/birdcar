<div class="publishing-develop-grid">
    <div class="min-w-0 space-y-8">
        @if ($attempt === null || in_array($stage?->value, ['published', 'abandoned'], true))
            <div class="publishing-invitation" data-publishing-enter>
                <flux:heading level="2" size="xl">{{ $article->published_release_id ? 'Give this piece its next chapter.' : 'There is an article in here.' }}</flux:heading>
                <p class="mt-4 max-w-xl text-base leading-relaxed text-zinc-600 dark:text-zinc-300">Start with a conversation. Your editorial partner will ask questions, help find the argument, and bring a direction back for your approval.</p>
                @can('develop', $article)
                    <flux:button class="mt-7" variant="primary" icon:trailing="arrow-right" wire:click="startDevelopment" data-publishing-editor-action>Develop this idea</flux:button>
                    <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">Starts an interview with a $5 agent allowance. Nothing publishes without you.</p>
                @endcan
                @if ($article->published_release_id)<flux:callout class="mt-6" text="Your published version stays live while you work on a revision." />@endif
            </div>
        @else
            @foreach ($pendingAgentRequests as $request)
                <section wire:key="agent-request-{{ $request->id }}" class="publishing-interview" data-agent-approval="{{ $request->id }}" data-publishing-enter>
                    <div class="mb-5 flex items-center gap-3"><span class="publishing-partner-mark" aria-hidden="true"><flux:icon.chat-bubble-left-right variant="outline" class="size-5" /></span><flux:heading level="2" size="lg">A few questions for you</flux:heading></div>
                    <ol class="space-y-4 text-base leading-relaxed">
                        @foreach ($request->pending_tool_approvals ?? [] as $approval)
                            @foreach ($approval['arguments']['questions'] ?? [] as $question)
                                <li>{{ $question }}</li>
                            @endforeach
                        @endforeach
                    </ol>
                    @can('develop', $article)
                        <form wire:submit="answerAgent({{ $request->id }}, '{{ $request->pendingApprovalHash() }}')" class="mt-7 space-y-4" data-publishing-editor-action>
                            <flux:textarea wire:model="agentAnswers.{{ $request->id }}" label="Your thoughts" placeholder="Tell it in your own words. The useful details are usually the specific ones." maxlength="4000" rows="5" />
                            <div class="flex flex-wrap items-center gap-3">
                                <flux:button type="submit" variant="primary" icon="paper-airplane" tooltip="Send answers and continue" aria-label="Send answers and continue" />
                                <flux:button variant="ghost" wire:click="answerAgent({{ $request->id }}, '{{ $request->pendingApprovalHash() }}', true)">Decline request</flux:button>
                            </div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">Continues this conversation. Angle, plan, and release approvals stay separate.</p>
                        </form>
                    @endcan
                </section>
            @endforeach

            @if ($pendingAgentRequests->isEmpty() && $stage?->value === 'developing' && ! $working)
                @if ($interviewQuestions !== [])
                    <section class="space-y-5">
                        <flux:heading level="2" size="lg">Your perspective comes first</flux:heading>
                        <x-admin.publishing.partials.editorial-content :value="$interviewQuestions" />
                        @if (filled($interviewContext['answers'] ?? null))
                            <div class="publishing-response"><p class="mb-2 text-sm font-medium">Your answers</p><x-admin.publishing.partials.editorial-content :value="$interviewContext['answers']" /></div>
                        @endif
                        @can('develop', $article)
                            <form wire:submit="submitInterviewAnswers" class="space-y-4">
                                <flux:textarea wire:model="interviewAnswers" label="Anything to add?" rows="4" placeholder="Your experience, the point you want to make, or what the brief is missing." />
                                <flux:button type="submit">Save interview answers</flux:button>
                            </form>
                        @endcan
                    </section>
                @elseif ($angleOptions === [] && $displayAngle === [])
                    <div class="publishing-empty">
                        <flux:heading level="2" size="lg">Let’s find the argument.</flux:heading>
                        <flux:text class="mt-3">An interview turns the original thought into a direction you can stand behind.</flux:text>
                        @can('develop', $article)<flux:button class="mt-6" variant="primary" wire:click="startInterview" data-publishing-editor-action>Start interview</flux:button>@endcan
                    </div>
                @endif
            @endif

            @if ($angleOptions !== [] || $displayAngle !== [])
                <section class="space-y-5" data-interview-and-angle>
                    <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading level="2" size="lg">The angle</flux:heading><flux:badge size="sm" :color="$angleApproved ? 'teal' : 'zinc'">{{ $angleApproved ? 'Approved' : 'Your decision' }}</flux:badge></div>
                    <flux:text>Choose the argument you want to make. Approving it authorizes research and planning—not a full draft.</flux:text>
                    <div class="space-y-3">
                        @foreach ($angleOptions as $key => $option)
                            <div wire:key="angle-{{ $key }}" class="publishing-angle {{ (string) ($interviewContext['selected_angle_option'] ?? '') === (string) $key ? 'is-selected' : '' }}">
                                <x-admin.publishing.partials.editorial-content :value="$option" />
                                @can('develop', $article)
                                    <flux:button class="mt-4" size="sm" :variant="(string) ($interviewContext['selected_angle_option'] ?? '') === (string) $key ? 'primary' : 'filled'" :wire:click="'selectAngleOption('.\Illuminate\Support\Js::from((string) $key).')'" :aria-pressed="(string) ($interviewContext['selected_angle_option'] ?? '') === (string) $key ? 'true' : 'false'">{{ (string) ($interviewContext['selected_angle_option'] ?? '') === (string) $key ? 'Selected angle' : 'Choose this angle' }}</flux:button>
                                @endcan
                            </div>
                        @endforeach
                        @if ($angleOptions === [])<x-admin.publishing.partials.editorial-content :value="$displayAngle" />@endif
                    </div>
                    @if (! $angleApproved && ! $working)
                        @can('approve', $article)<flux:button variant="primary" wire:click="approveAngle('{{ $angleInputHash }}')" data-publishing-editor-action>Approve angle</flux:button>@endcan
                    @endif
                    @if ($angleApproved)<p class="text-sm text-zinc-600 dark:text-zinc-300">Choosing another angle withdraws downstream approvals. Your manuscript is preserved.</p>@endif
                </section>
            @endif

            @if ($displayPlan !== [])
                <section class="publishing-plan space-y-6" data-generated-plan-digest>
                    <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading level="2" size="lg">A plan for the piece</flux:heading><flux:badge size="sm" :color="$planApproved ? 'teal' : 'zinc'">{{ $planApproved ? 'Approved' : 'Your decision' }}</flux:badge></div>
                    @if (isset($displayPlan['argument']))<p class="text-lg leading-relaxed">{{ $displayPlan['argument'] }}</p>@endif
                    <div><flux:heading level="3">Outline</flux:heading><x-admin.publishing.partials.editorial-content class="mt-3" :value="$displayPlan['outline'] ?? []" /></div>
                    <div><flux:heading level="3">Visual plan</flux:heading><x-admin.publishing.partials.editorial-content class="mt-3" :value="$displayPlan['visualPlan'] ?? []" /></div>
                    @if (! $planApproved && $angleApproved && ! $working)
                        @can('approve', $article)<flux:button variant="primary" wire:click="approvePlan('{{ $planInputHash }}')" data-publishing-editor-action>Approve plan and draft</flux:button>@endcan
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">Authorizes drafting from this plan, within the remaining allowance.</p>
                    @elseif ($planApproved)
                        <flux:button variant="primary" @click="selectMode('write')" icon:trailing="arrow-right">Go to the manuscript</flux:button>
                    @endif
                </section>
            @endif
        @endif
    </div>
    <aside class="publishing-brief space-y-7">
        <div><flux:heading level="2" size="lg">The living brief</flux:heading><flux:text class="mt-2">The thought behind the work, kept close.</flux:text></div>
        <details class="publishing-disclosure" @if (mb_strlen($article->idea) < 260) open @endif><summary>The original idea</summary><p class="mt-3 whitespace-pre-wrap break-words text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $article->idea }}</p></details>
        @if ($displayBrief !== [])<x-admin.publishing.partials.editorial-content :value="array_diff_key($displayBrief, ['idea' => true])" />@else<flux:text>Your brief will take shape during the interview. You don’t need to fill out a specification first.</flux:text>@endif
        <details class="publishing-disclosure">
            <summary>Voice and source material</summary>
            <div class="mt-5 space-y-6">
                <flux:input wire:model="sourceUrl" label="A source to research" placeholder="https://…" type="url" />
                <div><p class="mb-3 text-sm font-medium">Voice samples from published archive</p><div class="space-y-3">
                    @forelse ($voiceSampleOptions as $sampleArticle)
                        <flux:checkbox wire:key="voice-{{ $sampleArticle->id }}" wire:model="selectedVoiceSampleArticleIds" value="{{ $sampleArticle->id }}" label="{{ $sampleArticle->publishedRelease?->payload['metadata']['title'] ?? $sampleArticle->idea }}" />
                    @empty<flux:text>Published articles will be available as voice references here.</flux:text>@endforelse
                </div></div>
                <div><p class="mb-3 text-sm font-medium">Retained owner sources</p><div class="space-y-3">
                    @forelse ($evidenceSources->whereIn('source_type', ['owner', 'restricted']) as $source)
                        <flux:checkbox wire:key="source-{{ $source->id }}" wire:model="selectedEvidenceSourceIds" value="{{ $source->id }}" label="{{ $source->title ?? $source->url ?? 'Source '.$source->id }}" />
                    @empty<flux:text>No owner sources have been retained yet.</flux:text>@endforelse
                </div></div>
                @if ($angleApproved && ! $planApproved && ! $working)
                    @can('develop', $article)<flux:button size="sm" wire:click="startResearch" data-publishing-editor-action>Research sources</flux:button>@endcan
                @endif
                <p class="text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">Using material with an agent does not grant permission to publish it.</p>
            </div>
        </details>
    </aside>
</div>
