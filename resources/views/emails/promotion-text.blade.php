@if ($promoted)
Congratulations, {{ $firstName }}! You have been promoted to {{ $designation }}.
@else
Good news, {{ $firstName }}: your salary has been revised.
@endif

@foreach ($facts as $label => $value)
{{ $label }}: {{ $value }}
@endforeach
@if ($hasLetter)

Your letter is attached. Please keep it for your records.
@endif

Open the portal: {{ $url }}

— The {{ $brand['name'] }} team
