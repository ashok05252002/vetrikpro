@extends('emails.layout', ['accent' => '#d03b3b'])

@section('preheader', "{$count} {$noun} past due — oldest first.")
@section('eyebrow', 'Overdue')
@section('heading', "{$count} overdue {$noun}")

@section('content')
    <p style="margin:0 0 16px;">
        Hi {{ $firstName }},
        these are past their due date and still open. Please update them or move the dates.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #fde2e2; border-radius:12px; background:#fff7f7; font-size:14px;">
        @foreach ($tasks as $task)
            <tr>
                <td style="padding:12px 18px; {{ $loop->last ? '' : 'border-bottom:1px solid #fde2e2;' }}">
                    <a href="{{ $task['url'] }}" style="color:#1f2430; font-weight:600; text-decoration:none;">
                        <span style="font-family:Menlo, Consolas, monospace; font-size:12px; color:#6b7180;">{{ $task['reference'] }}</span>
                        {{ $task['title'] }}
                    </a>
                    <div style="margin-top:3px; font-size:12px; color:#8a90a0;">
                        {{ $task['project'] }} · due {{ $task['due'] }}
                        <span style="color:#d03b3b; font-weight:600;">({{ $task['days'] }} {{ $task['days'] === 1 ? 'day' : 'days' }} late)</span>
                    </div>
                </td>
            </tr>
        @endforeach
    </table>

    @include('emails.partials.button', ['url' => $url, 'label' => 'See overdue tasks', 'color' => '#d03b3b'])
@endsection
