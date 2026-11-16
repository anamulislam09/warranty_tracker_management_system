<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\PaymentSchedule;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class BillingReportController extends Controller
{
    private function data(Request $request): array
    {
        $to   = BillingService::monthFromInput($request->to) ?? today()->startOfMonth();
        $from = BillingService::monthFromInput($request->from) ?? $to->copy()->subMonthsNoOverflow(11);
        if ($from->gt($to)) [$from, $to] = [$to, $from];

        $accountId  = $request->account_id;
        $scheduleId = $request->schedule_id;
        $scope = fn ($q) => $q
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->when($scheduleId, fn ($q) => $q->where('payment_schedule_id', $scheduleId));

        // bills of the selected months (cancelled ones are ignored)
        $range = fn () => $scope(Invoice::where('status', '!=', 'cancelled')
            ->whereBetween('billing_month', [$from->toDateString(), $to->toDateString()]));

        $sum = $range()->selectRaw('COALESCE(SUM(amount),0) as billed, COALESCE(SUM(paid_amount),0) as collected')->first();

        $monthly = $range()->selectRaw("billing_month, MAX(period_end) as period_end, MAX(months) as months, COUNT(*) as invoices,
                SUM(amount) as billed, SUM(paid_amount) as collected,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count")
            ->groupBy('billing_month')->orderByDesc('billing_month')->get();

        // by payment schedule: Monthly / Quarterly / Half-yearly / Yearly ...
        $bySchedule = $range()->selectRaw("COALESCE(schedule_name, 'Other') as schedule_name, COUNT(*) as bills,
                COUNT(DISTINCT account_id) as customers, SUM(amount) as billed, SUM(paid_amount) as collected")
            ->groupBy('schedule_name')->orderBy('schedule_name')->get();

        $overdueAmount = $scope(Invoice::overdue()->whereBetween('billing_month', [$from->toDateString(), $to->toDateString()]))
            ->selectRaw('COALESCE(SUM(amount - paid_amount),0) as due')->value('due');

        // dues: every unpaid bill up to the "to" month, grouped by customer
        $open = $scope(Invoice::with('account.schedule')->open()->whereDate('billing_month', '<=', $to->toDateString()))
            ->orderBy('billing_month')->get();

        $dues = $open->groupBy('account_id')->map(function ($list) {
            return (object) [
                'account'  => $list->first()->account,
                'invoices' => $list,
                'balance'  => $list->sum(fn ($i) => $i->balance),
                'paid'     => $list->sum(fn ($i) => (float) $i->paid_amount),
                'oldest'   => $list->min('due_date'),
                'overdue'  => $list->contains(fn ($i) => $i->is_overdue),
            ];
        })->sortByDesc('balance')->values();

        return compact('from', 'to', 'accountId', 'scheduleId', 'sum', 'monthly', 'bySchedule', 'overdueAmount', 'dues');
    }

    /** Laravel paginator for a list that was already built in memory (the CSV export still gets the full list). */
    private function paginate($items, int $perPage, string $pageName, Request $request): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage($pageName);

        return (new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page,
            ['path' => $request->url(), 'pageName' => $pageName]
        ))->withQueryString();
    }

    public function index(Request $request)
    {
        $data = $this->data($request);

        return view('admin.reports.billing', $data + [
            'duesPage'   => $this->paginate($data['dues'], 5, 'dues_page', $request),        // 5 customers per page
            'periodPage' => $this->paginate($data['monthly'], 12, 'period_page', $request),  // 12 months per page
            'months'     => BillingService::monthOptions(36, 2),
            'accounts'   => Account::orderBy('name')->get(['id', 'name']),
            'schedules'  => PaymentSchedule::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request)
    {
        $d    = $this->data($request);
        $type = $request->type === 'monthly' ? 'monthly' : 'dues';
        $name = "billing-{$type}-" . today()->format('Ymd') . '.csv';
        $fmt  = BillingService::dateFormat();

        return response()->streamDownload(function () use ($d, $type, $fmt) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");                                   // so Excel shows UTF-8 correctly

            if ($type === 'monthly') {
                fputcsv($out, ['Period starts', 'Bills', 'Paid bills', 'Billed', 'Collected', 'Due']);
                foreach ($d['monthly'] as $m) {
                    fputcsv($out, [$m->billing_month->format('F Y'), $m->invoices, $m->paid_count,
                        number_format($m->billed, 2, '.', ''), number_format($m->collected, 2, '.', ''),
                        number_format($m->billed - $m->collected, 2, '.', '')]);
                }
            } else {
                fputcsv($out, ['Customer', 'Email', 'Payment schedule', 'Account status', 'Periods due', 'Paid so far', 'Total due', 'Oldest due date']);
                foreach ($d['dues'] as $row) {
                    fputcsv($out, [
                        $row->account->name, $row->account->email, $row->account->cycleLabel(), $row->account->status,
                        $row->invoices->map(fn ($i) => $i->period_label . ' (' . number_format($i->balance, 2, '.', '') . ')')->join('; '),
                        number_format($row->paid, 2, '.', ''), number_format($row->balance, 2, '.', ''), $row->oldest->format($fmt),
                    ]);
                }
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
