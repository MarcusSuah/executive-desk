<x-mail::message>
# {{ $label }}

**{{ $period }}**

{{ $headline }}

@foreach ($sections as $section)
## {{ $section['title'] }}

@if (count($section['items']) === 0)
_{{ $section['empty'] }}_

@else
@foreach ($section['items'] as $item)
- **{{ $item['title'] }}**@if (! empty($item['starts_at'])) — {{ $format($item['starts_at']) }}@if (! empty($item['ends_at'])) to {{ $format($item['ends_at'], 'g:i A') }}@endif @endif @if (! empty($item['due_at'])) — due {{ $format($item['due_at']) }}@endif @if (! empty($item['deadline']) && empty($item['starts_at'])) — deadline {{ $format($item['deadline']) }}@endif @if (! empty($item['priority']) && in_array($item['priority'], ['high', 'urgent'], true)) ({{ ucfirst($item['priority']) }})@endif

@if (! empty($item['location']))
  Location: {{ $item['location'] }}
@endif
@if (! empty($item['meeting_url']))
  Join: {{ $item['meeting_url'] }}
@endif
@if (! empty($item['agenda']))
  Agenda: {{ $item['agenda'] }}
@endif
@if (! empty($item['detail']))
  {{ $item['detail'] }}
@endif
@endforeach

@endif
@endforeach

<x-mail::button :url="$url">
Open the briefing
</x-mail::button>

Times are shown in {{ $timezone }}.

Regards,<br>
{{ config('app.name') }}
</x-mail::message>
