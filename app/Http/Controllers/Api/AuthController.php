<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required', 'device_name' => 'nullable|string']);

        $user = User::where('email', $request->email)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }
        if ($user->status !== 'active' || (! $user->isSuperAdmin() && optional($user->account)->status !== 'active')) {
            return response()->json(['message' => 'Your account is not active.'], 403);
        }

        return response()->json([
            'token' => $user->createToken($request->input('device_name', 'android'))->plainTextToken,
            'user'  => $user->only('id', 'name', 'email', 'role', 'permissions'),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }
}
