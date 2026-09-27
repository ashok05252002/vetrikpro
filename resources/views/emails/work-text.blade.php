{{ $eyebrow }}: {{ $card['reference'] }} {{ $card['title'] }}

Hi {{ $firstName }}, {{ $lead }}

@foreach ($card['facts'] as $label => $value)
{{ $label }}: {{ $value }}
@endforeach

{{ $button }}: {{ $card['url'] }}

— The {{ $brand['name'] }} team
