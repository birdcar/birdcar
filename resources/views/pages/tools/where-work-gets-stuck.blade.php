<?php

use App\Services\MarketingSite;

use function Laravel\Folio\name;

name('public.where-work-gets-stuck');

?>
@php
    $patterns = [
        [
            'slug' => 'it-runs-on-remembering',
            'name' => 'It runs on remembering',
            'definition' => 'Steps happen because someone remembers to do them, not because anything prompts them.',
            'scene' => 'A new client signs, and somebody knows what that means: a welcome email, a shared folder, a kickoff invite, a note to billing, a check-in two weeks later. Nothing asks for any of it. It works right up until that person is busy, on vacation, or has eleven other things to remember that week.',
            'signals' => ['Reminders to yourself: sticky notes, flagged emails, calendar holds used as a to-do list', '“I just know to…”', 'Things slip in busy weeks, not quiet ones'],
            'helps' => 'Move the remembering into the process. Sometimes that’s an automatic follow-up or a trigger between tools you already have. Sometimes it’s a checklist that starts itself. The point isn’t to take judgment away from people. It’s to stop spending their judgment on recall.',
        ],
        [
            'slug' => 'the-same-information-typed-twice',
            'name' => 'The same information, typed twice',
            'definition' => 'Information gets moved between tools by hand.',
            'scene' => 'An order comes in through one system and gets retyped into another. A client’s new address gets updated in the CRM, then in invoicing, then in the shared spreadsheet, if someone remembers. Each copy takes a minute. Together they take a surprising share of someone’s week, and every one is a chance for two places to disagree.',
            'signals' => ['Regular exports and imports', 'Copy and paste between tabs', '“I update it in both places”', 'Small mismatches nobody can explain'],
            'helps' => 'Connect the tools you already have before adding another. Often the fix is picking one place where the information starts and letting everything else read from it.',
        ],
        [
            'slug' => 'nobody-trusts-the-numbers',
            'name' => 'Nobody trusts the numbers',
            'definition' => 'People double-check the system because it’s been wrong before.',
            'scene' => 'The dashboard says one thing, so someone pulls the raw figures to make sure. The report goes out only after a person has gone through it line by line. Nobody decided to work this way. The system was wrong once, at a bad moment, and checking became part of the job.',
            'signals' => ['A spreadsheet kept open “just in case”', 'Manual reconciliation before anything goes out', '“Let me confirm that and get back to you”', 'More than one version of the same number'],
            'helps' => 'Fix where the numbers come from, not how they’re displayed. Another dashboard built on untrusted data just gives people one more thing to check. Once there’s a source people believe, the checking tends to stop on its own.',
        ],
        [
            'slug' => 'assembled-by-hand',
            'name' => 'Assembled by hand',
            'definition' => 'Something gets rebuilt from scratch every time, from pieces scattered across several places.',
            'scene' => 'Friday afternoon, someone opens five tabs and starts pulling numbers into a report that looks almost exactly like last week’s. The report is useful. Making it is the problem: the same hours, every week, to produce something the business already had the ingredients for.',
            'signals' => ['“It takes an afternoon to pull together”', 'Reports with the same structure every time', 'The report is late whenever the person who makes it is out', 'People asking “is this current?”'],
            'helps' => 'Build it once so it keeps itself up to date. Craft & Communicate’s client reporting involved finding performance numbers by hand. I built them a client-facing platform with live data, and it’s now something the agency offers its customers.',
            'link' => ['label' => 'Read about the work', 'href' => route('public.work').'#craft-and-communicate'],
        ],
        [
            'slug' => 'waiting-on-a-reply',
            'name' => 'Waiting on a reply',
            'definition' => 'Work stops at handoffs, approvals, and questions between people.',
            'scene' => 'Every step is quick. The waiting between steps isn’t. A proposal sits until someone signs off. A client’s question sits until the one person who knows the answer sees it. A task is done, but nobody downstream has heard. Much of the time isn’t spent working at all; the work is waiting to be noticed.',
            'signals' => ['Chasing: “Just following up on…”', 'Meetings that exist mainly to find out where things are', '“I’m waiting on…” as a common answer', 'Work that moves fast once it finally moves'],
            'helps' => 'Make ownership and status visible, and cut the handoffs that don’t need to exist. Sometimes that’s software. Often it’s agreeing who decides what, and making it obvious where each piece of work is.',
        ],
        [
            'slug' => 'only-one-person-knows-how',
            'name' => 'Only one person knows how',
            'definition' => 'Part of the business depends on one person’s knowledge.',
            'scene' => 'Dana knows how the quarterly filing works, which client needs their invoice formatted differently, and why that one integration breaks on the first of the month. Dana is great. Dana also can’t take a real vacation, and if Dana ever leaves, a lot of that knowledge leaves too.',
            'signals' => ['“Ask Dana”', 'Nobody can cover when that person is out', 'No documentation, or documentation only that person understands', 'New hires shadow instead of learning the work'],
            'helps' => 'Get the knowledge out of one head and into the work. Write it down, share it with a second person, or build it into the system so nobody has to carry it around. This isn’t about making anyone replaceable. It’s about letting the person who knows everything take a week off.',
        ],
        [
            'slug' => 'waiting-on-your-yes',
            'name' => 'Waiting on your yes',
            'definition' => 'Decisions come to you by default, not because they need you.',
            'scene' => 'You’re cc’d on everything. Small calls wait for you: a refund, a schedule change, a discount for a longtime client. Nobody’s sure they’re allowed to make them. Your team isn’t unwilling. They just don’t have a clear line between “decide this” and “check with me,” so it all lands on your desk. This is often the plainest answer to “why does everything come back to you?”',
            'signals' => ['Approvals pile up when you’re busy', 'Capable people hesitate on things they could handle', 'You answer the same kind of question again and again', 'Work slows down when you travel'],
            'helps' => 'This is usually a process change more than a software change: clear decision rules, limits people can act within, and a short list of what genuinely needs you. Software can help hold those lines later.',
        ],
        [
            'slug' => 'the-workaround-became-the-process',
            'name' => 'The workaround became the process',
            'definition' => 'Someone built a workaround to cover a gap, and now the business depends on it.',
            'scene' => 'The booking tool couldn’t handle the way you actually schedule, so someone started a shared spreadsheet. That was three years ago. Now the spreadsheet holds information the booking tool never will, new hires learn it first, and it has a name. Nobody’s quite sure what would happen if it disappeared.',
            'signals' => ['It has a name: “the Monday sheet,” “Jen’s tracker”', '“We do it this way because the system can’t…”', 'New people learn the workaround before the tool', 'The person who built it is the only one who can fix it'],
            'helps' => 'Decide on purpose: make the workaround official, or fix the gap it was covering. A workaround is often the best thinking anyone has done about how the work should go, and someone built it because they cared. Sometimes the right move is to take their design seriously and build it properly.',
        ],
    ];

    $canonical = app(MarketingSite::class)->url(route('public.where-work-gets-stuck', absolute: false));
    $setId = $canonical.'#patterns';
    $schema = [
        [
            '@type' => 'DefinedTermSet',
            '@id' => $setId,
            'name' => 'Eight ways work gets stuck',
            'description' => 'The patterns Birdcar uses to name why operational work keeps coming back to a business owner.',
            'url' => $canonical,
            'hasDefinedTerm' => array_map(fn (array $pattern): array => ['@id' => $canonical.'#'.$pattern['slug']], $patterns),
        ],
        ...array_map(fn (array $pattern): array => [
            '@type' => 'DefinedTerm',
            '@id' => $canonical.'#'.$pattern['slug'],
            'name' => $pattern['name'],
            'description' => $pattern['definition'],
            'url' => $canonical.'#'.$pattern['slug'],
            'inDefinedTermSet' => ['@id' => $setId],
        ], $patterns),
    ];

    $cards = [
        ['x' => 47.267, 'y' => 23.224, 'matrix' => '0.971, 0.102, -0.269, 1.019'],
        ['x' => 59.162, 'y' => 24.525, 'matrix' => '0.946, 0.132, -0.28, 1.032'],
        ['x' => 70.877, 'y' => 26.746, 'matrix' => '1.022, 0.121, -0.257, 1.033'],
        ['x' => 84.235, 'y' => 29.302, 'matrix' => '1.018, 0.238, -0.274, 1.071'],
        ['x' => 41.934, 'y' => 33.016, 'matrix' => '0.952, 0.091, -0.299, 1.009'],
        ['x' => 53.545, 'y' => 34.113, 'matrix' => '1.002, 0.18, -0.273, 1.06'],
        ['x' => 66.489, 'y' => 34.668, 'matrix' => '1.113, 0.197, -0.339, 1.232'],
        ['x' => 80.848, 'y' => 39.618, 'matrix' => '1.09, 0.163, -0.296, 1.062'],
    ];

    $signals = collect(range(0, 1))->flatMap(fn (int $index): array => array_map(fn (array $pattern): array => ['pattern' => $pattern['slug'], 'text' => $pattern['signals'][$index]], $patterns))->all();

    $patterns = array_map(fn (array $pattern, int $index): array => [...$pattern, 'number' => $index + 1], $patterns, array_keys($patterns));
