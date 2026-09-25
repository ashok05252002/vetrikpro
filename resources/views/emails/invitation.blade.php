@extends('emails.layout')

@section('preheader', "Set your password and complete your profile — your link works for {$expiresInHours} hours.")
@section('eyebrow', 'Welcome aboard')
@section('heading', "Welcome to {$brand['name']}, {$firstName}!")

@section('content')
    <p style="margin:0 0 14px;">We’re glad to have you. An account has been created for you on the {{ $brand['name'] }} employee portal, and your sign-in email is <strong style="color:#1f2430;">{{ $email }}</strong>.</p>
    <p style="margin:0 0 6px;">Start by choosing your password:</p>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Set your password'])

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0 6px; background:#f6f7fd; border-radius:12px;">
        <tr>
            <td style="padding:20px 22px 8px; font-size:13px; font-weight:700; letter-spacing:0.4px; color:#312e81;">WHAT HAPPENS NEXT</td>
        </tr>
        @foreach ($steps as $i => $step)
            <tr>
                <td style="padding:8px 22px {{ $loop->last ? '20px' : '8px' }};">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td valign="top" width="30">
                                <div style="width:22px; height:22px; border-radius:11px; background:#4f46e5; color:#ffffff; font-size:12px; font-weight:700; line-height:22px; text-align:center;">{{ $i + 1 }}</div>
                            </td>
                            <td valign="top" style="font-size:14px; line-height:1.5; color:#3d4354; padding-top:1px;">{{ $step }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endforeach
    </table>

    @if ($hasOfferLetter)
        <p style="margin:18px 0 0; padding:14px 16px; background:#fff8eb; border-left:4px solid #f59e0b; border-radius:6px; font-size:14px; color:#5b4a1f;">
            📎 <strong>Your offer letter is attached.</strong> Please sign it and upload the signed copy when you complete your profile.
        </p>
    @endif

    <p style="margin:18px 0 0; font-size:13px; color:#6b7180;">This link works once, for {{ $expiresInHours }} hours. If it has expired, ask HR to send you a new invite.</p>
@endsection

@section('signoff')
    See you soon,<br><strong style="color:#3d4354;">{{ $brand['name'] }} HR</strong>
@endsection
