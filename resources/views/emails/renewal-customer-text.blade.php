Renewal reminder

Dear {{ $customer }}, the following {{ $items->count() === 1 ? 'service is' : 'services are' }} due for renewal. Please renew in time to avoid any interruption.

@foreach ($items as $item)
- {{ $item['label'] }}: due {{ $item['due'] }}{{ $item['days_left'] < 0 ? ' (overdue by '.abs($item['days_left']).' days)' : '' }}. {{ $item['paid'] ? 'Balance '.$item['balance'].' ('.$item['paid'].' received)' : 'Amount '.$item['amount'] }}
@endforeach

If you have already paid, please ignore this reminder. For any questions, just reply to this email.

— {{ $brand['legal_name'] }}
