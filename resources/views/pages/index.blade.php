<?php

use function Laravel\Folio\name;

name('public.index');

?>
<x-marketing.layout :home="true">
    <section class="home-opening" aria-labelledby="home-heading">
        <div class="home-hero">
            <h1 id="home-heading">Why does everything<br class="desktop-break"> come back to you?</h1>
            <div class="hero-copy">
                <div>
                    <p class="hero-introduction">I help businesses untangle work that’s become harder to keep up with.</p>
                    <p class="hero-explanation">Your team shows me where the work gets stuck. I build the fix, so it stops coming back to you.</p>
                </div>
                <div class="hero-action">
                    <x-marketing.booking-link class="button button-yellow" placement="hero">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
                    <p class="assessment-terms">A pitch-free hour. A written report within three business days. Yours to keep.</p>
                </div>
            </div>
        </div>
    </section>
    <section class="home-walkthrough" aria-labelledby="walkthrough-heading">
        <div class="walkthrough-introduction">
            <h2 id="walkthrough-heading">Start with the work.<br>Leave with a way forward.</h2>
            <p>You don’t need a software brief. Just a process that’s harder than it should be. Here’s how the free Walkthrough turns that conversation into something you can use.</p>
        </div>
        <x-marketing.walkthrough-diagram />
    </section>
    <section class="assessment-strip" aria-labelledby="assessment-heading">
        <h2 id="assessment-heading">Which part of the week would you change?</h2>
        <div>
            <p>The reporting someone spends Friday assembling. The follow-up that depends on your memory. The process that keeps landing back on your desk.</p>
            <p>Before another hire, another subscription, or another hour of your evening, let’s look at what’s making that work difficult.</p>
            <x-marketing.booking-link class="button button-ink" placement="homepage-strip">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
            <a class="text-link offer-detail-link" href="{{ route('public.walkthrough') }}">What happens in a Walkthrough? <x-marketing.arrow /></a>
        </div>
    </section>
    <section class="home-work" aria-labelledby="work-heading">
        <h2 id="work-heading">A report is a start.<br>I also build the change.</h2>
        <div class="work-preview">
            <h3>Craft &amp; Communicate</h3>
            <p>Finding performance numbers by hand was part of the agency’s reporting work. I built a client-facing reporting platform with client management and live-updating data: something the agency can offer its customers.</p>
            <a class="text-link" href="{{ route('public.work') }}#craft-and-communicate">Read about the work <x-marketing.arrow /></a>
        </div>
        <x-marketing.reporting-diagram />
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
            <section id="build"><h3>Build</h3><p>Implementation is a separate purchase, if you want my help. You and I agree on a manageable scope. I build the improvement and test it with the people who’ll use it. That might mean connecting the tools you already have, building something specific to your business, or changing how the work moves between people.</p></section>
            <section id="care"><h3>Care</h3><p>You get a working system, documentation, and training. When continued help makes sense, I stay involved as your people learn the system and the business changes.</p></section>
        </div>
    </section>
    <section class="personal-note section-space" aria-labelledby="personal-heading">
        <h2 id="personal-heading">Complicated work.<br>A person to talk to.</h2>
        <div><p>I’ve spent fifteen years working on customer-facing technical systems, including internal tools, solutions engineering, and building a software business. Some of that work was for GitHub, Heroku, and Zapier.</p><p>I like making complicated things understandable, especially when that understanding makes someone’s working day better.</p></div>
    </section>
    <x-marketing.assessment-invitation />
</x-marketing.layout>
