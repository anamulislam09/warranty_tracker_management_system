<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountStatusLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Http\Request;

/**
 * The admin dashboard has NO product data: customers and money only.
 *  - the numbers (cards) follow the from - to filter; without a filter = this month and the 3 months before it
 *  - the charts never follow the filter: they always show this month and the 3 months before it
 */
class DashboardController extends Controller
{
    private function cleanDate(?string $value): ?string
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    public function __invoke(Request $request)
    {
        // ---- the dates: default = this month + the 3 months before it (4 months)
        $isDefault = ! $request->filled('from') && ! $request->filled('to');
        $from = $this->cleanDate($request->from) ?? today()->startOfMonth()->subMonthsNoOverflow(3)->toDateString();
        $to   = $this->cleanDate($request->to) ?? today()->endOfMonth()->toDateString();
        if ($from > $to) [$from, $to] = [$to, $from];

        // ---- customers (the date they registered)
        $byStatus = Account::query()->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        // every card opens the list of the same thing with the same dates
        $customerLink = fn (?string $status = null) => route('admin.accounts.index', array_filter(['status' => $status, 'from' => $from, 'to' => $to]));

        // ---- money: the bills issued in the dates (cancelled bills are ignored)
        $bills     = fn () => Invoice::where('status', '!=', 'cancelled')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to);
        $billed    = (float) $bills()->sum('amount');
        $collected = (float) $bills()->sum('paid_amount');
        $overdue   = (float) $bills()->overdue()->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as d')->value('d');

        // ---- charts: ALWAYS this month and the 3 months before it
        $months = collect([3, 2, 1, 0])->map(fn ($i) => today()->startOfMonth()->subMonthsNoOverflow($i));
        $first  = $months->first()->copy()->startOfDay();
        $last   = today()->endOfMonth();
        $key    = fn ($date) => $date->format('Y-m');

        $newByMonth      = Account::whereBetween('created_at', [$first, $last])->pluck('created_at')->groupBy($key)->map->count();
        $inactiveByMonth = AccountStatusLog::where('to_status', 'inactive')->whereBetween('created_at', [$first, $last])
            ->pluck('created_at')->groupBy($key)->map->count();
        $collectedByMonth = Payment::whereBetween('paid_on', [$first->toDateString(), $last->toDateString()])
            ->get(['paid_on', 'amount'])->groupBy(fn ($p) => $key($p->paid_on))->map(fn ($g) => round($g->sum('amount'), 2));

        $series = fn ($map, bool $money = false) => $months->map(fn ($m) => $money ? (float) ($map[$key($m)] ?? 0) : (int) ($map[$key($m)] ?? 0))->all();

        $charts = [
            'labels'       => $months->map(fn ($m) => $m->format('M Y'))->all(),
            'newCustomers' => $series($newByMonth),
            'inactive'     => $series($inactiveByMonth),
            'collected'    => $series($collectedByMonth, true),
            'status'       => [
                'labels' => $byStatus->keys()->map(fn ($s) => ucfirst($s) . ' (' . $byStatus[$s] . ')')->all(),
                'values' => $byStatus->values()->map(fn ($v) => (int) $v)->all(),
                'colors' => $byStatus->keys()->map(fn ($s) => ['active' => '#198754', 'inactive' => '#dc3545', 'pending' => '#ffc107'][$s] ?? '#6c757d')->all(),
            ],
        ];

        return view('admin.dashboard', [
            'from' => $from, 'to' => $to, 'isDefault' => $isDefault, 'charts' => $charts,
            'customerCards' => [
                ['Total Customers', (int) $byStatus->sum(), 'bi-people', 'primary', $customerLink()],
                ['Pending Approval', (int) ($byStatus['pending'] ?? 0), 'bi-hourglass-split', 'warning', $customerLink('pending')],
                ['Active Customers', (int) ($byStatus['active'] ?? 0), 'bi-person-check', 'success', $customerLink('active')],
                ['Inactive Customers', (int) ($byStatus['inactive'] ?? 0), 'bi-person-x', 'danger', $customerLink('inactive')],
            ],
            'moneyCards' => [
                ['Total Billed', $billed, 'bi-receipt', 'primary', route('admin.invoices.index', ['from' => $from, 'to' => $to])],
                ['Collected', $collected, 'bi-cash-coin', 'success', route('admin.payments.index', ['from' => $from, 'to' => $to])],
                ['Outstanding', max(0, $billed - $collected), 'bi-hourglass-split', 'warning', route('admin.reports.billing', ['from' => substr($from, 0, 7), 'to' => substr($to, 0, 7)])],
                ['Overdue', $overdue, 'bi-exclamation-triangle', 'danger', route('admin.invoices.index', ['status' => 'overdue', 'from' => $from, 'to' => $to])],
            ],
            'plans' => Plan::withCount('accounts')->get(),
        ]);
    }
}
