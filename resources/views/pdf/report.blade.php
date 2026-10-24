<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #0f172a; }
        h1 { font-size: 16px; margin: 0 0 2px; color: #172554; }
        .muted { color: #64748b; }
        .summary { margin: 10px 0; }
        .summary span { display: inline-block; margin-right: 16px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #172554; color: #fff; text-align: left; padding: 4px; }
        table.data td { border-bottom: 1px solid #e2e8f0; padding: 4px; vertical-align: top; }
    </style>
</head>
<body>
    <h1>{{ $report['title'] }}</h1>
    <p class="muted">{{ $period }} · times in {{ $timezone }} · generated {{ now()->setTimezone($timezone)->format('M j, Y g:i A T') }}</p>
    <div class="summary">
        @foreach ($report['summary'] as $label => $value)
            <span><strong>{{ $value }}</strong> {{ $label }}</span>
        @endforeach
    </div>
    <table class="data">
        <thead><tr>@foreach ($report['columns'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>@foreach (array_keys($report['columns']) as $key)<td>{{ $row[$key] ?? '' }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}" class="muted">No records in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
