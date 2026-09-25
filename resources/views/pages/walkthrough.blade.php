<?php

use function Laravel\Folio\name;

name('public.walkthrough');

$steps = [
    ['title' => 'Check the fit.', 'text' => 'Three questions, so neither of us spends the hour on the wrong thing.'],
    ['title' => 'Choose a time.', 'text' => 'A video call, or in person if you’d rather.'],
    ['title' => 'Walk me through it.', 'text' => 'Show me the work, awkward bits included.'],
    ['title' => 'Keep the report.', 'text' => 'My recommendations in writing, within three business days.'],
];

$questions = [
    ['question' => 'Is the Walkthrough really free?', 'answer' => 'Yes. The conversation and the written report are free. You don’t have to buy implementation to receive or use the report, and I won’t raise it unless you do.'],
    ['question' => 'Do I need to know what I want built?', 'answer' => 'No. Show me what’s painful and how it works today; you’ll leave with a clearer view of what would make it better.'],
    ['question' => 'Is this a fit for my business?', 'answer' => 'If you have customers, people doing the work, and a process that takes too much chasing, copying, or remembering, there’s something useful to look at. The operational problem matters more than the industry. One condition: this works when I can talk to the people doing the work. If that isn’t possible, I’m not the right fit.'],
    ['question' => 'What happens after I get the report?', 'answer' => 'You decide what to do with it. If you’d like my help, I can discuss a separate implementation engagement with you. If you want a deeper look before deciding, the same process runs as a paid discovery week with your team. You can also use the recommendations yourself or leave it there.'],
    ['question' => 'What can an hour actually tell you?', 'answer' => 'Enough to see the shape of the problem and where I’d look first. That’s what you get in the report: what’s happening, what I recommend, and where I’d start. When something needs a closer look, the report says so.'],
    ['question' => 'Is this an AI project?', 'answer' => 'It’s a Walkthrough of your work. AI might be useful, and so might connecting the tools you already use or changing a process. I recommend what makes sense for the problem.'],
    ['question' => 'Is this about replacing my team?', 'answer' => 'No. The point is to give the people you already have room for work that’s worth their time. That rules out surveillance, and projects whose whole purpose is cutting people regardless of the consequences.'],
];

?>
<x-marketing.layout title="Book a free Walkthrough" active="walkthrough" description="A free hour with me on the work that keeps coming back to you, then a written report within three business days. Yours to keep.">
    <section class="wt-hero" aria-labelledby="offer-heading">
        <div class="wt-hero-copy">
            <h1 id="offer-heading">An hour on the work.<br> A report you can use.</h1>
            <p class="wt-lead">In a free Walkthrough, you spend about an hour showing me the work that’s giving you trouble. Within three business days you get my recommendations in writing.</p>
            <p class="wt-promise">The hour is about your work. If you want to talk about hiring me, you’ll bring it up, not me.</p>
            <aside class="wt-report" aria-labelledby="report-heading">
                <h2 id="report-heading">Your Walkthrough report</h2>
                <ul role="list">
                    <li>What’s happening</li>
                    <li>What I recommend</li>
                    <li class="wt-report-start">Where I’d start</li>
                </ul>
            </aside>
            <p class="wt-report-terms">In writing within three business days. Yours to keep.</p>
        </div>
        <div class="wt-desk">
            <picture>
                <source type="image/webp" srcset="{{ asset('images/walkthrough/desk-1000.webp') }} 1000w, {{ asset('images/walkthrough/desk.webp') }} 2000w" sizes="(min-width: 1100px) 64vw, 100vw">
                <img class="wt-desk-plate" src="{{ asset('images/walkthrough/desk.png') }}" width="2000" height="1179" alt="" fetchpriority="high">
            </picture>
            <div class="wt-card-stack" aria-hidden="true"></div>
            <x-marketing.fit-card />
            <picture>
                <source type="image/webp" srcset="{{ asset('images/walkthrough/canary.webp') }}?v=2">
                <img class="wt-canary" src="{{ asset('images/walkthrough/canary.png') }}?v=2" width="320" height="334" alt="">
            </picture>
        </div>
    </section>

    <ol class="wt-steps" aria-label="How the Walkthrough works">
        @foreach ($steps as $step)
            <li><span class="wt-step-number" aria-hidden="true">{{ $loop->iteration }}</span><div><h2>{{ $step['title'] }}</h2><p>{{ $step['text'] }}</p></div></li>
        @endforeach
    </ol>

    <section class="wt-problem studio-section-intro" aria-labelledby="outcome-heading">
        <h2 id="outcome-heading">Bring the problem.<br> I’ll bring the questions.</h2>
        <div>
            <p>When you’re the person keeping track of everything, it’s hard to step back far enough to see what needs to change. So I start with the people doing the work, not with software.</p>
            <p>Maybe onboarding a new client involves a dozen reminders. Maybe reporting eats an afternoon. Maybe the process works perfectly, as long as you’re there to keep it working. Left alone, that kind of work has one fix: another coordinator, another subscription, or another late night. That’s the expensive version of standing still.</p>
            <p>What would change if the reminders sent themselves and the report was ready before you asked for it? You don’t need to diagnose it first. Bring one example, and an hour is enough to see the shape of it.</p>
            <a class="studio-link" href="{{ route('public.where-work-gets-stuck') }}">Not sure how to describe it? Start with these eight <x-marketing.arrow /></a>
        </div>
    </section>

    <section class="wt-choice" aria-labelledby="choice-heading">
        <h2 id="choice-heading">The next step is your choice.</h2>
        <div>
            <p>The report covers what’s happening, what I recommend, and where I’d start: the first change I’d make for you, and why it comes first.</p>
            <p>The free Walkthrough gives you a report, not a working implementation. You keep it either way.</p>
            <ul role="list">
                <li>Use the recommendations yourself.</li>
                <li>Talk to me about a separate implementation purchase.</li>
                <li>Keep the report, or leave it there.</li>
            </ul>
        </div>
    </section>

    <section class="wt-faq" aria-labelledby="questions-heading">
        <h2 id="questions-heading">A few fair questions.</h2>
        <div>
            @foreach ($questions as $item)
                <details><summary>{{ $item['question'] }}<span class="wt-faq-icon" aria-hidden="true"></span></summary><p>{{ $item['answer'] }}</p></details>
            @endforeach
        </div>
    </section>

    <section class="studio-close wt-close" aria-labelledby="book-heading">
        <h2 id="book-heading">Make a little room to figure it out.</h2>
        <p>Answer three quick checks and pick an hour that suits you. The report follows within three business days, and it’s yours whatever you decide.</p>
        <a class="studio-button" href="#choose-a-time" data-booking-cta="walkthrough-close">Check the fit and pick a time <x-marketing.arrow /></a>
        <p class="studio-terms">About an hour with me. A written report within three business days. Free.</p>
    </section>
</x-marketing.layout>
