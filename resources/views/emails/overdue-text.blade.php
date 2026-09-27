{{ $mine ? "You have {$count} overdue {$noun}" : "{$count} overdue {$noun} on your projects" }}

Hi {{ $firstName }},

@foreach ($tasks as $task)
- {{ $task['reference'] }} {{ $task['title'] }} — {{ $task['project'] }}, due {{ $task['due'] }} ({{ $task['days'] }} days late){{ $mine ? '' : ' · '.($task['assignee'] ?? 'unassigned') }}
  {{ $task['url'] }}
@endforeach

See overdue tasks: {{ $url }}

— The {{ $brand['name'] }} team
