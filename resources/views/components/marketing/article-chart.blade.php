@php
    $key = $chart['series'][0]['key'];
    $label = $chart['series'][0]['label'];
    $values = array_map(static fn ($row) => (float) $row[$key], $chart['data']);
    $maximum = $values === [] ? 0.0 : max($values);
    $barMaximum = max($maximum, 1.0);
    $count = count($chart['data']);
    $roughStep = max($maximum / 2, 0.5);
    $power = 10 ** floor(log10($roughStep));
    $step = $power;

    foreach ([1, 2, 4, 5, 10] as $multiplier) {
        if ($roughStep <= $multiplier * $power) {
            $step = $multiplier * $power;
            break;
        }
    }

    $lineMaximum = max($step * 2, 1.0);
    $ticks = [0, $step, $lineMaximum];
    $formatTick = static fn (float $value): string => fmod($value, 1.0) === 0.0 ? (string) (int) $value : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    $xPosition = static fn (int $index): float => $count <= 1 ? 320.0 : 60 + $index * 520 / ($count - 1);
@endphp
<figure class="article-chart">
    @if ($type === 'bar')
        <div class="bar-chart" role="img" aria-label="{{ $caption }} Values are in the data table below.">
            @foreach ($chart['data'] as $row)
                <div class="bar-row"><span>{{ $row[$chart['x']] }}</span><div class="bar-track"><span style="width: {{ $row[$key] / $barMaximum * 100 }}%"></span></div><strong>{{ $row[$key] }}</strong></div>
            @endforeach
        </div>
    @else
        <p class="chart-scroll-hint">Scroll for the full chart, or view the data below.</p>
        <div class="chart-scroll" tabindex="0" role="region" aria-label="{{ $label }} by {{ $chart['x'] }}. Scroll to see the whole chart.">
        <svg class="line-chart" viewBox="0 0 640 250" role="img" aria-label="{{ $caption }} Values are in the data table below.">
            @foreach ($ticks as $tick)
                <line x1="42" y1="{{ 214 - $tick / $lineMaximum * 178 }}" x2="598" y2="{{ 214 - $tick / $lineMaximum * 178 }}" class="chart-grid" />
                <text x="30" y="{{ 219 - $tick / $lineMaximum * 178 }}" text-anchor="end" class="chart-axis">{{ $formatTick($tick) }}</text>
            @endforeach
            <polyline points="@foreach ($chart['data'] as $index => $row){{ $xPosition($index) }},{{ 214 - $row[$key] / $lineMaximum * 178 }} @endforeach" class="chart-line" />
            @foreach ($chart['data'] as $index => $row)
                <circle cx="{{ $xPosition($index) }}" cy="{{ 214 - $row[$key] / $lineMaximum * 178 }}" r="5" class="chart-point" />
                <text x="{{ $xPosition($index) }}" y="{{ 200 - $row[$key] / $lineMaximum * 178 }}" text-anchor="middle" class="chart-value">{{ $row[$key] }}</text>
                <text x="{{ $xPosition($index) }}" y="244" text-anchor="middle" class="chart-axis">{{ $row[$chart['x']] }}</text>
            @endforeach
        </svg>
        </div>
    @endif
    <figcaption>{{ $caption }}</figcaption>
    <details class="chart-data"><summary>View chart data</summary><table><caption>{{ $caption }}</caption><thead><tr><th scope="col">{{ ucfirst($chart['x']) }}</th><th scope="col">{{ ucfirst($label) }}</th></tr></thead><tbody>@foreach ($chart['data'] as $row)<tr><th scope="row">{{ $row[$chart['x']] }}</th><td>{{ $row[$key] }}</td></tr>@endforeach</tbody></table></details>
</figure>
