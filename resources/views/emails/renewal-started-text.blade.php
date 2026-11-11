Your plan is active

Dear {{ $plan['customer'] }}, thank you for choosing us. Here are the details of your {{ $plan['label'] }}.

Service: {{ $plan['label'] }}
@if ($plan['start'])
Plan started on: {{ $plan['start'] }}
@endif
Renews: {{ $plan['cycle'] }}
Next renewal on: {{ $plan['due'] }}
Renewal amount: {{ $plan['amount'] }}

We will remind you before the renewal date. For any questions, just reply to this email.

— {{ $brand['legal_name'] }}
