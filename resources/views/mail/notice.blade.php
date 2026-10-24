<x-mail::message>
# {{ $heading }}

{{ $intro }}

@if (count($details) > 0)
<x-mail::table>
| | |
|:--|:--|
@foreach ($details as $label => $value)
| **{{ $label }}** | {{ str_replace(['|', "\n"], ['/', ' '], $value) }} |
@endforeach
</x-mail::table>
@endif

@if ($body)
@if ($bodyTitle)
**{{ $bodyTitle }}**

@endif
<x-mail::panel>
{{ $body }}
</x-mail::panel>
@endif

@if ($actionUrl)
<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>
@endif

Times are shown in your configured time zone. You can change which reminders you receive in your notification settings.

Regards,<br>
{{ config('app.name') }}
</x-mail::message>
