<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\UserNotification;
use App\Models\Warranty;
use App\Support\PlanUsage;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private function cleanDate(?string $value): ?string
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    public function __invoke(Request $request)
    {
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if (! $user->hasPermission('dashboard.view')) {
            foreach ([['products.view', 'products.index'], ['notifications.view', 'notifications.index']] as [$permission, $route]) {
                if ($user->hasPermission($permission)) {
                    return redirect()->route($route);
                }
            }
            return redirect()->route('profile.edit');          // my profile is open to everybody
        }

        // ---- overview: from - to = the date the product / document was added
        $from = $this->cleanDate($request->from);
        $to   = $this->cleanDate($request->to);

        $items = fn () => Product::query()->where('is_active', true)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));

        // the numbers are products / documents, exactly what the product list shows for the same filter
        $stats = [
            'total'    => $items()->count(),
            'active'   => $items()->status('active')->count(),
            'expiring' => $items()->status('expiring')->count(),
            'expired'  => $items()->status('expired')->count(),
        ];

        // each card opens the product list with the same status and dates (only if the user may see the list)
        $canList = $user->hasPermission('products.view');
        $link = fn (?string $status = null) => $canList
            ? route('products.index', array_filter(['status' => $status, 'from' => $from, 'to' => $to]))
            : null;

        $cards = [
            ['Products & Documents', $stats['total'], 'bi-box-seam', 'primary', $link()],
            ['Active', $stats['active'], 'bi-shield-check', 'success', $link('active')],
            ['Expiring Soon', $stats['expiring'], 'bi-clock-history', 'warning', $link('expiring')],
            ['Expired', $stats['expired'], 'bi-x-circle', 'danger', $link('expired')],
        ];

        $today = today()->toDateString();

        $upcoming = Warranty::with('product')->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->whereBetween('end_date', [$today, today()->addDays(90)->toDateString()])
            ->orderBy('end_date')->limit(100)->get();

        $recent = Product::with(['category', 'warranties'])->latest()->limit(20)->get();
        $notifications = UserNotification::where('user_id', $user->id)->latest()->limit(30)->get();

        return view('dashboard', [
            'cards' => $cards, 'from' => $from, 'to' => $to,
            'upcoming' => $upcoming, 'recent' => $recent, 'notifications' => $notifications,
            'usage' => PlanUsage::forAccount($user->account),
        ]);
    }
}
