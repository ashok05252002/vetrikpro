@extends('emails.layout')

@section('preheader', "Reset your {$brand['name']} portal password — the link works for {$expiresInMinutes} minutes.")
@section('eyebrow', 'Password reset')
@section('heading', 'Choose a new password')

@section('content')
    <p style="margin:0 0 14px;">Hi {{ $firstName }}, we received a request to reset the password for <strong style="color:#1f2430;">{{ $email }}</strong>.</p>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Reset password'])

    <p style="margin:14px 0 0; font-size:13px; color:#6b7180;">The link works for {{ $expiresInMinutes }} minutes. If you didn’t ask for this, you can ignore this email — your password stays as it is.</p>
@endsection
