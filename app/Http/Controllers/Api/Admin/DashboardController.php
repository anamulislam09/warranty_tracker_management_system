<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Account;
use App\Models\AccountStatusLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Http\Request;

/**
 * The super admin's dashboard: customers and money. The same numbers as the website.
 *   - the numbers follow the from / to dates (default: this month and the 3 months before it)
 *   - the chart numbers ALWAYS show this month and the 3 months before it
 */
class DashboardController extends ApiController
{
    private function cleanDate(?string $value): ?string
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    public function show(Request $request)
    {
        // 1) the dates
        $isDefault = ! $request->filled('from') && ! $request->filled('to');
        $from = $this->cleanDate($request->query('from')) ?? today()->startOfMonth()->subMonthsNoOverflow(3)->toDateString();
        $to   = $this->cleanDate($request->query('to')) ?? today()->endOfMonth()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        // 2) customers registered in the dates, counted by status
        $byStatus = Account::query()->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        // 3) money: the bills issued in the dates (cancelled bills are not counted)
        $bills = function () use ($from, $to) {
            return Invoice::where('status', '!=', 'cancelled')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to);
        };
        $billed    = (float) $bills()->sum('amount');
        $collected = (float) $bills()->sum('paid_amount');
        $overdue   = (float) $bills()->overdue()->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as d')->value('d');

        // 4) the charts: this month and the 3 months before
        $months = collect([3, 2, 1, 0])->map(fn ($i) => today()->startOfMonth()->subMonthsNoOverflow($i));
        $first  = $months->first()->copy()->startOfDay();
        $last   = today()->endOfMonth();
        $key    = fn ($date) => $date->format('Y-m');

        $newByMonth = Account::whereBetween('created_at', [$first, $last])->pluck('created_at')->groupBy($key)->map->count();
        $inactiveByMonth = AccountStatusLog::where('to_status', 'inactive')->whereBetween('created_at', [$first, $last])
            ->pluck('created_at')->groupBy($key)->map->count();
        $collectedByMonth = Payment::whereBetween('paid_on', [$first->toDateString(), $last->toDateString()])
            ->get(['paid_on', 'amount'])->groupBy(fn ($p) => $key($p->paid_on))->map(fn ($g) => round($g->sum('amount'), 2));

        $labels = $months->map(fn ($m) => $m->format('M Y'))->all();
        $new = $months->map(fn ($m) => (int) ($newByMonth[$key($m)] ?? 0))->all();
        $inactive = $months->map(fn ($m) => (int) ($inactiveByMonth[$key($m)] ?? 0))->all();
        $money = $months->map(fn ($m) => (float) ($collectedByMonth[$key($m)] ?? 0))->all();

        return $this->ok([
            'range' => ['from' => $from, 'to' => $to, 'is_default' => $isDefault],
            'customers' => [
                'total'    => (int) $byStatus->sum(),
                'pending'  => (int) ($byStatus['pending'] ?? 0),
                'active'   => (int) ($byStatus['active'] ?? 0),
                'inactive' => (int) ($byStatus['inactive'] ?? 0),
            ],
            'money' => [
                'billed'      => round($billed, 2),
                'collected'   => round($collected, 2),
                'outstanding' => round(max(0, $billed - $collected), 2),
                'overdue'     => round($overdue, 2),
                'currency'    => 'BDT',
            ],
            'charts' => [
                'labels'             => $labels,
                'new_customers'      => $new,
                'inactive_customers' => $inactive,
                'money_collected'    => $money,
                'customers_by_status' => $byStatus->map(fn ($count, $status) => ['status' => $status, 'count' => (int) $count])->values(),
            ],
            'customers_per_plan' => Plan::withCount('accounts')->get()->map(fn ($p) => [
                'plan_id' => $p->id, 'plan_name' => $p->name, 'customers' => (int) $p->accounts_count,
            ])->values(),
        ]);
    }
}
