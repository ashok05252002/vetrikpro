@php($late = $items->contains(fn ($i) => $i['days_left'] < 0))
@extends('emails.layout', ['accent' => $late ? '#d03b3b' : '#4f46e5'])

@section('preheader', $late ? 'A renewal is past due. Please renew to avoid interruption.' : 'A renewal is coming up. Please renew in time to avoid interruption.')
@section('eyebrow', 'Renewal reminder')
@section('heading', $late ? 'Your renewal is overdue' : 'Your renewal is coming up')

@section('content')
    <p style="margin:0 0 16px;">
        Dear {{ $customer }}, the following {{ $items->count() === 1 ? 'service is' : 'services are' }} due for renewal.
        Please renew in time to avoid any interruption.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e4e6ee; border-radius:12px; background:#fafbff; font-size:14px;">
        @foreach ($items as $item)
            <tr>
                <td style="padding:12px 18px; {{ $loop->last ? '' : 'border-bottom:1px solid #e4e6ee;' }}">
                    <div style="font-weight:600; color:#1f2430;">{{ $item['label'] }}</div>
                    <div style="margin-top:3px; font-size:13px; color:#6b7180;">
                        Due {{ $item['due'] }}
                        @if ($item['days_left'] < 0)
                            <span style="color:#d03b3b; font-weight:600;">(overdue by {{ abs($item['days_left']) }} {{ abs($item['days_left']) === 1 ? 'day' : 'days' }})</span>
                        @elseif ($item['days_left'] === 0)
                            <span style="color:#d03b3b; font-weight:600;">(today)</span>
                        @endif
                        ·
                        @if ($item['paid'])
                            Balance <strong style="color:#1f2430;">{{ $item['balance'] }}</strong> ({{ $item['paid'] }} received, thank you)
                        @else
                            Amount <strong style="color:#1f2430;">{{ $item['amount'] }}</strong>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </table>

    <p style="margin:18px 0 0;">If you have already paid, please ignore this reminder. For any questions, just reply to this email.</p>
@endsection

@section('signoff')
    — {{ $brand['legal_name'] }}
@endsection

@section('footnote')
    You are receiving this because {{ $brand['name'] }} manages these services for you.
@endsection
