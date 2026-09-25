@props(['pattern'])
<article class="stuck-pattern" id="{{ $pattern['slug'] }}" aria-labelledby="{{ $pattern['slug'] }}-heading">
    <header>
        <span class="route-number" aria-hidden="true">{{ $pattern['number'] }}</span>
        <h2 id="{{ $pattern['slug'] }}-heading">{{ $pattern['name'] }}</h2>
        <p class="stuck-definition">{{ $pattern['definition'] }}</p>
    </header>
    <div class="stuck-detail">
        <p>{{ $pattern['scene'] }}</p>
        <h3>You’ll notice</h3>
        <ul>
            @foreach ($pattern['signals'] as $signal)
                <li>{{ $signal }}</li>
            @endforeach
        </ul>
        <h3>What usually helps</h3>
        <p>{{ $pattern['helps'] }}</p>
        <div class="stuck-links">
            @isset($pattern['link'])
                <a class="text-link" href="{{ $pattern['link']['href'] }}">{{ $pattern['link']['label'] }} <x-marketing.arrow /></a>
            @endisset
            <a class="text-link stuck-back" href="#map">All eight patterns <x-marketing.arrow /></a>
        </div>
    </div>
</article>
