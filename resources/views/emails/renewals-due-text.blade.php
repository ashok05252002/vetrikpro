Renewals: {{ $heading }}

@foreach ($items as $item)
- {{ $item['title'] }}{{ $item['identifier'] ? ' ('.$item['identifier'].')' : '' }} — {{ $item['customer'] ?? 'Our own' }}, due {{ $item['due'] }} ({{ $item['days_left'] < 0 ? 'overdue by '.abs($item['days_left']).' days' : ($item['days_left'] === 0 ? 'due today' : $item['days_left'].' days left') }})
  {{ $item['paid'] ? 'Balance '.$item['balance'].' of '.$item['amount'] : 'Amount '.$item['amount'] }}
  {{ $item['url'] }}
@endforeach

Open renewals: {{ $url }}

— The {{ $brand['name'] }} team
