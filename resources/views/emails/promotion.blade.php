@extends('emails.layout', ['accent' => '#16a34a'])

@section('preheader', $promoted ? "You have been promoted to {$designation}, effective {$effective}." : "Your salary has been revised, effective {$effective}.")
@section('eyebrow', $promoted ? '🎉 Promotion' : 'Salary revision')
@section('heading', $promoted ? "Congratulations, {$firstName}!" : "Good news, {$firstName}")

@section('content')
    <p style="margin:0 0 16px;">
        @if ($promoted)
            We are delighted to let you know that you have been promoted to <strong style="color:#1f2430;">{{ $designation }}</strong>. Thank you for everything you bring to the team.
        @else
            Your compensation has been revised in recognition of your contribution. Thank you for your work.
        @endif
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #d9f2df; border-radius:12px; background:#f5fcf7;">
        <tr>
            <td style="padding:14px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
                    @foreach ($facts as $label => $value)
                        <tr>
                            <td width="160" style="padding:6px 0; color:#8a90a0;">{{ $label }}</td>
                            <td style="padding:6px 0; color:#1f2430; font-weight:600;">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    @if ($hasLetter)
        <p style="margin:18px 0 0;">Your letter is attached. Please keep it for your records.</p>
    @endif

    @include('emails.partials.button', ['url' => $url, 'label' => 'Open the portal', 'color' => '#16a34a'])
@endsection
