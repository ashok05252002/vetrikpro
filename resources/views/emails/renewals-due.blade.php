@extends('emails.layout', ['accent' => $overdue > 0 ? '#d03b3b' : '#4f46e5'])

@section('preheader', "{$heading} — most urgent first.")
@section('eyebrow', 'Renewals')
@section('heading', $heading)

@section('content')
    <p style="margin:0 0 16px;">
        These renewals are due soon or past due and not fully paid. You’ll get this every day until each one is paid,
        paused or cancelled.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e4e6ee; border-radius:12px; background:#fafbff; font-size:14px;">
        @foreach ($items as $item)
            <tr>
                <td style="padding:12px 18px; {{ $loop->last ? '' : 'border-bottom:1px solid #e4e6ee;' }}">
                    <a href="{{ $item['url'] }}" style="color:#1f2430; font-weight:600; text-decoration:none;">
                        {{ $item['title'] }}
                        @if ($item['identifier'])
                            <span style="font-family:Menlo, Consolas, monospace; font-size:12px; color:#6b7180;">{{ $item['identifier'] }}</span>
                        @endif
                    </a>
                    <div style="margin-top:3px; font-size:12px; color:#8a90a0;">
                        {{ $item['category'] }} · {{ $item['customer'] ?? 'Our own' }}@if ($item['provider']) · {{ $item['provider'] }}@endif · due {{ $item['due'] }}
                        @if ($item['days_left'] < 0)
                            <span style="color:#d03b3b; font-weight:600;">(overdue by {{ abs($item['days_left']) }} {{ abs($item['days_left']) === 1 ? 'day' : 'days' }})</span>
                        @elseif ($item['days_left'] === 0)
                            <span style="color:#d03b3b; font-weight:600;">(due today)</span>
                        @else
                            <span style="color:#1f2430; font-weight:600;">({{ $item['days_left'] }} {{ $item['days_left'] === 1 ? 'day' : 'days' }} left)</span>
                        @endif
                    </div>
                    <div style="margin-top:3px; font-size:12px; color:#6b7180;">
                        @if ($item['paid'])
                            Balance <strong style="color:#1f2430;">{{ $item['balance'] }}</strong> of {{ $item['amount'] }} ({{ $item['paid'] }} paid)
                        @else
                            Amount <strong style="color:#1f2430;">{{ $item['amount'] }}</strong>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </table>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Open renewals', 'color' => '#4f46e5'])
@endsection

@section('footnote')
    You are receiving this because you own these renewals or are on the renewal reminder list.
@endsection
