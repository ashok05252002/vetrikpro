Daily summary · {{ $today }} — {{ $totals['projects'] === 1 ? $projects[0]['name'] : $totals['projects'].' active projects' }}

{{ $greeting }}

Changes are since the last summary ({{ $sinceLabel }}).
Open tasks {{ $totals['open_tasks'] }} · Overdue {{ $totals['overdue'] }} · Open bugs {{ $totals['open_bugs'] }} · Awaiting review {{ $totals['reviews'] }} · Done since last {{ $totals['completed'] }}
@foreach ($projects as $p)

== {{ $p['name'] }} ({{ $p['code'] }}) — {{ $p['progress'] }}% done ==
Owner {{ $p['owner'] ?? '—' }}{{ $p['due'] ? ' · due '.$p['due'] : '' }}
@foreach ($p['stages'] as $stage){{ $stage['label'] }} {{ $stage['count'] }}{{ $loop->last ? '' : ' · ' }}@endforeach

{{ $p['url'] }}

PENDING
@foreach (['Overdue tasks' => $p['pending']['overdue'], 'Due in the next 7 days' => $p['pending']['due_soon'], 'Open bugs' => $p['pending']['bugs'], 'Awaiting review' => $p['pending']['reviews']] as $title => $list)
@if ($list['rows'])
{{ $title }}:
@foreach ($list['rows'] as $row)
- {{ $row['reference'] }} {{ $row['title'] }}{{ isset($row['days']) ? ' — '.$row['days'].' days late' : '' }}{{ isset($row['status']) ? ' — '.$row['status'] : '' }}{{ array_key_exists('assignee', $row) ? ' · '.($row['assignee'] ?? 'unassigned') : '' }}
@endforeach
@if ($list['more'])
  and {{ $list['more'] }} more
@endif
@endif
@endforeach

SINCE THE LAST SUMMARY
@if (! $p['changed'])
No changes.
@else
@foreach (['Tasks' => $p['changes']['tasks'], 'Bugs' => $p['changes']['bugs']] as $title => $list)
@foreach ($list['rows'] as $row)
- {{ $row['reference'] }} {{ $row['title'] }}: {{ $row['created'] ? 'New' : $row['from'] }} → {{ $row['to'] }}{{ $row['by'] ? ' (by '.$row['by'].')' : '' }}
@endforeach
@if ($list['more'])
  and {{ $list['more'] }} more {{ strtolower($title) }}
@endif
@endforeach
@foreach ($p['changes']['merged']['rows'] as $row)
- {{ $row['reference'] }} {{ $row['title'] }}: merged{{ $row['by'] ? ' by '.$row['by'] : '' }}
@endforeach
@endif
@endforeach

— The {{ $brand['name'] }} team
