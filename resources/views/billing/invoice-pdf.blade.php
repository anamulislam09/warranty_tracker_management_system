@php
    // $invoice (with account, payments) and $b (company details + logo) come from App\Services\InvoicePdf
    $m = fn ($v) => \App\Models\Invoice::moneyPlain($v);
    $long = 'd F Y';
    $short = 'd M Y';
    $status = $invoice->status;
    $account = $invoice->account;
    $owner = optional($account->owner)->name;
    $country = config('warranty.countries')[$account->country_code] ?? $account->country_code;

    // the watermark: one strong colour, made very light by opacity, and kept BEHIND the text
    $watermark = $status === 'paid' ? 'PAID' : ($status === 'cancelled' ? 'CANCELLED' : 'DUE');
    $wmColor   = $status === 'paid' ? '#198754' : ($status === 'cancelled' ? '#6c757d' : '#dc3545');
    $stColor   = $status === 'paid' ? '#198754' : ($status === 'cancelled' ? '#6c757d' : ($invoice->is_overdue ? '#dc3545' : '#d97706'));

    $addressLines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $b['company_address'])));
    $noteLines    = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $b['invoice_notes'])));
    $hasDiscount  = (float) $invoice->discount_amount > 0;

    // the invoice must stay on ONE page: the last 5 payments are listed, older ones are summed up
    $all    = $invoice->payments;
    $shown  = $all->slice(-5)->values();
    $older  = $all->slice(0, max(0, $all->count() - 5));
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_no }}</title>
    <style>
        @page { margin: 22px 30px 36px 30px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5px; color: #222; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        /* watermark: behind everything, very light */
        .wm { position: fixed; z-index: -1; top: 330px; left: 0; width: 100%; text-align: center;
              font-size: 130px; font-weight: bold; color: {{ $wmColor }}; opacity: 0.08; transform: rotate(-30deg); }

        .navy { color: #12305f; }
        .muted { color: #6b7a90; }
        .co-name { font-size: 18px; font-weight: bold; color: #12305f; }
        .tagline { font-size: 7.5px; letter-spacing: .4px; color: #6b7a90; text-transform: uppercase; }
        .inv-title { font-size: 28px; font-weight: bold; color: #12305f; text-align: right; line-height: 1.1; }
        .inv-sub { font-size: 9px; font-weight: bold; color: #7b8aa0; text-align: right; letter-spacing: .5px; }
        .rule { border-top: 2px solid #1b7fd0; margin: 8px 0 9px 0; }

        .panel td { background: #f0f5fa; padding: 7px 10px; border: 1px solid #dbe5f0; }
        .h { font-size: 9.5px; font-weight: bold; color: #12305f; text-transform: uppercase; margin: 0 0 3px 0; }
        .sec { font-size: 9.5px; font-weight: bold; color: #12305f; text-transform: uppercase; margin: 10px 0 4px 0; }

        .grid th { background: #12305f; color: #fff; text-align: left; padding: 5px 8px; font-size: 8.5px; text-transform: uppercase; }
        .grid td { padding: 5px 8px; border: 1px solid #dbe5f0; }
        .grid .light td { background: #f0f5fa; }
        .r { text-align: right; }
        .c { text-align: center; }

        .totals td { padding: 4px 8px; border: 1px solid #dbe5f0; background: #f8fafc; }
        .totals .net td { background: #e6eef8; font-weight: bold; font-size: 11px; color: #12305f; }

        .stamp { font-weight: bold; color: {{ $stColor }}; }
        .footer { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 8px; color: #7b8aa0; border-top: 1px solid #dbe5f0; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="wm">{{ $watermark }}</div>

    {{-- ---------- header: logo, company, INVOICE ---------- --}}
    <table>
        <tr>
            <td style="width:14%">
                @if ($b['logo'])<img src="{{ $b['logo'] }}" style="width:86px;height:86px" alt="">@endif
            </td>
            <td style="width:52%">
                <div class="co-name">{{ $b['company_name'] }}</div>
                @if ($b['company_tagline'])<div class="tagline">{{ $b['company_tagline'] }}</div>@endif
                <div style="margin-top:5px; line-height:1.4">
                    @if ($b['company_office'])<b>{{ $b['company_office'] }}</b><br>@endif
                    @foreach ($addressLines as $line){{ $line }}<br>@endforeach
                    @if ($b['company_email'])Email: {{ $b['company_email'] }}<br>@endif
                    @if ($b['company_website'])Web: {{ $b['company_website'] }}@endif
                    @if ($b['company_phone']) &nbsp;|&nbsp; Phone: {{ $b['company_phone'] }}@endif
                </div>
            </td>
            <td style="width:34%">
                <div class="inv-title">INVOICE</div>
                @if ($b['invoice_subtitle'])<div class="inv-sub">{{ $b['invoice_subtitle'] }}</div>@endif
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    {{-- ---------- invoice to / details ---------- --}}
    <table class="panel">
        <tr>
            <td style="width:50%">
                <div class="h">Invoice to</div>
                <b>{{ $account->name }}</b><br>
                @if ($owner && $owner !== $account->name)Attn: {{ $owner }}<br>@endif
                {{ $country }}<br>
                Email: {{ $account->email }}@if ($account->phone)<br>Phone: {{ $account->phone }}@endif
            </td>
            <td style="width:50%">
                <div class="h">Invoice details</div>
                <b>Invoice No:</b> {{ $invoice->invoice_no }}<br>
                <b>Invoice Date:</b> {{ $invoice->created_at->format($long) }}<br>
                <b>Due Date:</b> {{ $invoice->due_date->format($long) }}<br>
                <b>Currency:</b> BDT (Bangladeshi Taka)<br>
                <b>Status:</b> <span class="stamp">{{ strtoupper($invoice->status_text) }}</span>
            </td>
        </tr>
    </table>

    {{-- ---------- subscription summary ---------- --}}
    <div class="sec">Subscription summary</div>
    <table class="grid">
        <thead><tr><th style="width:32%">Product</th><th style="width:18%">Plan</th><th style="width:20%">Billing cycle</th><th>Subscription period</th></tr></thead>
        <tbody>
            <tr class="light">
                <td><b>{{ $b['product_name'] }}</b>@if ($b['product_description'])<br>{{ $b['product_description'] }}@endif</td>
                <td><b>{{ $invoice->plan_name }}</b></td>
                <td><b>{{ $invoice->cycle_label }}</b><br>{{ $invoice->months }} {{ $invoice->months == 1 ? 'month' : 'months' }}</td>
                <td>{{ $invoice->billing_month->format($short) }} &ndash; {{ optional($invoice->period_end)->format($short) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ---------- charges ---------- --}}
    <div class="sec">Charges</div>
    <table class="grid">
        <thead><tr><th>Description</th><th class="c" style="width:8%">Qty</th><th class="r" style="width:20%">Unit price</th><th class="r" style="width:20%">Amount</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ $b['product_name'] }} &ndash; {{ $invoice->plan_name }} Plan<br>
                    <span class="muted">{{ $invoice->cycle_label }} subscription ({{ $invoice->months }} {{ $invoice->months == 1 ? 'month' : 'months' }})</span></td>
                <td class="c">1</td>
                <td class="r">{{ $m($invoice->base_amount) }}</td>
                <td class="r"><b>{{ $m($invoice->base_amount) }}</b></td>
            </tr>
            @if ($hasDiscount)
                <tr>
                    <td>{{ $invoice->cycle_label }} subscription discount</td>
                    <td class="c">&mdash;</td>
                    <td class="r">{{ (float) $invoice->discount_percent }}%</td>
                    <td class="r" style="color:#198754"><b>- {{ $m($invoice->discount_amount) }}</b></td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ---------- payments received (left) and totals (right) ---------- --}}
    <table style="margin-top:9px">
        <tr>
            <td style="width:54%; padding-right:12px">
                <div class="sec" style="margin-top:0">Payments received</div>
                @if ($all->count())
                    <table class="grid">
                        <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="r">Amount</th></tr></thead>
                        <tbody>
                            @if ($older->count())
                                <tr><td colspan="3" class="muted">{{ $older->count() }} earlier {{ $older->count() == 1 ? 'payment' : 'payments' }}</td><td class="r">{{ $m($older->sum('amount')) }}</td></tr>
                            @endif
                            @foreach ($shown as $p)
                                <tr>
                                    <td>{{ $p->paid_on->format($short) }}</td>
                                    <td>{{ config('billing.methods')[$p->method] ?? $p->method }}</td>
                                    <td>{{ $p->reference ?: '-' }}</td>
                                    <td class="r">{{ $m($p->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="muted">No payment received yet.</div>
                @endif
            </td>
            <td style="width:46%">
                <table class="totals">
                    <tr><td>Subtotal</td><td class="r">{{ $m($invoice->base_amount) }}</td></tr>
                    @if ($hasDiscount)<tr><td>Discount ({{ (float) $invoice->discount_percent }}%)</td><td class="r">- {{ $m($invoice->discount_amount) }}</td></tr>@endif
                    <tr><td>VAT / Tax</td><td class="r">{{ $b['tax_text'] ?: 'Not charged' }}</td></tr>
                    <tr class="net"><td>NET PAYABLE</td><td class="r">{{ $m($invoice->amount) }}</td></tr>
                    <tr><td>Paid</td><td class="r" style="color:#198754">{{ $m($invoice->paid_amount) }}</td></tr>
                    <tr><td><b>Balance due</b></td>
                        <td class="r"><b style="color:{{ $invoice->balance > 0 && $status !== 'cancelled' ? '#dc3545' : '#198754' }}">{{ $status === 'cancelled' ? '-' : $m($invoice->balance) }}</b></td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ---------- payment information / notes ---------- --}}
    <table class="panel" style="margin-top:10px">
        <tr>
            <td style="width:50%">
                <div class="h">Payment information</div>
                @if ($b['payment_method'])<b>Payment Method:</b> {{ $b['payment_method'] }}<br>@endif
                @if ($b['bank_name'])<b>Bank:</b> {{ $b['bank_name'] }}@endif
                @if ($b['bank_branch']) &nbsp;<b>Branch:</b> {{ $b['bank_branch'] }}@endif
                @if ($b['bank_name'] || $b['bank_branch'])<br>@endif
                @if ($b['account_name'])<b>Account Name:</b> {{ $b['account_name'] }}<br>@endif
                @if ($b['account_no'])<b>Account No:</b> {{ $b['account_no'] }}<br>@endif
                <b>Payment Reference:</b> {{ $invoice->invoice_no }}
            </td>
            <td style="width:50%">
                <div class="h">Subscription &amp; billing notes</div>
                @foreach ($noteLines as $line)&bull; {{ $line }}<br>@endforeach
                @if ($invoice->note)&bull; {{ $invoice->note }}<br>@endif
            </td>
        </tr>
    </table>

    @if ($b['invoice_footer'])
        <p class="navy" style="text-align:center; margin: 9px 0 0 0; font-size: 10.5px">{{ $b['invoice_footer'] }}</p>
    @endif

    <div class="footer">
        {{ strtoupper($b['company_name']) }} &nbsp;|&nbsp; {{ strtoupper($b['product_name']) }}
        @if ($b['company_website']) &nbsp;|&nbsp; {{ $b['company_website'] }}@endif
        @if ($b['company_email']) &nbsp;|&nbsp; {{ $b['company_email'] }}@endif
    </div>
</body>
</html>
