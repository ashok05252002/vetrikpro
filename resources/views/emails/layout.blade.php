{{--
    Shared layout for every email. Table-based with inline styles, because
    that is what renders the same in Gmail, Outlook and Apple Mail. Colours
    match the portal: indigo #4f46e5, violet #7c3aed.

    Sections a child view fills:
      preheader  — the grey preview line in the inbox
      eyebrow    — small label above the heading (e.g. WELCOME)
      heading    — the headline
      content    — the body
    and $accent (optional) recolours the eyebrow and top rule, e.g. red for urgent.
--}}
@php
    $accent = $accent ?? '#4f46e5';
    // Embedded when sending; while previewing there is no message to embed into.
    $logo = $brand['logo_path'] ? (isset($message) ? $message->embed($brand['logo_path']) : 'data:image/png;base64,'.base64_encode(file_get_contents($brand['logo_path']))) : null;
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <title>@yield('heading')</title>
    <style>
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .px { padding-left: 22px !important; padding-right: 22px !important; }
            .h1 { font-size: 22px !important; }
        }
        a { color: #4f46e5; }
    </style>
</head>
<body style="margin:0; padding:0; background:#eef0f7; font-family:-apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1f2430; -webkit-font-smoothing:antialiased;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; font-size:1px; line-height:1px; color:#eef0f7;">@yield('preheader')&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef0f7;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px;">

                    {{-- Brand band --}}
                    <tr>
                        <td class="px" bgcolor="#4f46e5" style="background:#4f46e5; background-image:linear-gradient(120deg, #4f46e5 0%, #7c3aed 100%); border-radius:16px 16px 0 0; padding:26px 36px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td valign="middle">
                                        @if ($logo)
                                            <img src="{{ $logo }}" alt="{{ $brand['name'] }}" height="40" style="display:block; height:40px; max-width:180px; border:0; border-radius:6px;">
                                        @else
                                            <span style="font-size:20px; font-weight:700; color:#ffffff; letter-spacing:-0.2px;">{{ $brand['name'] }}</span>
                                        @endif
                                    </td>
                                    <td valign="middle" align="right" style="font-size:12px; color:#e0e1ff; letter-spacing:0.3px;">Employee portal</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td bgcolor="#ffffff" style="background:#ffffff; border-radius:0 0 16px 16px; box-shadow:0 8px 24px rgba(31,36,48,0.06);">
                            <div style="height:4px; background:{{ $accent }}; line-height:4px; font-size:0;">&nbsp;</div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td class="px" style="padding:34px 40px 8px;">
                                        @hasSection('eyebrow')
                                            <p style="margin:0 0 10px; font-size:12px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; color:{{ $accent }};">@yield('eyebrow')</p>
                                        @endif
                                        <h1 class="h1" style="margin:0 0 16px; font-size:26px; line-height:1.25; font-weight:700; color:#1f2430; letter-spacing:-0.3px;">@yield('heading')</h1>
                                        <div style="font-size:15px; line-height:1.65; color:#3d4354;">
                                            @yield('content')
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px" style="padding:22px 40px 34px;">
                                        <div style="border-top:1px solid #eceef4; padding-top:18px; font-size:13px; line-height:1.6; color:#6b7180;">
                                            @hasSection('signoff')
                                                @yield('signoff')
                                            @else
                                                — The {{ $brand['name'] }} team
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding:22px 24px 8px; font-size:12px; line-height:1.6; color:#8a90a0;">
                            <strong style="color:#5b6170;">{{ $brand['legal_name'] }}</strong><br>
                            @if ($brand['address']){!! nl2br(e($brand['address']), false) !!}<br>@endif
                            @if ($brand['contact']){{ $brand['contact'] }}<br>@endif
                            <span style="color:#a3a8b5;">You are receiving this because you have an account on the {{ $brand['name'] }} employee portal.</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
