@props(['heading' => 'Show me the part that keeps getting stuck.'])
<section class="closing-invitation" aria-labelledby="invitation-heading">
    <h2 id="invitation-heading">{{ $heading }}</h2>
    <div>
        <p>You don’t need to arrive with a software specification. An example of something that’s harder than it ought to be is a useful place to start.</p>
        <a class="button button-cream" href="{{ route('public.assessment') }}">Book a free assessment <x-marketing.arrow /></a>
        <p class="action-note">About an hour. A practical report. Yours to keep.</p>
    </div>
</section>
