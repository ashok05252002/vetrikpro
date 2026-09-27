<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $draft ? 'Draft invoice' : ($tax ? 'Tax invoice ' : 'Invoice ').$reference }} — {{ $invoice->bill_name }}</title>
<style>
    @page { margin: 34px 0 60px; }
    body { margin: 0; font-family: 'Helvetica', 'Arial', sans-serif; font-size: 9pt; color: #1f2430; line-height: 1.45; }
    .accent { height: 6px; background: #4f46e5; margin-top: -34px; }
    .page { padding: 22px 40px 0; }
    table { border-collapse: collapse; }
    .head { width: 100%; margin-bottom: 14px; }
    .head td { vertical-align: top; }
    .logo img { max-height: 54px; max-width: 180px; }
    .brand { font-size: 15pt; font-weight: bold; }
    .company { text-align: right; font-size: 8.5pt; color: #5b6170; }
    .company .name { font-size: 11pt; font-weight: bold; color: #1f2430; }
    .title { width: 100%; margin: 4px 0 14px; border-top: 1px solid #e4e6ee; border-bottom: 1px solid #e4e6ee; }
    .title td { padding: 10px 0; vertical-align: middle; }
    .title h1 { margin: 0; font-size: 15pt; color: #312e81; letter-spacing: .02em; }
    .title .ref { text-align: right; font-size: 9pt; color: #5b6170; }
    .title .ref strong { color: #1f2430; font-size: 11pt; }
    .stamp { display: inline-block; padding: 2px 8px; border: 1.5px solid #d03b3b; color: #d03b3b; font-weight: bold; font-size: 9pt; letter-spacing: .08em; margin-left: 8px; }
    .stamp.draft { border-color: #8a90a0; color: #8a90a0; }
    .parties { width: 100%; margin-bottom: 14px; }
    .parties td { width: 50%; vertical-align: top; padding-right: 16px; }
    .label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: .06em; color: #8a90a0; margin-bottom: 3px; }
    .party-name { font-weight: bold; font-size: 10pt; }
    .facts td { padding: 1px 0; font-size: 8.5pt; }
    .facts td.k { color: #5b6170; padding-right: 10px; }
    .items { width: 100%; margin-bottom: 12px; }
    .items th { background: #eef0ff; color: #312e81; font-size: 7.5pt; text-transform: uppercase; letter-spacing: .04em; padding: 6px 6px; text-align: right; }
    .items th.l, .items td.l { text-align: left; }
    .items td { padding: 6px 6px; border-bottom: 1px solid #eceef4; text-align: right; vertical-align: top; }
    .items .desc { font-weight: bold; }
    .items .sub { color: #8a90a0; font-size: 7.5pt; }
    .items .was { color: #8a90a0; text-decoration: line-through; font-size: 7.5pt; }
    .bottom { width: 100%; page-break-inside: avoid; }
    .bottom td { vertical-align: top; }
    .totals { width: 100%; }
    .totals td { padding: 3px 0; }
    .totals td.v { text-align: right; }
    .totals tr.grand td { border-top: 1.5px solid #312e81; padding-top: 7px; font-size: 11pt; font-weight: bold; color: #312e81; }
    .words { font-size: 8.5pt; color: #3d4354; margin-top: 6px; }
    .box { margin-top: 12px; font-size: 8.5pt; }
    .box .label { margin-top: 8px; }
    .sign { text-align: right; margin-top: 26px; font-size: 8.5pt; page-break-inside: avoid; }
    .sign .line { border-top: 1px solid #9aa0ae; width: 180px; margin: 34px 0 4px auto; }
    .muted { color: #5b6170; }
    .foot { position: fixed; bottom: -44px; left: 0; right: 0; padding: 8px 40px; border-top: 1px solid #e4e6ee; font-size: 7pt; color: #8a90a0; text-align: center; }
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
                <div>{{ collect([$company['phone'], $company['email']])->filter()->implode(' · ') }}</div>
                @if ($company['tax_id'])<div><strong>GSTIN:</strong> {{ $company['tax_id'] }}</div>@endif
                @if ($company['state'])<div>State: {{ $company['state'] }} ({{ $company['state_code'] }})</div>@endif
            </td>
        </tr>
    </table>

    <table class="title">
        <tr>
            <td>
                <h1>{{ $tax ? 'TAX INVOICE' : 'INVOICE' }}
                    @if ($draft)<span class="stamp draft">DRAFT</span>@endif
                    @if ($cancelled)<span class="stamp">CANCELLED</span>@endif
                </h1>
            </td>
            <td class="ref">
                @unless ($draft)<div>Invoice no. <strong>{{ $reference }}</strong></div>@endunless
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Bill to</div>
                <div class="party-name">{{ $invoice->bill_name }}</div>
                @if ($invoice->bill_address)<div>{!! nl2br(e($invoice->bill_address), false) !!}</div>@endif
                @if ($invoice->bill_gstin)<div><strong>GSTIN:</strong> {{ $invoice->bill_gstin }}</div>@endif
                @if ($invoice->bill_email)<div class="muted">{{ $invoice->bill_email }}</div>@endif
            </td>
            <td>
                <table class="facts">
                    <tr><td class="k">Invoice date</td><td><strong>{{ $issueDate }}</strong></td></tr>
                    @if ($dueDate)<tr><td class="k">Due date</td><td><strong>{{ $dueDate }}</strong></td></tr>@endif
                    @if ($placeOfSupply)<tr><td class="k">Place of supply</td><td>{{ $placeOfSupply }}</td></tr>@endif
                    @if ($tax)<tr><td class="k">Tax</td><td>{{ $invoice->is_interstate ? 'IGST (inter-state)' : 'CGST + SGST (intra-state)' }}</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <tr>
            <th class="l" style="width: 4%;">#</th>
            <th class="l">Item</th>
            <th>Qty</th>
            <th>Rate</th>
            @if ($tax)
                <th>Taxable</th>
                <th>GST</th>
            @endif
            <th>Amount</th>
        </tr>
        @foreach ($items as $i => $item)
            <tr>
                <td class="l">{{ $i + 1 }}</td>
                <td class="l">
                    <div class="desc">{{ $item['description'] }}</div>
                    @if ($item['hsn_sac'])<div class="sub">HSN/SAC {{ $item['hsn_sac'] }}</div>@endif
                </td>
                <td>{{ $item['quantity'] }}</td>
                <td>
                    @if ($item['discounted'])
                        <div class="was">{{ $item['unit_price'] }}</div>
                        <div>{{ $item['discounted'] }}</div>
                    @else
                        {{ $item['unit_price'] }}
                    @endif
                </td>
                @if ($tax)
                    <td>{{ $item['taxable'] }}</td>
                    <td>{{ $item['rate'] }}<div class="sub">{{ $item['tax'] }}</div></td>
                @endif
                <td><strong>{{ $item['amount'] }}</strong></td>
            </tr>
        @endforeach
    </table>

    <table class="bottom">
        <tr>
            <td style="width: 55%; padding-right: 24px;">
                @if ($words)<div class="label">Amount in words</div><div class="words">{{ $words }}</div>@endif
                @if ($bank)
                    <div class="box"><div class="label">Pay to</div>{!! nl2br(e($bank), false) !!}</div>
                @endif
                @if ($invoice->notes)
                    <div class="box"><div class="label">Notes</div>{!! nl2br(e($invoice->notes), false) !!}</div>
                @endif
                @if ($invoice->terms)
                    <div class="box"><div class="label">Terms</div><span class="muted">{!! nl2br(e($invoice->terms), false) !!}</span></div>
                @endif
            </td>
            <td style="width: 45%;">
                <table class="totals">
                    @foreach ($totals as $label => $value)
                        <tr><td class="muted">{{ $label }}</td><td class="v">{{ $value }}</td></tr>
                    @endforeach
                    <tr class="grand"><td>Total</td><td class="v">{{ $total }}</td></tr>
                </table>
                <div class="sign">
                    <div>For <strong>{{ $company['legal_name'] ?: $company['name'] }}</strong></div>
                    <div class="line"></div>
                    <div>{{ $signatory['name'] ?: 'Authorised signatory' }}</div>
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="foot">
    {{ collect([$company['legal_name'] ?: $company['name'], $company['tax_id'] ? 'GSTIN '.$company['tax_id'] : null, $company['website']])->filter()->implode(' · ') }}
    · This is a computer-generated invoice.
</div>
</body>
</html>
