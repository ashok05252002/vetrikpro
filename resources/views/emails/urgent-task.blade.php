@extends('emails.layout', ['accent' => '#dc2626'])

@section('preheader', "{$task['reference']} {$task['title']} — marked urgent by {$actor}.")
@section('eyebrow', '⚡ Urgent task')
@section('heading', $task['title'])

@section('content')
    <p style="margin:0 0 16px;">Hi {{ $firstName }}, <strong style="color:#1f2430;">{{ $actor }}</strong> {{ $reason }}. It needs your attention first.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #fde2e2; border-radius:12px; background:#fff7f7;">
        <tr>
            <td style="padding:16px 20px 6px;">
                <span style="display:inline-block; padding:3px 10px; border-radius:999px; background:#dc2626; color:#ffffff; font-size:11px; font-weight:700; letter-spacing:0.6px;">URGENT</span>
                <span style="display:inline-block; margin-left:8px; font-family:Menlo, Consolas, monospace; font-size:12px; color:#6b7180;">{{ $task['reference'] }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding:6px 20px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
                    @foreach ($facts as $label => $value)
                        <tr>
                            <td width="110" style="padding:6px 0; color:#8a90a0;">{{ $label }}</td>
                            <td style="padding:6px 0; color:#1f2430; font-weight:600;">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
        @if ($task['description'])
            <tr>
                <td style="padding:0 20px 18px; font-size:14px; line-height:1.6; color:#3d4354;">
                    <div style="border-top:1px solid #fde2e2; padding-top:12px;">{!! nl2br(e(\Illuminate\Support\Str::limit($task['description'], 600)), false) !!}</div>
                </td>
            </tr>
        @endif
    </table>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Open the task', 'color' => '#dc2626'])
@endsection
