@php
    $checks = [
        ['key' => 'team', 'line' => 'You have customers and people doing the work.', 'note' => 'Then there may be less for me to look at yet. You can still book, and I’ll tell you honestly if it’s early.'],
        ['key' => 'process', 'line' => 'One process takes too much chasing, copying, or remembering.', 'note' => 'Then you may not need me yet. You can still book if something keeps bugging you.'],
        ['key' => 'people', 'line' => 'I can talk to the people who do it.', 'stops' => true],
    ];
@endphp
<div {{ $attributes->class('fit-card') }} id="choose-a-time" data-fit-card>
    <div class="fit-check" role="group" aria-labelledby="fit-heading">
        <h2 id="fit-heading">Before you pick a time</h2>
        <p class="fit-sub">Three quick checks. It takes ten seconds.</p>
        @foreach ($checks as $check)
            <div class="fit-row" role="radiogroup" aria-labelledby="fit-{{ $check['key'] }}" data-fit-row @if ($check['stops'] ?? false) data-fit-stops @endif>
                <p class="fit-line" id="fit-{{ $check['key'] }}">{{ $check['line'] }}</p>
                <div class="fit-pills">
                    <label><input type="radio" name="fit-{{ $check['key'] }}" value="yes"> Yes</label>
                    <label><input type="radio" name="fit-{{ $check['key'] }}" value="not-yet"> Not yet</label>
                </div>
                @isset($check['note'])
                    <p class="fit-row-note">{{ $check['note'] }}</p>
                @endisset
            </div>
        @endforeach
        <a class="fit-action" href="#choose-a-time" data-booking-cta="walkthrough-hero" data-fit-action>Show me times <x-marketing.arrow /></a>
        <div class="fit-stop">
            <p>Then I’m not the right fit, and I’d rather say so now. The eight ways work gets stuck might still help you name what’s going on.</p>
            <a class="studio-link" href="{{ route('public.where-work-gets-stuck') }}">Eight ways work gets stuck <x-marketing.arrow /></a>
        </div>
        <p class="fit-status" role="status" data-fit-status></p>
        <p class="fit-note">If I can’t talk to the people doing the work, I’m not the right fit.</p>
    </div>
    <div class="fit-calendar" tabindex="-1" aria-labelledby="calendar-heading" data-fit-calendar>
        <h2 id="calendar-heading">Pick a time</h2>
        <p class="fit-sub">About an hour, on a video call unless you’d rather meet in person.</p>
        <div class="booking-calendar" data-cal-inline data-cal-link="{{ config('marketing.booking_calendar') }}" data-cal-namespace="{{ config('marketing.booking_namespace') }}" data-cal-defer></div>
        <p class="fit-fallback">If the calendar doesn’t load, <a href="{{ config('marketing.booking_url') }}" data-booking-fallback>book on Cal.com</a>.</p>
        <button class="fit-change" type="button" data-fit-change>Change my answers</button>
    </div>
</div>
