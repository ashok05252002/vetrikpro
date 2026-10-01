Welcome to {{ $brand['name'] }}, {{ $firstName }}!

An account has been created for you on the {{ $brand['name'] }} employee portal. Your sign-in email is {{ $email }}.

Set your password: {{ $url }}

What happens next:
@foreach ($steps as $i => $step)
{{ $i + 1 }}. {{ $step }}
@endforeach
@if ($hasOfferLetter)

Your {{ $letter }} is attached. Please sign it and upload the signed copy when you complete your profile.
@endif

The link works once, for {{ $expiresInHours }} hours. If it has expired, ask HR to send you a new invite.

— {{ $brand['name'] }} HR
