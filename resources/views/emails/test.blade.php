@extends('emails.layout')

@section('preheader', 'Your mail settings are working.')
@section('eyebrow', 'Test email')
@section('heading', 'Mail is working')

@section('content')
    <p style="margin:0 0 16px;">{{ $sentBy }} sent this from Settings to check that email reaches a real inbox. If you can read it, nothing more needs doing.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
        @foreach ($facts as $label => $value)
            <tr>
                <td width="110" style="padding:6px 0; color:#8a90a0;">{{ $label }}</td>
                <td style="padding:6px 0; color:#1f2430; font-weight:600;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>
@endsection
