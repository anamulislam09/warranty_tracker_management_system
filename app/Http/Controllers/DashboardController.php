<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\UserNotification;
use App\Models\Warranty;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $today = today()->toDateString();
        $soon  = today()->addDays(config('warranty.expiring_days'))->toDateString();
        $w     = fn () => Warranty::whereHas('product', fn ($q) => $q->where('is_active', true));

        $stats = [
            'products' => Product::where('is_active', true)->count(),
            'active'   => $w()->currentlyValid()->count(),
            'expiring' => $w()->whereBetween('end_date', [$today, $soon])->count(),
            'expired'  => $w()->where('end_date', '<', $today)->count(),
        ];

        $upcoming = Warranty::with('product')->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->whereBetween('end_date', [$today, today()->addDays(90)->toDateString()])
            ->orderBy('end_date')->limit(6)->get();

        $recent = Product::with(['category', 'warranties'])->latest()->limit(5)->get();
        $notifications = UserNotification::where('user_id', $user->id)->latest()->limit(5)->get();

        return view('dashboard', compact('stats', 'upcoming', 'recent', 'notifications'));
    }
}
