You have {{ $count }} overdue {{ $noun }}

Hi {{ $firstName }},

@foreach ($tasks as $task)
- {{ $task['reference'] }} {{ $task['title'] }} — {{ $task['project'] }}, due {{ $task['due'] }} ({{ $task['days'] }} days late)
  {{ $task['url'] }}
@endforeach

See overdue tasks: {{ $url }}

— The {{ $brand['name'] }} team
