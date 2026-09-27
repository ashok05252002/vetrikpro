@extends('emails.layout')

@section('preheader', "Invoice {$reference} for {$total}".($dueDate ? ", due {$dueDate}" : '').'.')
@section('eyebrow', 'Invoice '.$reference)
@section('heading', "Invoice for {$total}")

@section('content')
    <p style="margin:0 0 16px;">Dear {{ $customer }}, please find our invoice attached.</p>

    @if ($note)
        <p style="margin:0 0 16px;">{!! nl2br(e($note), false) !!}</p>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e4e6ee; border-radius:12px; background:#fafbff;">
        <tr>
            <td style="padding:14px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
                    <tr><td width="130" style="padding:6px 0; color:#8a90a0;">Invoice</td><td style="padding:6px 0; font-weight:600;">{{ $reference }}</td></tr>
                    <tr><td style="padding:6px 0; color:#8a90a0;">Date</td><td style="padding:6px 0; font-weight:600;">{{ $issueDate }}</td></tr>
                    @if ($dueDate)
                        <tr><td style="padding:6px 0; color:#8a90a0;">Due</td><td style="padding:6px 0; font-weight:600;">{{ $dueDate }}</td></tr>
                    @endif
                    <tr><td style="padding:6px 0; color:#8a90a0;">Amount</td><td style="padding:6px 0; font-weight:700; color:#312e81; font-size:16px;">{{ $total }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:18px 0 0;">If you have any questions about this invoice, just reply to this email.</p>
@endsection
