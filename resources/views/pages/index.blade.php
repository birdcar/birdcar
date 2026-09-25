<?php

use function Laravel\Folio\name;

name('public.index');

$storyTags = [
    ['x' => 78.5, 'y' => 27.8, 'today' => 'Approval?', 'pattern' => 'Waiting on your yes', 'slug' => 'waiting-on-your-yes', 'fixed' => 'Routed, not waiting'],
    ['x' => 12.9, 'y' => 38.6, 'today' => 'Quote request', 'pattern' => 'Waiting on a reply', 'slug' => 'waiting-on-a-reply', 'fixed' => 'Quote sent'],
    ['x' => 39.9, 'y' => 44.8, 'today' => 'Crew schedule', 'pattern' => 'It runs on remembering', 'slug' => 'it-runs-on-remembering', 'fixed' => 'Schedule synced'],
    ['x' => 62.9, 'y' => 55.3, 'today' => 'Friday report', 'pattern' => 'Assembled by hand', 'slug' => 'assembled-by-hand', 'fixed' => 'Report ready'],
    ['x' => 38.6, 'y' => 59.2, 'today' => 'Invoice', 'pattern' => 'The same information, typed twice', 'slug' => 'the-same-information-typed-twice', 'fixed' => 'Invoice sent'],
];

$models = [
    ['image' => 'cap-connect', 'name' => 'Connected tools', 'text' => 'Your CRM, calendar, and accounting share what they know, so nobody copies it between them.'],
    ['image' => 'cap-internal', 'name' => 'Internal tools', 'text' => 'The spreadsheet everyone keeps open becomes software that fits the job.'],
    ['image' => 'cap-portal', 'name' => 'Client portals', 'text' => 'Clients check status, send files, and approve work without calling you.'],
    ['image' => 'cap-reporting', 'name' => 'Reports that build themselves', 'text' => 'The Friday report is ready before anyone asks for it.'],
    ['image' => 'cap-followup', 'name' => 'Follow-up on time', 'text' => 'Reminders, invoices, and check-ins go out when they should, not when someone remembers.'],
    ['image' => 'cap-review', 'name' => 'AI-assisted work, reviewed by people', 'text' => 'Drafting, sorting, and summarizing that your team checks before anything goes out.'],
];

