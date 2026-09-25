@props(['priority' => false, 'alt' => 'A cutaway model of a small service business: a van bay, an open office, a front desk, and a glass office at the back where the owner sits behind a tall stack of paper, under a red warning light, beside a caged canary.'])
<picture>
    <source
        type="image/webp"
        srcset="{{ asset('images/home/diorama-960.webp') }} 960w, {{ asset('images/home/diorama-1400.webp') }} 1400w, {{ asset('images/home/diorama-1920.webp') }} 1920w"
        sizes="(min-width: 1100px) 58vw, 100vw"
    >
    <img
        class="miniature-plate"
        src="{{ asset('images/home/diorama.png') }}"
        width="1400"
        height="1400"
        @if ($priority) fetchpriority="high" @else loading="lazy" @endif
        alt="{{ $alt }}"
    >
</picture>
