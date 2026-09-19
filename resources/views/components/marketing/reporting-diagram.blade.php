@props(['caption' => null])
<figure {{ $attributes->class('reporting-diagram') }}>
    <div class="reporting-comparison">
        <div class="reporting-before">
            <h3>Finding the numbers by hand</h3>
            <p>Performance numbers had to be gathered for reporting.</p>
            <svg viewBox="0 0 280 110" aria-hidden="true" focusable="false">
                <path d="M12 8h60v54H12z M25 24h34 M25 35h24 M110 8h60v54h-60z M123 24h34 M123 35h24 M208 8h60v54h-60z M221 24h34 M221 35h24" />
                <path d="M42 70v18h98 M238 70v18h-98 M140 70v34m-6-6 6 6 6-6" />
            </svg>
            <p class="reporting-relationship">The work I brought together <x-marketing.arrow /></p>
        </div>
        <div class="reporting-platform">
            <h3>A client-facing reporting platform</h3>
            <dl>
                <div><dt>Performance data</dt><dd>Live-updating data for reporting.</dd></div>
                <div><dt>Client management</dt><dd>The agency’s clients managed alongside their reporting.</dd></div>
            </dl>
            <div class="reporting-result">
                <x-marketing.arrow />
                <p><strong>Part of the agency’s service</strong><span>An offering Craft &amp; Communicate can sell to its customers.</span></p>
            </div>
        </div>
    </div>
    <figcaption>@if ($caption)<strong class="diagram-caption">{{ $caption }}</strong>@endif Craft &amp; Communicate’s reporting, illustrated. This shows how the work fits together, not a product screenshot or measured results.</figcaption>
</figure>
