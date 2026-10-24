<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ ucfirst($briefing->type->value) }} briefing</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { font-size: 20px; margin: 0 0 4px; color: #172554; }
        h2 { font-size: 13px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #cbd5e1; color: #172554; }
        .muted { color: #64748b; }
        .summary td { padding: 6px 10px; border: 1px solid #e2e8f0; text-align: center; }
        .summary strong { font-size: 16px; display: block; }
        ul { margin: 0; padding-left: 16px; }
        li { margin-bottom: 6px; }
        .tag { font-size: 9px; padding: 1px 4px; border-radius: 3px; background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <h1>{{ $briefing->type->value === 'daily' ? 'Daily' : 'Weekly' }} briefing — {{ $briefing->user->name }}</h1>
    <p class="muted">
        {{ $briefing->period_start->equalTo($briefing->period_end) ? $briefing->period_start->format('l, F j, Y') : $briefing->period_start->format('M j').' – '.$briefing->period_end->format('M j, Y') }}
        · times in {{ $briefing->timezone }} · generated {{ $briefing->generated_at->setTimezone($briefing->timezone)->format('M j, Y g:i A T') }}
    </p>
    <p>{{ $briefing->content['headline'] ?? '' }}</p>

    @if (! empty($briefing->content['summary']))
        <table class="summary" cellspacing="0"><tr>
            @foreach ($briefing->content['summary'] as $key => $value)
                <td><strong>{{ $value }}</strong>{{ ucwords(str_replace('_', ' ', $key)) }}</td>
            @endforeach
        </tr></table>
    @endif

    @foreach ($briefing->content['sections'] ?? [] as $section)
        <h2>{{ $section['title'] }}</h2>
        @if (count($section['items']) === 0)
            <p class="muted">{{ $section['empty'] }}</p>
        @else
            <ul>
                @foreach ($section['items'] as $item)
                    <li>
                        <strong>{{ $item['title'] }}</strong>
                        @if (! empty($item['priority']) && in_array($item['priority'], ['high', 'urgent'], true))
                            <span class="tag">{{ ucfirst($item['priority']) }}</span>
                        @endif
                        @if (! empty($item['starts_at'])) <br><span class="muted">{{ $format($item['starts_at']) }}@if (! empty($item['ends_at'])) – {{ $format($item['ends_at'], 'g:i A') }}@endif</span> @endif
                        @if (! empty($item['due_at'])) <br><span class="muted">Due {{ $format($item['due_at']) }}</span> @endif
                        @if (! empty($item['deadline']) && empty($item['starts_at'])) <br><span class="muted">Deadline {{ $format($item['deadline']) }}</span> @endif
                        @if (! empty($item['location'])) <br><span class="muted">Location: {{ $item['location'] }}</span> @endif
                        @if (! empty($item['agenda'])) <br><span class="muted">Agenda: {{ $item['agenda'] }}</span> @endif
                        @if (! empty($item['detail'])) <br><span class="muted">{{ $item['detail'] }}</span> @endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endforeach
</body>
</html>
