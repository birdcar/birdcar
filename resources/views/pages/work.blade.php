<?php

use function Laravel\Folio\name;

name('public.work');

?>
<x-marketing.layout title="Selected work" active="work" description="A closer look at my work with Craft & Communicate: a reporting platform built around the agency and its clients.">
    <header class="page-title work-title"><h1>Selected work.</h1><p>The useful part of a project is what people can do with it.</p></header>
    <article class="work-story section-space" id="craft-and-communicate">
        <div class="story-heading"><h2>Craft &amp;<br>Communicate</h2><p>A reporting platform that’s part of the service.</p></div>
        <div class="story-body">
            <h3>Getting the numbers was part of the work.</h3>
            <p>Craft &amp; Communicate’s reporting involved finding performance numbers by hand. I built a client-facing platform to bring that work together, with client management and data that updates live.</p>
            <p>The finished platform is also an offering the agency can sell to its customers. That gives the work a place in the agency’s service, beyond the process of assembling a report.</p>
        </div>
        <figure class="reporting-diagram">
            <div class="reporting-flow">
                <div class="flow-source"><span>Performance<br>data</span><span>Client<br>management</span></div>
                <x-marketing.arrow class="flow-arrow" />
                <div class="flow-platform"><span>One place<br>for reporting.</span><small>Live-updating data</small></div>
                <x-marketing.arrow class="flow-arrow" />
                <div class="flow-client">A service<br>for clients.</div>
            </div>
            <figcaption>A view of how the work fits together, illustrated.</figcaption>
        </figure>
        <h3 class="story-bottom-heading">Built around the business.</h3>
        <div class="story-bottom"><p>The reporting, the client management, and the customer-facing experience belong together. This is the kind of work I like: understanding how the pieces fit, then giving people a useful way to work with them.</p></div>
    </article>
    <x-marketing.assessment-invitation heading="What’s getting in the way of your work?" />
</x-marketing.layout>
