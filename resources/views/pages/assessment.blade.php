<?php

use function Laravel\Folio\name;

name('public.assessment');

?>
<x-marketing.layout title="Book a free business assessment" active="assessment" description="Find out what’s making work harder than it needs to be. A free, hour-long conversation with me and a written report of recommended improvements, yours to keep.">
    <section class="offer-opening" aria-labelledby="offer-heading">
        <div class="offer-copy">
            <h1 id="offer-heading">Does it all come<br>back to you?</h1>
            <p class="offer-lead">Let’s work out what would change that.</p>
            <p>When you’re the person keeping track of everything, it’s hard to step back far enough to see what needs to change. I’ll take a closer look with you.</p>
            <p>In a free assessment, I’ll spend about an hour getting to know the work that’s giving you trouble. Afterward, I’ll write up the problems I’ve understood and the improvements I recommend.</p>
            <a class="button button-lilac" href="#choose-a-time">Book my free assessment <x-marketing.arrow /></a>
            <p class="action-note">About an hour with me. A written report you keep. Free.</p>
        </div>
        <aside class="offer-report" aria-labelledby="report-heading">
            <h2 id="report-heading">A clearer picture.<br>A useful next step.</h2>
            <p>Your assessment report</p>
            <dl>
                <div><dt>What’s happening</dt><dd>The problems I’ve understood from your work.</dd></div>
                <div><dt>What I recommend</dt><dd>Improvements worth considering for your business.</dd></div>
                <div><dt>Something to work from</dt><dd>A written report you can put to use, with or without me.</dd></div>
            </dl>
            <span class="report-signoff">Yours to keep.</span>
        </aside>
    </section>
    <section class="offer-outcome section-space" aria-labelledby="outcome-heading">
        <h2 id="outcome-heading">Bring the problem.<br>I’ll bring the questions.</h2>
        <div><p>Maybe onboarding a new client involves a dozen reminders. Maybe reporting eats an afternoon. Maybe the process works perfectly, as long as you’re there to keep it working.</p><p>You don’t need to diagnose it first. Pick an example of work that’s harder than it ought to be, and I’ll help you look at what’s going on.</p></div>
    </section>
    <section class="offer-steps section-space" aria-labelledby="meeting-heading">
        <h2 id="meeting-heading">Here’s how it works.</h2>
        <ol>
            <li><span class="step-number">1</span><div><h3>Choose a time.</h3><p>Book an hour that works for you. Bring an example of the work you’d like to talk through; a few notes are plenty.</p></div></li>
            <li><span class="step-number">2</span><div><h3>Walk me through it.</h3><p>I’ll ask questions about how the work happens, who’s involved, and where it gets difficult. I want to understand the problem in its context.</p></div></li>
            <li><span class="step-number">3</span><div><h3>Keep the report.</h3><p>After the conversation, I’ll put my observations and recommendations in writing. You can use them independently or ask me to help put them into practice.</p></div></li>
        </ol>
    </section>
    <section class="assessment-faq section-space" aria-labelledby="questions-heading">
        <h2 id="questions-heading">A few fair questions.</h2>
        <div>
            <details><summary>Is the assessment really free?<x-marketing.arrow /></summary><p>Yes. The conversation and the written report are free. You don’t have to buy implementation to receive or use the report.</p></details>
            <details><summary>Do I need to know what I want built?<x-marketing.arrow /></summary><p>No. Tell me what’s painful, show me how it works today, and I’ll help you think through what would make it better.</p></details>
            <details><summary>Is this a fit for my business?<x-marketing.arrow /></summary><p>If you have customers, people doing the work, and a process that takes too much chasing, copying, or remembering, there’s something useful to look at. The operational problem matters more than the industry.</p></details>
            <details><summary>What happens after I get the report?<x-marketing.arrow /></summary><p>You decide what to do with it. If you’d like my help, I can discuss a separate implementation engagement with you. You can also use the recommendations yourself or leave it there.</p></details>
            <details><summary>What can an hour actually tell you?<x-marketing.arrow /></summary><p>An hour gives me a starting view of the problem you bring. My report reflects what I can learn in that conversation. If something needs a closer look, I’ll say so.</p></details>
            <details><summary>Is this an AI assessment?<x-marketing.arrow /></summary><p>It’s an assessment of the work. AI might be useful, and so might connecting the tools you already use or changing a process. I’ll recommend what makes sense for the problem.</p></details>
        </div>
    </section>
    <section class="offer-close" id="choose-a-time" aria-labelledby="book-heading">
        <h2 id="book-heading">Make a little room<br>to figure it out.</h2>
        <p>Pick an hour that suits you. A conversation with me, then practical recommendations in writing.<br>Yours to use however you choose.</p>
        <div class="booking-calendar" data-cal-inline data-cal-link="{{ config('marketing.booking_calendar') }}" data-cal-namespace="{{ config('marketing.booking_namespace') }}"></div>
        <p class="action-note">If the calendar doesn’t load, <a href="{{ config('marketing.booking_url') }}">book on Cal.com</a>.</p>
    </section>
</x-marketing.layout>
