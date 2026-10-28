@extends('emails.layout')

@php
    $plural = fn (int $n, string $word) => $n.' '.($n === 1 ? $word : str($word)->plural());
    $muted = 'font-size:12px; color:#8a90a0;';
    $ref = 'font-family:Menlo, Consolas, monospace; font-size:12px; color:#6b7180;';
@endphp

@section('preheader', "{$plural($totals['open_tasks'], 'open task')}, {$totals['overdue']} overdue, {$plural($totals['open_bugs'], 'open bug')} — and what changed since {$sinceLabel}.")
@section('eyebrow', "Daily summary · {$today}")
@section('heading', $totals['projects'] === 1 ? $projects[0]['name'] : "{$totals['projects']} active projects")

@section('content')
    <p style="margin:0 0 16px;">
        {{ $greeting }} here is where {{ $totals['projects'] === 1 ? 'the project stands' : 'every active project stands' }} today,
        what is still pending, and what changed since the last summary ({{ $sinceLabel }}).
    </p>

    {{-- Totals across everything in this email --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #eceef4; border-radius:12px; font-size:13px; text-align:center;">
        <tr>
            @foreach ([['Open tasks', $totals['open_tasks']], ['Overdue', $totals['overdue']], ['Open bugs', $totals['open_bugs']], ['Awaiting review', $totals['reviews']], ['Done since last', $totals['completed']]] as [$label, $value])
                <td style="padding:12px 6px; {{ $loop->last ? '' : 'border-right:1px solid #eceef4;' }}">
                    <div style="font-size:20px; font-weight:700; color:{{ $label === 'Overdue' && $value > 0 ? '#d03b3b' : '#1f2430' }};">{{ $value }}</div>
                    <div style="{{ $muted }}">{{ $label }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    @foreach ($projects as $p)
        <div style="margin-top:28px; padding-top:20px; border-top:2px solid #eceef4;">
            <a href="{{ $p['url'] }}" style="color:#1f2430; font-size:18px; font-weight:700; text-decoration:none;">{{ $p['name'] }}</a>
            <span style="{{ $ref }}">{{ $p['code'] }}</span>
            <div style="{{ $muted }} margin-top:2px;">
                Owner {{ $p['owner'] ?? '—' }}@if ($p['due']) · due {{ $p['due'] }}@endif
            </div>

            {{-- Progress --}}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:12px 0 4px;">
                <tr>
                    <td style="background:#eceef4; border-radius:4px; height:8px; line-height:8px; font-size:0;">
                        @if ($p['progress'] > 0)
                            <div style="width:{{ $p['progress'] }}%; background:#4f46e5; border-radius:4px; height:8px; line-height:8px; font-size:0;">&nbsp;</div>
                        @endif
                    </td>
                    <td width="48" align="right" style="font-size:13px; font-weight:600; color:#1f2430;">{{ $p['progress'] }}%</td>
                </tr>
            </table>
            <div style="{{ $muted }}">
                @foreach ($p['stages'] as $stage){{ $stage['label'] }} {{ $stage['count'] }}@unless ($loop->last) · @endunless @endforeach
            </div>

            {{-- Pending --}}
            <p style="margin:18px 0 6px; font-size:13px; font-weight:700; letter-spacing:0.6px; text-transform:uppercase; color:#5b6170;">Pending</p>
            @php
                $pending = $p['pending'];
                $nothingPending = ! $pending['overdue']['rows'] && ! $pending['due_soon']['rows'] && ! $pending['bugs']['rows'] && ! $pending['reviews']['rows'];
            @endphp
            @if ($nothingPending)
                <p style="margin:0; {{ $muted }}">Nothing pending — no overdue or upcoming tasks, open bugs or reviews.</p>
            @endif

            @foreach ([
                ['Overdue tasks', $pending['overdue'], '#d03b3b'],
                ['Due in the next 7 days', $pending['due_soon'], null],
                ['Open bugs', $pending['bugs'], null],
                ['Merge requests awaiting review', $pending['reviews'], null],
            ] as [$title, $list, $colour])
                @if ($list['rows'])
                    <p style="margin:10px 0 4px; font-size:13px; font-weight:600; color:{{ $colour ?? '#1f2430' }};">{{ $title }} ({{ count($list['rows']) + $list['more'] }})</p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #eceef4; border-radius:10px; font-size:13px;">
                        @foreach ($list['rows'] as $row)
                            <tr>
                                <td style="padding:8px 14px; {{ $loop->last && ! $list['more'] ? '' : 'border-bottom:1px solid #eceef4;' }}">
                                    <a href="{{ $row['url'] }}" style="color:#1f2430; text-decoration:none;"><span style="{{ $ref }}">{{ $row['reference'] }}</span> {{ $row['title'] }}</a>
                                    <div style="{{ $muted }}">
                                        @isset($row['days'])
                                            due {{ $row['due'] }} <span style="color:#d03b3b; font-weight:600;">({{ $plural($row['days'], 'day') }} late)</span> · {{ $row['assignee'] ?? 'unassigned' }}
                                        @elseif (isset($row['due']))
                                            due {{ $row['due'] }} · {{ $row['assignee'] ?? 'unassigned' }}
                                        @elseif (isset($row['status']))
                                            {{ $row['status'] }} · {{ $row['assignee'] ?? 'unassigned' }}
                                        @else
                                            by {{ $row['requester'] ?? '—' }} · reviewer {{ $row['reviewer'] ?? 'any dev admin' }}
                                        @endisset
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @if ($list['more'])
                            <tr><td style="padding:8px 14px; {{ $muted }}">and {{ $list['more'] }} more</td></tr>
                        @endif
                    </table>
                @endif
            @endforeach

            {{-- Since the last summary --}}
            <p style="margin:18px 0 6px; font-size:13px; font-weight:700; letter-spacing:0.6px; text-transform:uppercase; color:#5b6170;">Since the last summary</p>
            @if (! $p['changed'])
                <p style="margin:0; {{ $muted }}">No changes.</p>
            @else
                @php $c = $p['counts']; @endphp
                <p style="margin:0 0 6px; {{ $muted }}">
                    {{ $plural($c['created'], 'task') }} created · {{ $c['completed'] }} completed · {{ $plural($c['bugs_reported'], 'bug') }} reported · {{ $c['bugs_closed'] }} closed · {{ $c['merged'] }} merged
                </p>

                @foreach ([['Tasks', $p['changes']['tasks']], ['Bugs', $p['changes']['bugs']], ['Merged', $p['changes']['merged']]] as [$title, $list])
                    @if ($list['rows'])
                        <p style="margin:10px 0 4px; font-size:13px; font-weight:600; color:#1f2430;">{{ $title }}</p>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #eceef4; border-radius:10px; font-size:13px;">
                            @foreach ($list['rows'] as $row)
                                <tr>
                                    <td style="padding:8px 14px; {{ $loop->last && ! $list['more'] ? '' : 'border-bottom:1px solid #eceef4;' }}">
                                        <a href="{{ $row['url'] }}" style="color:#1f2430; text-decoration:none;"><span style="{{ $ref }}">{{ $row['reference'] }}</span> {{ $row['title'] }}</a>
                                        <div style="{{ $muted }}">
                                            @if ($title === 'Merged')
                                                merged by {{ $row['by'] ?? '—' }}
                                            @else
                                                {{ $row['created'] ? 'New' : $row['from'] }} → <strong style="color:#3d4354;">{{ $row['to'] }}</strong>@if ($row['by']) · by {{ $row['by'] }}@endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            @if ($list['more'])
                                <tr><td style="padding:8px 14px; {{ $muted }}">and {{ $list['more'] }} more</td></tr>
                            @endif
                        </table>
                    @endif
                @endforeach
            @endif
        </div>
    @endforeach

    @include('emails.partials.button', ['url' => route('projects.index'), 'label' => 'Open projects'])
@endsection

@section('footnote')
    @if ($leadership)
        You are receiving this daily summary because your address is on the leadership list in {{ $brand['name'] }} → Email notifications.
    @else
        You are receiving this daily summary because you were added to {{ $totals['projects'] === 1 ? 'this project' : 'these projects' }} on {{ $brand['name'] }}.
    @endif
@endsection
