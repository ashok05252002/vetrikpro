@extends('emails.layout', ['accent' => $accent])

@section('preheader', "{$card['reference']} {$card['title']} — {$lead}")
@section('eyebrow', $eyebrow)
@section('heading', $card['title'])

@section('content')
    <p style="margin:0 0 16px;">Hi {{ $firstName }}, {{ $lead }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e4e6ee; border-radius:12px; background:#fafbff;">
        <tr>
            <td style="padding:14px 20px 4px;">
                <span style="display:inline-block; font-family:Menlo, Consolas, monospace; font-size:12px; color:#6b7180;">{{ $card['reference'] }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding:4px 20px 14px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
                    @foreach ($card['facts'] as $label => $value)
                        <tr>
                            <td width="110" style="padding:5px 0; color:#8a90a0;">{{ $label }}</td>
                            <td style="padding:5px 0; color:#1f2430; font-weight:600;">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    @include('emails.partials.button', ['url' => $card['url'], 'label' => $button, 'color' => $accent])
@endsection
