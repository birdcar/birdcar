@php
    $key = $chart['series'][0]['key'];
    $label = $chart['series'][0]['label'];
    $maximum = max(array_column($chart['data'], $key));
    $count = count($chart['data']);
@endphp
<figure class="article-chart">
    @if ($type === 'bar')
        <div class="bar-chart" role="img" aria-label="{{ $caption }} Values are in the data table below.">
            @foreach ($chart['data'] as $row)
                <div class="bar-row"><span>{{ $row[$chart['x']] }}</span><div class="bar-track"><span style="width: {{ $row[$key] / $maximum * 100 }}%"></span></div><strong>{{ $row[$key] }}</strong></div>
            @endforeach
        </div>
    @else
        <svg class="line-chart" viewBox="0 0 640 250" role="img" aria-label="{{ $caption }} Values are in the data table below.">
            @foreach ([0, 400, 800] as $tick)
                <line x1="42" y1="{{ 214 - $tick / 800 * 178 }}" x2="598" y2="{{ 214 - $tick / 800 * 178 }}" class="chart-grid" />
                <text x="30" y="{{ 219 - $tick / 800 * 178 }}" text-anchor="end" class="chart-axis">{{ $tick }}</text>
            @endforeach
            <polyline points="@foreach ($chart['data'] as $index => $row){{ 60 + $index * 520 / ($count - 1) }},{{ 214 - $row[$key] / 800 * 178 }} @endforeach" class="chart-line" />
            @foreach ($chart['data'] as $index => $row)
                <circle cx="{{ 60 + $index * 520 / ($count - 1) }}" cy="{{ 214 - $row[$key] / 800 * 178 }}" r="5" class="chart-point" />
                <text x="{{ 60 + $index * 520 / ($count - 1) }}" y="{{ 200 - $row[$key] / 800 * 178 }}" text-anchor="middle" class="chart-value">{{ $row[$key] }}</text>
                <text x="{{ 60 + $index * 520 / ($count - 1) }}" y="244" text-anchor="middle" class="chart-axis">{{ $row[$chart['x']] }}</text>
            @endforeach
        </svg>
    @endif
    <figcaption>{{ $caption }}</figcaption>
    <details class="chart-data"><summary>View chart data</summary><table><caption>{{ $caption }}</caption><thead><tr><th scope="col">{{ ucfirst($chart['x']) }}</th><th scope="col">{{ ucfirst($label) }}</th></tr></thead><tbody>@foreach ($chart['data'] as $row)<tr><th scope="row">{{ $row[$chart['x']] }}</th><td>{{ $row[$key] }}</td></tr>@endforeach</tbody></table></details>
</figure>
