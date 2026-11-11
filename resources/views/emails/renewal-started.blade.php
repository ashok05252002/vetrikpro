@extends('emails.layout', ['accent' => '#4f46e5'])

@section('preheader', "Your {$plan['label']} is active. Next renewal: {$plan['due']}.")
@section('eyebrow', $plan['category'])
@section('heading', 'Your plan is active')

@section('content')
    <p style="margin:0 0 16px;">
        Dear {{ $plan['customer'] }}, thank you for choosing us. Here are the details of your {{ $plan['label'] }}.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e4e6ee; border-radius:12px; background:#fafbff;">
        <tr>
            <td style="padding:14px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
                    <tr><td width="150" style="padding:6px 0; color:#8a90a0;">Service</td><td style="padding:6px 0; font-weight:600;">{{ $plan['label'] }}</td></tr>
                    @if ($plan['start'])
                        <tr><td style="padding:6px 0; color:#8a90a0;">Plan started on</td><td style="padding:6px 0; font-weight:600;">{{ $plan['start'] }}</td></tr>
                    @endif
                    <tr><td style="padding:6px 0; color:#8a90a0;">Renews</td><td style="padding:6px 0; font-weight:600;">{{ $plan['cycle'] }}</td></tr>
                    <tr><td style="padding:6px 0; color:#8a90a0;">Next renewal on</td><td style="padding:6px 0; font-weight:700; color:#312e81; font-size:16px;">{{ $plan['due'] }}</td></tr>
                    <tr><td style="padding:6px 0; color:#8a90a0;">Renewal amount</td><td style="padding:6px 0; font-weight:600;">{{ $plan['amount'] }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:18px 0 0;">We will remind you before the renewal date. For any questions, just reply to this email.</p>
@endsection

@section('signoff')
    — {{ $brand['legal_name'] }}
@endsection

@section('footnote')
    You are receiving this because {{ $brand['name'] }} manages this service for you.
@endsection
