<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Models\Warranty;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = today()->toDateString();
        $soon  = today()->addDays(config('warranty.expiring_days'))->toDateString();
        $w     = fn () => Warranty::whereHas('product', fn ($q) => $q->where('is_active', true));

        $stats = [
            'accounts' => Account::count(),
            'pending'  => Account::where('status', 'pending')->count(),
            'active'   => Account::where('status', 'active')->count(),
            'inactive' => Account::where('status', 'inactive')->count(),
            'users'    => User::where('role', '!=', 'super_admin')->count(),
            'products' => Product::where('is_active', true)->count(),
            'billing_due'       => (float) Invoice::open()->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as due')->value('due'),
            'overdue_customers' => Account::whereHas('invoices', fn ($q) => $q->overdue())->count(),
            'w_active'   => $w()->currentlyValid()->count(),
            'w_expiring' => $w()->whereBetween('end_date', [$today, $soon])->count(),
            'w_expired'  => $w()->where('end_date', '<', $today)->count(),
        ];

        return view('admin.dashboard', [
            'stats'    => $stats,
            'pending'  => Account::with('plan')->where('status', 'pending')->latest()->get(),
            'latest'   => Account::with('plan')->latest()->limit(5)->get(),
            'plans'    => Plan::withCount('accounts')->get(),
            'expiring' => Warranty::with('product.account')->whereHas('product', fn ($q) => $q->where('is_active', true))
                            ->whereBetween('end_date', [$today, $soon])->orderBy('end_date')->limit(6)->get(),
        ]);
    }
}
