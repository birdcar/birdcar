<?php

use function Laravel\Folio\name;

name('public.work');

$rooms = [
    [
        'plate' => 'room-hand', 'image' => 'images/work/room-hand.png', 'width' => 1400, 'height' => 1080, 'tag' => 'Finding the numbers by hand', 'tagAt' => [72.6, 23.7],
        'route' => [[31, 46], [55, 84]],
        'alt' => 'A cutaway model of an agency office: one person at a desk sorts tall stacks of paper reports and sticky notes by hand.',
    ],
    [
        'plate' => 'room-platform', 'image' => 'images/work/room-platform.png', 'width' => 1400, 'height' => 958, 'tag' => 'A client-facing reporting platform', 'tagAt' => [64.5, 17],
        'route' => [[26, 52], [53, 64], [63, 86]],
        'alt' => 'The same agency, calmer: one person works at a monitor showing live charts from a reporting platform, with a canary on the credenza.',
    ],
    [
        'plate' => 'room-clients', 'image' => 'images/work/room-clients.png', 'width' => 1400, 'height' => 995, 'tag' => 'Part of the agency’s service', 'tagAt' => [58, 12],
        'route' => [[30, 58], [55, 70]],
        'alt' => 'A client’s meeting room: two people review the same live charts on a wall screen and a laptop.',
    ],
];

?>
<x-marketing.layout title="Selected work" active="work" description="A closer look at my work with Craft & Communicate: a reporting platform built around the agency and its clients.">
    <div class="wk-journey" data-journey>
        <svg class="wk-route" data-journey-route aria-hidden="true" focusable="false">
            <path d="" />
            <g class="wk-packet" data-journey-packet>
                <path d="M0 -13 11.3 -6.5 0 0 -11.3 -6.5Z" fill="#fff1ad" />
                <path d="M-11.3 -6.5 0 0V13L-11.3 6.5Z" fill="#f7c948" />
                <path d="M0 0 11.3 -6.5V6.5L0 13Z" fill="#dca21a" />
            </g>
        </svg>

        <header class="wk-title" id="craft-and-communicate">
            <h1>Craft &amp;<br> Communicate</h1>
            <p class="wk-lead">A reporting platform that’s part of the agency’s service, not just the work behind it.</p>
            <p class="wk-note">Illustrated. Not a product screenshot or measured results.</p>
        </header>

        @foreach ($rooms as $room)
            <figure @class(['wk-room', 'wk-'.$room['plate']]) data-route-room>
                <picture>
                    <source type="image/webp" srcset="{{ asset('images/work/'.$room['plate'].'-760.webp') }} 760w, {{ asset('images/work/'.$room['plate'].'.webp') }} 1400w" sizes="(min-width: 1100px) 48vw, 100vw">
                    <img src="{{ asset($room['image']) }}" width="{{ $room['width'] }}" height="{{ $room['height'] }}" alt="{{ $room['alt'] }}" @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif>
                </picture>
                @foreach ($room['route'] as [$x, $y])
                    <span class="wk-anchor" style="--x: {{ $x }}%; --y: {{ $y }}%" data-route-anchor></span>
                @endforeach
                <figcaption class="wk-tag" style="--x: {{ $room['tagAt'][0] }}%; --y: {{ $room['tagAt'][1] }}%">{{ $room['tag'] }}</figcaption>
            </figure>

            @if ($loop->first)
                <section class="wk-story" aria-labelledby="reporting-problem">
                    <h2 id="reporting-problem">Getting the numbers was part of the work.</h2>
                    <p>Craft &amp; Communicate’s reporting involved finding performance numbers by hand. I built a client-facing platform to bring that work together, with client management and data that updates live.</p>
                </section>
            @elseif ($loop->index === 1)
                <section class="wk-story" aria-labelledby="reporting-offer">
                    <h2 id="reporting-offer">Now their clients see it too.</h2>
                    <p>The finished platform is also an offering the agency can sell to its customers. That gives the work a place in the agency’s service, beyond the process of assembling a report.</p>
                </section>
            @else
                <section class="wk-story wk-story-last" aria-labelledby="reporting-business">
                    <h2 id="reporting-business">Built around the business.</h2>
                    <p>The reporting, the client management, and the customer-facing experience belong together. This is the kind of work I like: understanding how the pieces fit, then giving people a useful way to work with them.</p>
                    <p class="wk-caption">Craft &amp; Communicate’s reporting, illustrated. This shows how the work fits together, not a product screenshot or measured results.</p>
                </section>
            @endif
        @endforeach
    </div>

    <x-marketing.assessment-invitation heading="What’s getting in the way of your work?" />
</x-marketing.layout>
