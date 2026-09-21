@props(['animate' => false, 'caption' => null])
<figure {{ $attributes->class(['walkthrough-diagram']) }} @if ($animate) data-walkthrough-diagram @endif>
    <ol class="walkthrough-stages" role="list">
        <li>
            <div class="stage-connection" aria-hidden="true">
                <span data-diagram-emphasis>1</span>
                <svg viewBox="0 0 24 24" preserveAspectRatio="xMidYMax meet" focusable="false"><path d="M12 -4000V22m-7-8 7 8 7-8" /></svg>
            </div>
            <h3>Show me the work</h3>
            <p>Pick a process your team actually performs: chasing a follow-up, putting a report together, getting a new client started.</p>
            <p>Then spend about an hour with me and the person doing the work. Walk me through what happens, including the awkward bits. If you want to talk about hiring me, you’ll bring it up, not me.</p>
            <svg class="stage-handoff" viewBox="0 0 64 24" preserveAspectRatio="xMaxYMid meet" aria-hidden="true" focusable="false"><path d="M-4000 12H62m-8-7 8 7-8 7" /></svg>
        </li>
        <li>
            <div class="stage-connection" aria-hidden="true">
                <span data-diagram-emphasis>2</span>
                <svg viewBox="0 0 24 24" preserveAspectRatio="xMidYMax meet" focusable="false"><path d="M12 -4000V22m-7-8 7 8 7-8" /></svg>
            </div>
            <h3>Keep the report</h3>
            <p>Within three business days, I’ll send you a written report:</p>
            <div class="report-document">
                <dl class="report-contents">
                    <div><dt>What’s getting in the way</dt><dd>The problems I observed in the work.</dd></div>
                    <div><dt>What I’d change</dt><dd>Recommended improvements and why they help.</dd></div>
                    <div class="first-recommendation" data-diagram-recommendation><dt>Where I’d start</dt><dd>The first change and why it comes first.</dd></div>
                </dl>
            </div>
            <svg class="stage-handoff" viewBox="0 0 64 24" preserveAspectRatio="xMaxYMid meet" aria-hidden="true" focusable="false"><path d="M-4000 12H62m-8-7 8 7-8 7" /></svg>
        </li>
        <li>
            <div class="stage-connection" aria-hidden="true"><span data-diagram-emphasis>3</span></div>
            <h3>Choose what happens next</h3>
            <ul class="walkthrough-choices" role="list">
                <li><svg viewBox="0 0 32 24" preserveAspectRatio="xMinYMid meet" aria-hidden="true" focusable="false"><path d="M1 -4000V4000M1 12H30m-8-7 8 7-8 7" /></svg>Use the recommendations yourself.</li>
                <li><svg viewBox="0 0 32 24" preserveAspectRatio="xMinYMid meet" aria-hidden="true" focusable="false"><path d="M1 -4000V4000M1 12H30m-8-7 8 7-8 7" /></svg>Hire me for a separate implementation.</li>
                <li><svg viewBox="0 0 32 24" preserveAspectRatio="xMinYMid meet" aria-hidden="true" focusable="false"><path d="M1 -4000V12H30m-8-7 8 7-8 7" /></svg>Or do nothing. No purchase obligation.</li>
            </ul>
        </li>
    </ol>
    @if ($caption)<figcaption>{{ $caption }}</figcaption>@endif
</figure>