?>
<x-marketing.layout :home="true" title="Better work, faster. Without another hire." description="I find where work keeps landing on your desk, then build the tools that let your team handle it without you. Start with a free Walkthrough and a written report you keep.">
    <section class="studio-hero" aria-labelledby="home-heading">
        <div class="studio-hero-copy">
            <h1 id="home-heading">Better work,<br> faster. Without<br> another hire.</h1>
            <p class="studio-lead">I find where work keeps landing on your desk, then build the tools that let your team handle it without you.</p>
            <x-marketing.booking-link class="studio-button" placement="hero">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
            <p class="studio-terms">One pitch-free hour. A written report within three business days. Yours to keep.</p>
        </div>
        <figure class="miniature miniature-hero" data-miniature data-miniature-state="today">
            <div class="miniature-stage" data-miniature-tilt>
                <x-marketing.miniature-plate priority />
                <canvas class="miniature-flow" aria-hidden="true"></canvas>
                <ul class="miniature-tags" aria-label="Work that ends up on the owner’s desk">
                    @foreach ($storyTags as $tag)
                        <li style="--x: {{ $tag['x'] }}%; --y: {{ $tag['y'] }}%">{{ $tag['today'] }}</li>
                    @endforeach
                </ul>
            </div>
        </figure>
    </section>

    <section class="studio-proof" aria-labelledby="proof-heading">
        <h2 id="proof-heading">Where I’ve built systems</h2>
        <x-marketing.employer-marks />
    </section>

    <section class="studio-story" id="how-i-work" aria-labelledby="story-heading">
        <h2 id="story-heading">Here’s what changes when the work stops routing through you.</h2>
        <div class="story-track">
            <ol class="story-steps">
                <li class="story-step" data-story-step="today">
                    <h3>Today, every question finds its way to your desk.</h3>
                    <p>The quote waiting on your approval. The crew schedule that changed again. The Friday report someone builds by hand. None of it is hard. It all just needs you.</p>
                </li>
                <li class="story-step" data-story-step="walkthrough">
                    <h3>In a Walkthrough, I follow the work through the business.</h3>
                    <p>You and your team show me how it really happens. I write down where it gets stuck and why, using names you’ll recognize, and send you a report within three business days, including where I’d start.</p>
                    <a class="studio-link" href="{{ route('public.where-work-gets-stuck') }}">Eight ways I see work get stuck <x-marketing.arrow /></a>
                </li>
                <li class="story-step" data-story-step="fixed">
                    <h3>Then I build what fixes it.</h3>
                    <p>Tools shaped around how your business already works: the report assembles itself, approvals reach the right person, the schedule updates everyone. Your team does better work, faster, and growth stops meaning another hire.</p>
                </li>
            </ol>
            <div class="story-figure">
                <figure class="miniature miniature-story" data-miniature data-miniature-state="today">
                    <fieldset class="miniature-control">
                        <legend>Show the business</legend>
                        <label><input type="radio" name="miniature-state" value="today" checked> Today</label>
                        <label><input type="radio" name="miniature-state" value="walkthrough"> Walkthrough</label>
                        <label><input type="radio" name="miniature-state" value="fixed"> After the fix</label>
                    </fieldset>
                    <div class="miniature-stage">
                        <x-marketing.miniature-plate alt="The same business, shown in three states: today, during the Walkthrough, and after the fix." />
                        <img class="miniature-patch" data-patch="walkthrough" src="{{ asset('images/home/owner-office-conversation.webp') }}" width="640" height="580" alt="" loading="lazy">
                        <img class="miniature-patch" data-patch="fixed" src="{{ asset('images/home/owner-office-after.webp') }}" width="640" height="580" alt="" loading="lazy">
                        <canvas class="miniature-flow" aria-hidden="true"></canvas>
                        <ul class="miniature-tags miniature-tags-story" aria-label="What each desk is waiting on">
                            @foreach ($storyTags as $tag)
                                <li style="--x: {{ $tag['x'] }}%; --y: {{ $tag['y'] }}%">
                                    <span data-when="today">{{ $tag['today'] }}</span>
                                    <a data-when="walkthrough" href="{{ route('public.where-work-gets-stuck') }}#{{ $tag['slug'] }}">{{ $tag['pattern'] }}</a>
                                    <span data-when="fixed">{{ $tag['fixed'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </figure>
            </div>
        </div>
    </section>

    <section class="studio-build" aria-labelledby="build-heading">
        <div class="studio-section-intro">
            <h2 id="build-heading">What I build</h2>
            <p>Whatever the Walkthrough turns up, the fix usually looks like one of these: built around your business and tested with the people who’ll use it.</p>
        </div>
        <ul class="model-shelf">
            @foreach ($models as $model)
                <li class="model">
                    <img src="{{ asset('images/home/'.$model['image'].'.webp') }}" width="560" height="560" alt="" loading="lazy">
                    <h3>{{ $model['name'] }}</h3>
                    <p>{{ $model['text'] }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="studio-offer" aria-labelledby="offer-heading">
        <div class="studio-section-intro">
            <h2 id="offer-heading">It starts with a free Walkthrough.</h2>
            <p>No software brief needed. Bring one process that’s harder than it should be.</p>
        </div>
        <ol class="offer-track">
            <li>
                <h3>The Walkthrough</h3>
                <p class="offer-terms">Free · about an hour</p>
                <p>You and your team show me the work, on a video call or in person. If you want to talk about hiring me, you’ll bring it up, not me.</p>
            </li>
            <li class="offer-report">
                <h3>The report</h3>
                <p class="offer-terms">Free · within three business days</p>
                <p>What’s getting in the way, what I’d change, and <strong>where I’d start</strong>. Yours to keep, whatever you decide.</p>
            </li>
            <li>
                <h3>The build</h3>
                <p class="offer-terms">Scoped together</p>
                <p>If you want my help, we agree on scope and schedule first. I build it and test it with the people who’ll use it. Implementation is a separate purchase.</p>
            </li>
            <li>
                <h3>Care</h3>
                <p class="offer-terms">Optional</p>
                <p>You get a working system, documentation, and training. I can stay involved as your team settles in and the business changes.</p>
            </li>
        </ol>
        <div class="offer-actions">
            <x-marketing.booking-link class="studio-button" placement="homepage-strip">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
            <a class="studio-link" href="{{ route('public.walkthrough') }}">What happens in a Walkthrough? <x-marketing.arrow /></a>
        </div>
    </section>

    <section class="studio-work" aria-labelledby="work-heading">
        <h2 id="work-heading">Craft &amp; Communicate</h2>
        <div>
            <p class="studio-work-lead">Finding performance numbers by hand was part of the agency’s reporting work. I built a client-facing reporting platform with client management and live-updating data: something the agency can offer its customers.</p>
            <a class="studio-link" href="{{ route('public.work') }}#craft-and-communicate">Read about the work <x-marketing.arrow /></a>
        </div>
    </section>

    <section class="studio-person" aria-labelledby="person-heading">
        <img class="studio-person-model" src="{{ asset('images/home/birdcar.webp') }}" width="720" height="720" alt="A small cream-and-teal vintage car with a canary perched on its roof: a bird and a car." loading="lazy">
        <div>
            <h2 id="person-heading">Hi, I’m Nick Cannariato. Most people call me Birdcar.</h2>
            <p>Cannariato sounds like “canary auto”: a bird and a car. The nickname has stuck for more than twenty years.</p>
            <p>I’ve spent fifteen years building customer-facing technical systems: internal tools, solutions engineering, and a software business of my own. Some of that work was for GitHub, Heroku, and Zapier. Now I bring what works inside those companies to businesses that don’t have an engineering team.</p>
            <p>When you work with me, you work with me. No account managers, no handoffs.</p>
        </div>
    </section>

    <section class="studio-close" aria-labelledby="invitation-heading">
        <h2 id="invitation-heading">Show me the part that keeps landing on your desk.</h2>
        <p>You don’t need a software brief. Bring one example of work that takes more than it should. The hour is about your work, not a pitch.</p>
        <x-marketing.booking-link class="studio-button" placement="closing-invitation">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
        <p class="studio-terms">About an hour. A written report within three business days. Yours to keep.</p>
    </section>
</x-marketing.layout>
