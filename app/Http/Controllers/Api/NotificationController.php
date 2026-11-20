<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\NotificationResource;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        $page = UserNotification::where('user_id', $request->user()->id)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest()->paginate($this->perPage($request, 20, 50));

        return $this->paginated($page, NotificationResource::class, ['unread_count' => $request->user()->unreadCount()]);
    }

    /** For the badge on the bell icon. */
    public function unreadCount(Request $request)
    {
        return $this->ok(['unread_count' => $request->user()->unreadCount()]);
    }

    public function read(Request $request, int $id)
    {
        $notification = UserNotification::where('user_id', $request->user()->id)->find($id);
        if (! $notification) {
            return $this->fail('Not found.', 'not_found');
        }
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return $this->ok($this->one(NotificationResource::class, $notification), 'Marked as read.');
    }

    public function readAll(Request $request)
    {
        UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->ok(['unread_count' => 0], 'All notifications marked as read.');
    }
}
