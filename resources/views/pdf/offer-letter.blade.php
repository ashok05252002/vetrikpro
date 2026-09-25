<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $title }} — {{ $values['employee_name'] }}</title>
<style>
    /* Continuation pages keep a margin; page one's accent bar sits in the top margin. */
    @page { margin: 34px 0 70px; }
    body { margin: 0; font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #1f2430; line-height: 1.5; }
    .accent { height: 6px; background: #4f46e5; margin-top: -34px; }
    .page { padding: 22px 48px 0; }
    .head { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .head td { vertical-align: top; }
    .logo img { max-height: 58px; max-width: 190px; }
    .brand { font-size: 15pt; font-weight: bold; color: #1f2430; }
    .company { text-align: right; font-size: 8.5pt; color: #5b6170; line-height: 1.45; }
    .company .name { font-size: 11pt; font-weight: bold; color: #1f2430; }
    .rule { border: 0; border-top: 1px solid #e4e6ee; margin: 0 0 14px; }
    .meta { width: 100%; border-collapse: collapse; font-size: 9pt; color: #5b6170; margin-bottom: 14px; }
    .meta td { padding: 0; }
    .to { margin-bottom: 14px; }
    .to .name { font-weight: bold; color: #1f2430; }
    h1 { font-size: 13pt; margin: 0 0 10px; color: #312e81; }
    p { margin: 0 0 9px; text-align: justify; }
    .summary { width: 100%; border-collapse: collapse; margin: 12px 0 16px; font-size: 9pt; page-break-inside: avoid; }
    .summary th { text-align: left; background: #eef0ff; color: #312e81; padding: 6px 10px; font-size: 8pt; text-transform: uppercase; letter-spacing: .04em; }
    .summary td { padding: 5px 10px; border-bottom: 1px solid #eceef4; }
    .summary td.label { color: #5b6170; width: 20%; }
    .summary td.value { font-weight: bold; width: 30%; }
    .signs { width: 100%; border-collapse: collapse; margin-top: 12px; page-break-inside: avoid; }
    .signs td { width: 50%; vertical-align: top; padding-right: 24px; font-size: 9.5pt; }
    .line { border-top: 1px solid #9aa0ae; width: 78%; margin: 34px 0 5px; }
    .muted { color: #5b6170; }
    .foot { position: fixed; bottom: -52px; left: 0; right: 0; padding: 10px 48px; border-top: 1px solid #e4e6ee; font-size: 7.5pt; color: #8a90a0; text-align: center; }
</style>
</head>
<body>
<div class="accent"></div>
<div class="page">
    <table class="head">
        <tr>
            <td class="logo">
                @if ($company['logo'])
                    <img src="{{ $company['logo'] }}" alt="">
                @else
                    <div class="brand">{{ $company['name'] }}</div>
                @endif
            </td>
            <td class="company">
                <div class="name">{{ $company['legal_name'] ?: $company['name'] }}</div>
                @if ($company['address'])<div>{!! nl2br(e($company['address']), false) !!}</div>@endif
                <div>{{ collect([$company['phone'], $company['email'], $company['website']])->filter()->implode(' · ') }}</div>
            </td>
        </tr>
    </table>
    <hr class="rule">

    <table class="meta">
        <tr>
            <td>Ref: {{ $reference }}</td>
            <td style="text-align: right;">Date: {{ $values['today'] }}</td>
        </tr>
    </table>

    <div class="to">
        <div class="muted">To</div>
        <div class="name">{{ $values['employee_name'] }}</div>
        @if ($email)<div class="muted">{{ $email }}</div>@endif
    </div>

    <h1>{{ $title }}</h1>

    @foreach ($paragraphs as $paragraph)
        <p>{!! $paragraph !!}</p>
    @endforeach

    <table class="summary">
        <tr><th colspan="4">Offer summary</th></tr>
        <tr>
            <td class="label">Position</td><td class="value">{{ $values['designation'] }}</td>
            <td class="label">Department</td><td class="value">{{ $values['department'] }}</td>
        </tr>
        <tr>
            <td class="label">Date of joining</td><td class="value">{{ $values['joining_date'] }}</td>
            <td class="label">Employment type</td><td class="value">{{ $values['employment_type'] }}</td>
        </tr>
        <tr>
            <td class="label">Monthly salary</td><td class="value">{{ $values['monthly_salary'] }}</td>
            <td class="label">Annual CTC</td><td class="value">{{ $values['annual_ctc'] }}</td>
        </tr>
        <tr>
            <td class="label">Employee code</td><td class="value">{{ $values['employee_code'] }}</td>
            <td class="label">Offer valid until</td><td class="value">{{ $values['offer_valid_until'] }}</td>
        </tr>
    </table>

    <table class="signs">
        <tr>
            <td>
                <div>For <strong>{{ $company['legal_name'] ?: $company['name'] }}</strong></div>
                <div class="muted">Signed on behalf of the company.</div>
                <div class="line"></div>
                <div><strong>{{ $signatory['name'] ?: 'Authorised signatory' }}</strong></div>
                <div class="muted">{{ $signatory['title'] }}</div>
            </td>
            <td>
                <div><strong>Acceptance</strong></div>
                <div class="muted">I accept this offer on the terms set out above.</div>
                <div class="line"></div>
                <div><strong>{{ $values['employee_name'] }}</strong></div>
                <div class="muted">Signature and date</div>
            </td>
        </tr>
    </table>
</div>

<div class="foot">
    {{ collect([$company['legal_name'] ?: $company['name'], $company['tax_id'] ? 'GSTIN/Tax ID: '.$company['tax_id'] : null, $company['website']])->filter()->implode(' · ') }}
    · This letter was generated by the {{ $company['name'] }} employee portal.
</div>
</body>
</html>
