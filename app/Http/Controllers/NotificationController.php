<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = UserNotification::where('user_id', $request->user()->id)->latest()->paginate(15);
        return view('notifications.index', compact('notifications'));
    }

    public function read(Request $request, UserNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => now()]);

        return $notification->product_id
            ? redirect()->route('products.show', $notification->product_id)
            : back();
    }

    public function readAll(Request $request)
    {
        UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return back()->with('success', 'All notifications marked as read.');
    }
}
