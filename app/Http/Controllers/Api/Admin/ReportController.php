<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\BillingReportController;
use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\Request;

/**
 * The billing report. The numbers come from the website's own report code (BillingReportController::data),
 * so the app and the website always show the same figures. The CSV files are /admin/reports/billing/export (routes/api.php).
 */
class ReportController extends ApiController
{
    public function billing(Request $request)
    {
        // ?from=2026-01&to=2026-12 (months), ?account_id=, ?schedule_id=
        $d = app(BillingReportController::class)->data($request);

        // the unpaid bills, customer by customer, in pages
        $perPage = max(1, min(100, (int) $request->input('dues_per_page', 20)));
        $page    = max(1, (int) $request->input('dues_page', 1));
        $dues    = $d['dues'];

        return $this->ok([
            'range' => ['from' => $d['from']->format('Y-m'), 'to' => $d['to']->format('Y-m')],
            'summary' => [
                'billed'      => round((float) $d['sum']->billed, 2),
                'collected'   => round((float) $d['sum']->collected, 2),
                'outstanding' => round((float) $d['sum']->billed - (float) $d['sum']->collected, 2),
                'overdue'     => round((float) $d['overdueAmount'], 2),
                'currency'    => 'BDT',
            ],
            'by_schedule' => $d['bySchedule']->map(fn ($r) => [
                'schedule_name' => $r->schedule_name, 'customers' => (int) $r->customers, 'bills' => (int) $r->bills,
                'billed' => round((float) $r->billed, 2), 'collected' => round((float) $r->collected, 2),
                'due' => round((float) $r->billed - (float) $r->collected, 2),
            ])->values(),
            // one row per month: a bill is counted in the month its period STARTS
            'by_month' => $d['monthly']->map(fn ($r) => [
                'month' => $r->billing_month->format('Y-m'), 'label' => $r->billing_month->format('F Y'),
                'bills' => (int) $r->invoices, 'paid_bills' => (int) $r->paid_count,
                'billed' => round((float) $r->billed, 2), 'collected' => round((float) $r->collected, 2),
                'due' => round((float) $r->billed - (float) $r->collected, 2),
                'collected_percent' => $r->billed > 0 ? (int) round($r->collected / $r->billed * 100) : 0,
            ])->values(),
            'dues' => $dues->forPage($page, $perPage)->map(fn ($row) => [
                'account' => [
                    'id' => $row->account->id, 'name' => $row->account->name, 'email' => $row->account->email,
                    'status' => $row->account->status, 'payment_schedule' => $row->account->cycleLabel(),
                ],
                'paid'            => round((float) $row->paid, 2),
                'balance'         => round((float) $row->balance, 2),
                'oldest_due_date' => $row->oldest->toDateString(),
                'is_overdue'      => (bool) $row->overdue,
                'invoices'        => $row->invoices->map(fn ($i) => [
                    'id' => $i->id, 'invoice_no' => $i->invoice_no, 'period_label' => $i->period_label,
                    'balance' => round($i->balance, 2), 'due_date' => $i->due_date->toDateString(), 'is_overdue' => (bool) $i->is_overdue,
                ])->values(),
            ])->values(),
        ], 'OK', [
            'dues_total' => $dues->count(), 'dues_balance' => round((float) $dues->sum('balance'), 2),
            'dues_page' => $page, 'dues_per_page' => $perPage, 'dues_has_more' => $dues->count() > $page * $perPage,
        ]);
    }
}
