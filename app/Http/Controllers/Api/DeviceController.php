<?php

namespace App\Http\Controllers\Api;

use App\Models\UserDevice;
use Illuminate\Http\Request;

/**
 * The phone's Firebase (FCM) token, so the server can send push messages to it.
 * The app sends it after login and every time Firebase gives a new token.
 * (Sending the push messages is not built yet: see the documentation.)
 */
class DeviceController extends ApiController
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token'       => 'required|string|max:255',
            'platform'    => 'nullable|in:android,ios',
            'device_name' => 'nullable|string|max:100',
        ]);

        // one token belongs to one user: a phone that logs in as someone else moves to the new user
        UserDevice::updateOrCreate(['token' => $data['token']], [
            'user_id' => $request->user()->id, 'platform' => $data['platform'] ?? 'android',
            'device_name' => $data['device_name'] ?? null, 'last_used_at' => now(),
        ]);

        return $this->ok(null, 'Device saved.');
    }

    public function destroy(Request $request)
    {
        $request->validate(['token' => 'required|string|max:255']);
        UserDevice::where('user_id', $request->user()->id)->where('token', $request->input('token'))->delete();

        return $this->ok(null, 'Device removed.');
    }
}
