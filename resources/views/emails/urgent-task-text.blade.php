URGENT: {{ $task['reference'] }} {{ $task['title'] }}

Hi {{ $firstName }}, {{ $actor }} {{ $reason }}.

@foreach ($facts as $label => $value)
{{ $label }}: {{ $value }}
@endforeach
@if ($task['description'])

{{ \Illuminate\Support\Str::limit($task['description'], 600) }}
@endif

Open the task: {{ $url }}

— The {{ $brand['name'] }} team
