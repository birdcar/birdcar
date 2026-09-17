<?php

use function Laravel\Folio\name;

name('public.index');

?>
<x-marketing.layout :home="true">
    <section class="home-opening" aria-labelledby="home-heading">
        <img class="future-horizon" src="{{ asset('images/future-horizon.png') }}" alt="" width="2172" height="724" fetchpriority="high">
        <div class="home-hero">
            <h1 id="home-heading"><span>Make room</span><span>for better</span><span>work.</span></h1>
            <div class="hero-copy">
                <p class="hero-introduction">I help businesses untangle work that’s become harder to keep up with.</p>
                <p class="hero-explanation">I work with you and the people doing that work to understand what’s getting in the way, then build tools and processes that make it easier to handle.</p>
                <x-marketing.booking-link class="button button-lilac" placement="hero">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
                <p class="assessment-terms">About an hour. A free Walkthrough. A report to keep.</p>
            </div>
        </div>
    </section>
    <section class="home-work" aria-labelledby="work-heading">
        <h2 id="work-heading">Work in practice.</h2>
        <a class="work-preview" href="{{ route('public.work') }}#craft-and-communicate">
            <h3>Craft &amp; Communicate</h3>
            <p>I built a client-facing reporting platform that brings together performance data and gives the agency something it can offer its customers.</p>
            <span class="text-link">Read about the work <x-marketing.arrow /></span>
        </a>
    </section>
    <section class="assessment-strip" aria-labelledby="assessment-heading">
        <h2 id="assessment-heading">Let’s start with what’s painful.</h2>
        <div>
            <p>The reporting someone spends Friday assembling. The follow-up that depends on your memory. The process that keeps landing back on your desk.</p>
            <p>I’ll spend about an hour with you, then write up what I’ve understood and the improvements I recommend. The Walkthrough is free. The report is yours to keep.</p>
            <x-marketing.booking-link class="button button-ink" placement="homepage-strip">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
        </div>
    </section>
    <section class="approach-section section-space" id="how-i-work" aria-labelledby="approach-heading">
        <div class="approach-introduction">
            <h2 id="approach-heading">I start with the people<br class="desktop-break"> doing the work.</h2>
            <div>
                <p>A process can look perfectly reasonable until you ask someone to walk you through their Tuesday. Then you find the spreadsheet they keep open, the information they copy between tools, and the things they check because nobody quite trusts the system.</p>
                <p>That’s where I want to start. Before suggesting an improvement, I need to understand what people are already doing to keep things working.</p>
            </div>
        </div>
        <div class="approach-steps">
            <section id="understand"><h3>Understand</h3><p>I talk with you and your team, follow the work through the business, and look for where it gets difficult. Together, I want to establish what an improvement would actually mean for the people involved.</p></section>
            <section id="build"><h3>Build</h3><p>I agree on a manageable scope, build the improvement, and test it with the people who’ll use it. That might involve connecting existing tools, building something specific to your business, or changing how the work moves between people.</p></section>
            <section id="care"><h3>Care</h3><p>I leave you with a working system, documentation, and training. When continued help makes sense, I can stay involved as people learn the system and the business changes.</p></section>
        </div>
    </section>
    <section class="personal-note section-space" aria-labelledby="personal-heading">
        <h2 id="personal-heading">Complicated work.<br>A person to talk to.</h2>
        <div><p>I’ve spent fifteen years working on customer-facing technical systems, including internal tools, solutions engineering, and building a software business.</p><p>I like making complicated things understandable, especially when that understanding makes someone’s working day better.</p></div>
    </section>
    <x-marketing.assessment-invitation />
</x-marketing.layout>
