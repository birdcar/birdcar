@props(['inline' => false, 'placement' => null])
@php($marker = $placement ? ['data-booking-cta' => $placement] : [])
@if ($inline)
    <a {{ $attributes->merge(['href' => '#choose-a-time', ...$marker]) }}>{{ $slot }}</a>
@else
    <a {{ $attributes->merge(['href' => route('public.assessment'), ...$marker]) }} data-cal-link="{{ config('marketing.booking_calendar') }}" data-cal-namespace="{{ config('marketing.booking_namespace') }}" data-cal-config="{{ json_encode(['layout' => 'month_view', 'useSlotsViewOnSmallScreen' => 'true', 'theme' => 'light']) }}">{{ $slot }}</a>
@endif
