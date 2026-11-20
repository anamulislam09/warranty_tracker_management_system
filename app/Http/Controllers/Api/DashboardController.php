<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\NotificationResource;
use App\Http\Resources\ProductSummaryResource;
use App\Models\Product;
use App\Models\UserNotification;
use App\Models\Warranty;
use App\Support\PlanUsage;
use Illuminate\Http\Request;

/** The same numbers as the website's customer dashboard. */
class DashboardController extends ApiController
{
    private function cleanDate(?string $value): ?string
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $from = $this->cleanDate($request->query('from'));         // the date the product / document was added
        $to   = $this->cleanDate($request->query('to'));

        $items = fn () => Product::query()->where('is_active', true)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));

        $canProducts = $user->hasPermission('products.view');
        $canNotify   = $user->hasPermission('notifications.view');
        $today       = today()->toDateString();

        $upcoming = $canProducts
            ? Warranty::with('product')->whereHas('product', fn ($q) => $q->where('is_active', true))
                ->whereBetween('end_date', [$today, today()->addDays(90)->toDateString()])->orderBy('end_date')->limit(50)->get()
            : collect();

        $recent = $canProducts
            ? Product::with(['category', 'subcategory', 'warranties'])->withCount('documents')->latest()->limit(10)->get()
            : collect();

        $notifications = $canNotify ? UserNotification::where('user_id', $user->id)->latest()->limit(10)->get() : collect();

        return $this->ok([
            'range' => ['from' => $from, 'to' => $to],
            // these numbers are products / documents, exactly what the product list shows for the same status
            'cards' => [
                'total'    => $items()->count(),
                'active'   => $items()->status('active')->count(),
                'expiring' => $items()->status('expiring')->count(),
                'expired'  => $items()->status('expired')->count(),
            ],
            'usage' => PlanUsage::forAccount($user->account),
            'upcoming_expiries' => $upcoming->map(fn ($w) => [
                'warranty_id'  => $w->id, 'product_id' => $w->product_id,
                'product_name' => $w->product->name, 'item_type' => $w->product->item_type,
                'component'    => $w->component, 'end_date' => $w->end_date->toDateString(),
                'days_remaining' => $w->days_remaining, 'status' => $w->status,
            ])->values(),
            'recent_products'      => ProductSummaryResource::collection($recent)->resolve($request),
            'recent_notifications' => NotificationResource::collection($notifications)->resolve($request),
            'unread_notifications' => $canNotify ? $user->unreadCount() : 0,
        ]);
    }

    /** Only the plan usage (products / storage / users). */
    public function usage(Request $request)
    {
        return $this->ok(PlanUsage::forAccount($request->user()->account));
    }
}
