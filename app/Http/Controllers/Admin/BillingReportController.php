<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Invoice;
use App\Services\BillingService;
use Illuminate\Http\Request;

class BillingReportController extends Controller
{
    private function data(Request $request): array
    {
        $to   = BillingService::monthFromInput($request->to) ?? today()->startOfMonth();
        $from = BillingService::monthFromInput($request->from) ?? $to->copy()->subMonthsNoOverflow(11);
        if ($from->gt($to)) [$from, $to] = [$to, $from];

        $accountId = $request->account_id;
        $scope = fn ($q) => $q->when($accountId, fn ($q) => $q->where('account_id', $accountId));

        // ---- summary + month-wise (bills of the selected months, cancelled ones ignored)
        $range = fn () => $scope(Invoice::where('status', '!=', 'cancelled')
            ->whereBetween('billing_month', [$from->toDateString(), $to->toDateString()]));

        $sum = $range()->selectRaw('COALESCE(SUM(amount),0) as billed, COALESCE(SUM(paid_amount),0) as collected')->first();

        $monthly = $range()->selectRaw("billing_month, COUNT(*) as invoices, SUM(amount) as billed, SUM(paid_amount) as collected,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count")
            ->groupBy('billing_month')->orderByDesc('billing_month')->get();

        $overdueAmount = $scope(Invoice::overdue()->whereBetween('billing_month', [$from->toDateString(), $to->toDateString()]))
            ->selectRaw('COALESCE(SUM(amount - paid_amount),0) as due')->value('due');

        // ---- dues: every unpaid bill up to the "to" month, grouped by customer
        $open = $scope(Invoice::with('account')->open()->whereDate('billing_month', '<=', $to->toDateString()))
            ->orderBy('billing_month')->get();

        $dues = $open->groupBy('account_id')->map(function ($list) {
            return (object) [
                'account'  => $list->first()->account,
                'invoices' => $list,
                'balance'  => $list->sum(fn ($i) => $i->balance),
                'oldest'   => $list->min('due_date'),
                'overdue'  => $list->contains(fn ($i) => $i->is_overdue),
            ];
        })->sortByDesc('balance')->values();

        return compact('from', 'to', 'accountId', 'sum', 'monthly', 'overdueAmount', 'dues');
    }

    public function index(Request $request)
    {
        return view('admin.reports.billing', $this->data($request) + [
            'months'   => BillingService::monthOptions(36, 2),
            'accounts' => Account::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request)
    {
        $d    = $this->data($request);
        $type = $request->type === 'monthly' ? 'monthly' : 'dues';
        $name = "billing-{$type}-" . today()->format('Ymd') . '.csv';
        $fmt  = config('warranty.date_format');

        return response()->streamDownload(function () use ($d, $type, $fmt) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");                                   // so Excel shows UTF-8 correctly

            if ($type === 'monthly') {
                fputcsv($out, ['Month', 'Bills', 'Paid bills', 'Billed', 'Collected', 'Due']);
                foreach ($d['monthly'] as $m) {
                    fputcsv($out, [$m->billing_month->format('F Y'), $m->invoices, $m->paid_count,
                        number_format($m->billed, 2, '.', ''), number_format($m->collected, 2, '.', ''),
                        number_format($m->billed - $m->collected, 2, '.', '')]);
                }
            } else {
                fputcsv($out, ['Customer', 'Email', 'Account status', 'Months due', 'Total due', 'Oldest due date']);
                foreach ($d['dues'] as $row) {
                    fputcsv($out, [
                        $row->account->name, $row->account->email, $row->account->status,
                        $row->invoices->map(fn ($i) => $i->billing_month->format('M Y') . ' (' . number_format($i->balance, 2, '.', '') . ')')->join('; '),
                        number_format($row->balance, 2, '.', ''), $row->oldest->format($fmt),
                    ]);
                }
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
