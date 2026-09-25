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

    $rows = [72, 224, 376, 528];
    $routes = collect($patterns)->map(function (array $pattern, int $index) use ($rows): array {
        $right = $index >= 4;
        $row = $index % 4;
        $y = $rows[$row];
        $entry = 252 + $row * 32;
        $desk = $right ? 360 : 240;
        $edge = $right ? 418 : 182;
        $pull = $right ? 40 : -40;
        $tip = $right ? 1 : -1;

        return [
            ...$pattern,
            'number' => $index + 1,
            'side' => $right ? 'right' : 'left',
            'y' => round($y / 6, 3),
            'path' => sprintf('M%d %dC%d %d %d %d %d %d', $edge, $y, $edge - $pull, $y, $desk + $pull, $entry, $desk + $tip, $entry),
            'arrow' => sprintf('M%d %dL%d %dL%d %d', $desk + 9 * $tip, $entry - 5, $desk + $tip, $entry, $desk + 9 * $tip, $entry + 5),
        ];
    });
@endphp
<x-marketing.layout title="Eight ways work gets stuck" active="tools" :schema="$schema" description="The work that keeps landing on your desk usually gets stuck in a handful of familiar ways. These are the eight patterns I look for, and use in my Walkthrough reports.">
    <section class="stuck-opening" aria-labelledby="stuck-heading">
        <div class="stuck-copy">
            <h1 id="stuck-heading">Eight ways work gets stuck.<br>Most businesses have a few.</h1>
            <p class="stuck-lead">Most of the work that ends up on your desk isn’t hard because it’s complicated. It’s hard in a handful of familiar ways.</p>
            <p>After watching a lot of people walk me through their week, I’ve started using the same eight names for them. They’re the names I use in my Walkthrough reports, and they’re here so you can recognize them before we ever talk.</p>
            <x-marketing.booking-link class="button button-yellow" placement="stuck-hero">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
            <p class="action-note">A pitch-free hour. A written report within three business days. Yours to keep.</p>
        </div>
        <nav class="stuck-map" id="map" aria-label="The eight patterns">
            <svg class="stuck-routes-drawing" viewBox="0 0 600 600" aria-hidden="true" focusable="false">
                @foreach ($routes as $route)
                    <g class="stuck-route" data-route="{{ $route['number'] }}"><path d="{{ $route['path'] }}" /><path d="{{ $route['arrow'] }}" /></g>
                @endforeach
            </svg>
            <p class="stuck-desk"><span>Your desk</span></p>
            <ol class="stuck-routes" role="list">
                @foreach ($routes as $route)
                    <li class="stuck-route-link is-{{ $route['side'] }}" data-route="{{ $route['number'] }}" style="--route-y: {{ $route['y'] }}%">
                        <a href="#{{ $route['slug'] }}"><span class="route-number">{{ $route['number'] }}</span><span class="route-name">{{ $route['name'] }}</span></a>
                    </li>
                @endforeach
            </ol>
        </nav>
    </section>
    <section class="stuck-patterns" aria-label="The patterns, one to four">
        <p class="stuck-overlap">A process usually has more than one, and that’s normal. None of these are about someone doing their job badly. They describe how the work is set up, not the people keeping it going. The scenes are composites, and the names are made up.</p>
        @foreach ($routes->take(4) as $route)
            <x-marketing.stuck-pattern :pattern="$route" />
        @endforeach
    </section>
    <section class="stuck-interlude" aria-labelledby="interlude-heading">
        <h2 id="interlude-heading">Already seeing your week in here?</h2>
        <div>
            <p>You don’t need to read all eight first. Bring one example of the work to a free Walkthrough, and you and I will work out which of these it is.</p>
            <x-marketing.booking-link class="button button-yellow" placement="stuck-interlude">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
        </div>
    </section>
    <section class="stuck-patterns" aria-label="The patterns, five to eight">
        @foreach ($routes->slice(4) as $route)
            <x-marketing.stuck-pattern :pattern="$route" />
        @endforeach
    </section>
    <section class="closing-invitation" aria-labelledby="invitation-heading">
        <h2 id="invitation-heading">Recognize a few?</h2>
        <div>
            <p>Most processes that keep coming back to you have two or three of these at once. Knowing which to fix first is the hard part, and that’s what the Walkthrough is for.</p>
            <p>You spend about an hour walking me through the work. Within three business days you get a written report: what’s happening, what I’d recommend, and where I’d start. It’s free, and it’s yours to keep.</p>
            <x-marketing.booking-link class="button button-yellow" placement="stuck-close">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
        </div>
    </section>
</x-marketing.layout>
