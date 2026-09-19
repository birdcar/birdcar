<div class="article-diagram">
    @if ($name === 'walkthrough')
        <x-marketing.walkthrough-diagram :caption="$caption" />
    @elseif ($name === 'reporting')
        <x-marketing.reporting-diagram :caption="$caption" />
    @endif
</div>
