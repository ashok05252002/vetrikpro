Invoice {{ $reference }} — {{ $total }}

Dear {{ $customer }}, please find our invoice attached.
@if ($note)

{{ $note }}
@endif

Invoice: {{ $reference }}
Date: {{ $issueDate }}
@if ($dueDate)
Due: {{ $dueDate }}
@endif
Amount: {{ $total }}

If you have any questions about this invoice, just reply to this email.

— {{ $brand['legal_name'] }}
