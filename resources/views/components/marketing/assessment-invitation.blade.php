@props(['heading' => 'Show me the part that keeps getting stuck.'])
<section class="closing-invitation" aria-labelledby="invitation-heading">
    <h2 id="invitation-heading">{{ $heading }}</h2>
    <div>
        <p>You don’t need to arrive with a software specification. Bring one example of work that takes more than it should. The hour is about your work, not a pitch.</p>
        <x-marketing.booking-link class="button button-paper" placement="closing-invitation">Book a free Walkthrough <x-marketing.arrow /></x-marketing.booking-link>
        <p class="action-note">About an hour. A written report within three business days. Yours to keep.</p>
    </div>
</section>
