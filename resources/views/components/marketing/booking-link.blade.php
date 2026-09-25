@props(['onWalkthrough' => false, 'placement' => null])
@php($marker = $placement ? ['data-booking-cta' => $placement] : [])
<a {{ $attributes->merge(['href' => $onWalkthrough ? '#choose-a-time' : route('public.walkthrough'), ...$marker]) }}>{{ $slot }}</a>
