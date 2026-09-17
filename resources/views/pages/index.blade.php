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
                <p class="hero-explanation">Your team shows me where the work gets stuck. I build the fix, so it stops coming back to you.</p>
                <x-marketing.booking-link class="button button-lilac" placement="hero">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
                <p class="assessment-terms">About an hour. A free Walkthrough. A written report within three business days.</p>
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
            <p>What does it cost when the only fix is another hire, another subscription, or another hour of your evening? And what would the week look like if the report assembled itself and the follow-up went out without you?</p>
            <p>About an hour with you, then a written report: what’s going wrong, what I’d change, and where I’d start. Free, within three business days, and yours to keep.</p>
            <x-marketing.booking-link class="button button-ink" placement="homepage-strip">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
        </div>
    </section>
    <section class="approach-section section-space" id="how-i-work" aria-labelledby="approach-heading">
        <div class="approach-introduction">
            <h2 id="approach-heading">Your team knows where it hurts.<br class="desktop-break"> I start there.</h2>
            <div>
                <p>A process can look perfectly reasonable until you ask someone to walk you through their Tuesday. Then you find the spreadsheet they keep open, the information they copy between tools, and the things they check because nobody quite trusts the system.</p>
                <p>That’s where the Walkthrough starts. Before suggesting an improvement, I need to understand what your people are already doing to keep things working.</p>
            </div>
        </div>
        <div class="approach-steps">
            <section id="understand"><h3>Understand</h3><p>You and your team walk me through the work. I follow it through the business and look for where it gets difficult. Then you and I decide what better means: for you, and for the people doing it.</p></section>
            <section id="build"><h3>Build</h3><p>You and I agree on a manageable scope. I build the improvement and test it with the people who’ll use it. That might mean connecting the tools you already have, building something specific to your business, or changing how the work moves between people.</p></section>
            <section id="care"><h3>Care</h3><p>You get a working system, documentation, and training. When continued help makes sense, I stay involved as your people learn the system and the business changes.</p></section>
        </div>
    </section>
    <section class="personal-note section-space" aria-labelledby="personal-heading">
        <h2 id="personal-heading">Complicated work.<br>A person to talk to.</h2>
        <div><p>I’ve spent fifteen years working on customer-facing technical systems, including internal tools, solutions engineering, and building a software business. Some of that work was for GitHub, Heroku, and Zapier.</p><p>I like making complicated things understandable, especially when that understanding makes someone’s working day better.</p></div>
    </section>
    <x-marketing.assessment-invitation />
</x-marketing.layout>