@endphp
<x-marketing.layout title="Eight ways work gets stuck" active="tools" :schema="$schema" description="The work that keeps landing on your desk usually gets stuck in a handful of familiar ways. These are the eight patterns I look for, and use in my Walkthrough reports.">
    <section class="st-hero" aria-labelledby="stuck-heading">
        <div class="st-hero-copy">
            <h1 id="stuck-heading">Eight ways work<br> gets stuck. Most<br> businesses have a few.</h1>
            <p class="st-lead">Most of the work that ends up on your desk isn’t hard because it’s complicated. It’s hard in a handful of familiar ways.</p>
            <x-marketing.booking-link class="studio-button" placement="stuck-hero">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
        </div>
        <picture>
            <source type="image/webp" srcset="{{ asset('images/stuck/stuck-desk-1000.webp') }} 1000w, {{ asset('images/stuck/stuck-desk.webp') }} 2000w" sizes="(min-width: 1100px) 81vw, 140vw">
            <img class="st-desk" src="{{ asset('images/stuck/stuck-desk.png') }}" width="2000" height="1541" alt="" fetchpriority="high">
        </picture>
        <nav class="st-map" id="map" aria-label="The eight patterns">
            <ol role="list">
                @foreach ($patterns as $pattern)
                    <li @class(['st-card', 'is-lifted' => $pattern['number'] === 7]) style="--x: {{ $cards[$loop->index]['x'] }}vw; --y: {{ $cards[$loop->index]['y'] }}vw; --m: {{ $cards[$loop->index]['matrix'] }}">
                        <a href="#{{ $pattern['slug'] }}"><span class="st-number">{{ $pattern['number'] }}</span><span class="st-card-name">{{ $pattern['name'] }}</span></a>
                    </li>
                @endforeach
            </ol>
            <picture>
                <source type="image/webp" srcset="{{ asset('images/stuck/stuck-canary.webp') }}">
                <img class="st-canary" src="{{ asset('images/stuck/stuck-canary.png') }}" width="300" height="306" alt="">
            </picture>
        </nav>
    </section>

    <section class="st-intro studio-section-intro" aria-labelledby="stuck-intro-heading">
        <h2 id="stuck-intro-heading">The names I use in every report.</h2>
        <div>
            <p>After watching a lot of people walk me through their week, I’ve started using the same eight names for them. They’re the names I use in my Walkthrough reports, and they’re here so you can recognize them before we ever talk.</p>
            <p>A process usually has more than one, and that’s normal. None of these are about someone doing their job badly. They describe how the work is set up, not the people keeping it going. The scenes are composites, and the names are made up.</p>
        </div>
    </section>

    <section class="st-patterns" aria-label="The patterns, one to four">
        @foreach (array_slice($patterns, 0, 4) as $pattern)
            <x-marketing.stuck-pattern :pattern="$pattern" />
        @endforeach
    </section>

    <section class="st-interlude" aria-labelledby="interlude-heading">
        <h2 id="interlude-heading">Already seeing your week in here?</h2>
        <div>
            <p>You don’t need to read all eight first. Bring one example of the work to a free Walkthrough, and you and I will work out which of these it is.</p>
            <x-marketing.booking-link class="studio-button" placement="stuck-interlude">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
        </div>
    </section>

    <section class="st-patterns" aria-label="The patterns, five to eight">
        @foreach (array_slice($patterns, 4) as $pattern)
            <x-marketing.stuck-pattern :pattern="$pattern" />
        @endforeach
    </section>

    <section class="st-close" aria-labelledby="invitation-heading">
        <div class="st-close-copy">
            <h2 id="invitation-heading">Recognize a few?</h2>
            <p>Most processes that keep coming back to you have two or three of these at once. Knowing which to fix first is the hard part, and that’s what the Walkthrough is for.</p>
            <p>You spend about an hour walking me through the work. Within three business days you get a written report: what’s happening, what I’d recommend, and where I’d start. It’s free, and it’s yours to keep.</p>
        </div>
        <form class="st-check" data-self-check aria-labelledby="check-heading" onsubmit="return false">
            <div class="st-check-signs">
                <h3 id="check-heading">Tick what sounds like your week.</h3>
                <ul role="list">
                    @foreach ($signals as $signal)
                        <li><label><input type="checkbox" data-pattern="{{ $signal['pattern'] }}"> {{ $signal['text'] }}</label></li>
                    @endforeach
                </ul>
            </div>
            <div class="st-check-results">
                <h3>The patterns behind them</h3>
                <ol role="list" data-self-check-results>
                    @foreach ($patterns as $pattern)
                        <li data-pattern="{{ $pattern['slug'] }}" data-name="{{ $pattern['name'] }}" style="view-transition-name: sc-{{ $pattern['slug'] }}">
                            <span class="st-number" aria-hidden="true">{{ $pattern['number'] }}</span>
                            <a href="#{{ $pattern['slug'] }}">{{ $pattern['name'] }}</a>
                            <span class="st-dots" aria-hidden="true">@foreach (array_slice($pattern['signals'], 0, 2) as $signal)<span data-dot></span>@endforeach</span>
                        </li>
                    @endforeach
                </ol>
                <p class="st-check-summary" role="status" data-self-check-summary></p>
                <x-marketing.booking-link class="studio-button" placement="stuck-close">Bring these to a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
                <p class="studio-terms">A pitch-free hour. A written report within three business days. Yours to keep.</p>
            </div>
        </form>
    </section>
</x-marketing.layout>
