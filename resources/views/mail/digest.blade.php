<x-mail::message>
# {{ $subjectLine }}

{{ $intro }}

@foreach ($sections as $label => $items)
## {{ $label }}

@foreach ($items as $item)
- **@if ($item['url'])[{{ str_replace(['[', ']'], ['(', ')'], $item['title']) }}]({{ $item['url'] }})@else{{ $item['title'] }}@endif** — {{ $item['body'] }} <br><small>{{ $item['at'] }}</small>
@endforeach

@endforeach
<x-mail::button :url="$actionUrl">
Open Executive Desk
</x-mail::button>

High priority alerts are still sent to you straight away. You can change what goes into this digest in your notification settings.

Regards,<br>
{{ config('app.name') }}
</x-mail::message>
