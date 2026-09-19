<?php

use function Laravel\Folio\name;

name('public.work');

?>
<x-marketing.layout title="Selected work" active="work" description="A closer look at my work with Craft & Communicate: a reporting platform built around the agency and its clients.">
    <header class="page-title work-title" id="craft-and-communicate"><h1>Craft &amp; Communicate</h1><p>A reporting platform that’s part of the agency’s service, not just the work behind it.</p></header>
    <article class="work-story section-space" aria-labelledby="reporting-problem">
        <div class="story-heading"><h2 id="reporting-problem">Getting the numbers was part of the work.</h2></div>
        <div class="story-body">
            <p>Craft &amp; Communicate’s reporting involved finding performance numbers by hand. I built a client-facing platform to bring that work together, with client management and data that updates live.</p>
            <p>The finished platform is also an offering the agency can sell to its customers. That gives the work a place in the agency’s service, beyond the process of assembling a report.</p>
        </div>
        <x-marketing.reporting-diagram />
        <h2 class="story-bottom-heading">Built around the business.</h2>
        <div class="story-bottom"><p>The reporting, the client management, and the customer-facing experience belong together. This is the kind of work I like: understanding how the pieces fit, then giving people a useful way to work with them.</p></div>
    </article>
    <x-marketing.assessment-invitation heading="What’s getting in the way of your work?" />
</x-marketing.layout>
