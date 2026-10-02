Mail is working

{{ $sentBy }} sent this from Settings to check that email reaches a real inbox. If you can read it, nothing more needs doing.

@foreach ($facts as $label => $value)
{{ $label }}: {{ $value }}
@endforeach

— The {{ $brand['name'] }} team
