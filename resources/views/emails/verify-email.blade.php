@extends('emails.layout')

@section('preheader', 'Confirm your email address to finish setting up your account.')
@section('eyebrow', 'One quick step')
@section('heading', 'Confirm your email address')

@section('content')
    <p style="margin:0 0 14px;">Hi {{ $firstName }}, please confirm that <strong style="color:#1f2430;">{{ $email }}</strong> is your email address.</p>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Confirm email'])

    <p style="margin:14px 0 0; font-size:13px; color:#6b7180;">If you didn’t create an account, no further action is needed.</p>
@endsection
