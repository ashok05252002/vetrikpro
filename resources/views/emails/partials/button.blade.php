{{-- A button that renders as a button in every client, Outlook included. --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 18px;">
    <tr>
        <td bgcolor="{{ $color ?? '#4f46e5' }}" style="border-radius:10px; background:{{ $color ?? '#4f46e5' }};">
            <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:14px 28px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:10px;">{{ $label }}</a>
        </td>
    </tr>
</table>
<p style="margin:0 0 6px; font-size:12px; color:#8a90a0;">Button not working? Paste this link into your browser:<br><a href="{{ $url }}" style="color:#6366f1; word-break:break-all;">{{ $url }}</a></p>
