@props(['heading' => 'Show me the part that keeps getting stuck.'])
<section class="closing-invitation" aria-labelledby="invitation-heading">
    <h2 id="invitation-heading">{{ $heading }}</h2>
    <div>
        <p>You don’t need to arrive with a software specification. An example of something that’s harder than it ought to be is a useful place to start.</p>
        <x-marketing.booking-link class="button button-paper" placement="closing-invitation">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
        <p class="action-note">About an hour. A practical report. Yours to keep.</p>
    </div>
</section>
