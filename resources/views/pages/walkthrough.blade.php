<?php

use function Laravel\Folio\name;

name('public.walkthrough');

?>
<x-marketing.layout title="Book a free Walkthrough" active="walkthrough" description="A free hour with me on the work that keeps coming back to you, then a written report within three business days. Yours to keep.">
    <section class="offer-opening" aria-labelledby="offer-heading">
        <div class="offer-copy">
            <h1 id="offer-heading">Does it all come<br>back to you?</h1>
            <p class="offer-lead">Let’s work out what would change that.</p>
            <p>When you’re the person keeping track of everything, it’s hard to step back far enough to see what needs to change. So I start with the people doing the work, not with software.</p>
            <p>In a free Walkthrough, you spend about an hour showing me the work that’s giving you trouble. Within three business days you get my recommendations in writing.</p>
            <p>The hour is about your work. If you want to talk about hiring me, you’ll bring it up, not me.</p>
            <x-marketing.booking-link inline placement="walkthrough-hero" class="button button-lilac">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
            <p class="action-note">About an hour with me. A written report within three business days. Free.</p>
        </div>
        <aside class="offer-report" aria-labelledby="report-heading">
            <h2 id="report-heading">A clearer picture.<br>A useful next step.</h2>
            <p>Your Walkthrough report</p>
            <dl>
                <div><dt>What’s happening</dt><dd>The problems in your work as I’ve understood them.</dd></div>
                <div><dt>What I recommend</dt><dd>What I’d change, and what each change fixes for you.</dd></div>
                <div><dt>Where I’d start</dt><dd>The first change I’d make for you, and why it comes first.</dd></div>
            </dl>
            <span class="report-signoff">Yours to keep.</span>
        </aside>
    </section>
    <section class="offer-outcome section-space" aria-labelledby="outcome-heading">
        <h2 id="outcome-heading">Bring the problem.<br>I’ll bring the questions.</h2>
        <div><p>Maybe onboarding a new client involves a dozen reminders. Maybe reporting eats an afternoon. Maybe the process works perfectly, as long as you’re there to keep it working. Left alone, that kind of work has one fix: another coordinator, another subscription, or another late night. That’s the expensive version of standing still.</p><p>What would change if the reminders sent themselves and the report was ready before you asked for it? You don’t need to diagnose it first. Bring one example, and an hour is enough to see the shape of it.</p></div>
    </section>
    <section class="offer-steps section-space" aria-labelledby="meeting-heading">
        <h2 id="meeting-heading">Here’s how it works.</h2>
        <ol>
            <li><span class="step-number">1</span><div><h3>Choose a time.</h3><p>Book an hour that works for you. Bring an example of the work you’d like to talk through; a few notes are plenty.</p></div></li>
            <li><span class="step-number">2</span><div><h3>Walk me through it.</h3><p>You walk me through how the work happens, who’s involved, and where it gets difficult. I keep asking until the problem makes sense in your context.</p></div></li>
            <li><span class="step-number">3</span><div><h3>Keep the report.</h3><p>After the conversation, you get my observations and recommendations in writing, to use on your own or with my help.</p></div></li>
        </ol>
    </section>
    <section class="assessment-faq section-space" aria-labelledby="questions-heading">
        <h2 id="questions-heading">A few fair questions.</h2>
        <div>
            <details><summary>Is the Walkthrough really free?<x-marketing.arrow /></summary><p>Yes. The conversation and the written report are free. You don’t have to buy implementation to receive or use the report, and I won’t raise it unless you do.</p></details>
            <details><summary>Do I need to know what I want built?<x-marketing.arrow /></summary><p>No. Show me what’s painful and how it works today; you’ll leave with a clearer view of what would make it better.</p></details>
            <details><summary>Is this a fit for my business?<x-marketing.arrow /></summary><p>If you have customers, people doing the work, and a process that takes too much chasing, copying, or remembering, there’s something useful to look at. The operational problem matters more than the industry. One condition: this works when I can talk to the people doing the work. If that isn’t possible, I’m not the right fit.</p></details>
            <details><summary>What happens after I get the report?<x-marketing.arrow /></summary><p>You decide what to do with it. If you’d like my help, I can discuss a separate implementation engagement with you. If you want a deeper look before deciding, the same process runs as a paid discovery week with your team. You can also use the recommendations yourself or leave it there.</p></details>
            <details><summary>What can an hour actually tell you?<x-marketing.arrow /></summary><p>Enough to see the shape of the problem and where I’d look first. That’s what you get in the report: what’s happening, what I recommend, and where I’d start. When something needs a closer look, the report says so.</p></details>
            <details><summary>Is this an AI project?<x-marketing.arrow /></summary><p>It’s a Walkthrough of your work. AI might be useful, and so might connecting the tools you already use or changing a process. I recommend what makes sense for the problem.</p></details>
            <details><summary>Is this about replacing my team?<x-marketing.arrow /></summary><p>No. The point is to give the people you already have room for work that’s worth their time. That rules out surveillance, and projects whose whole purpose is cutting people regardless of the consequences.</p></details>
        </div>
    </section>
    <section class="offer-close" id="choose-a-time" aria-labelledby="book-heading">
        <h2 id="book-heading">Make a little room<br>to figure it out.</h2>
        <p>Pick an hour that suits you. The report follows within three business days,<br>and it’s yours whatever you decide.</p>
        <div class="booking-calendar" data-cal-inline data-cal-link="{{ config('marketing.booking_calendar') }}" data-cal-namespace="{{ config('marketing.booking_namespace') }}"></div>
        <p class="action-note">If the calendar doesn’t load, <a href="{{ config('marketing.booking_url') }}" data-booking-fallback>book on Cal.com</a>.</p>
    </section>
</x-marketing.layout>
