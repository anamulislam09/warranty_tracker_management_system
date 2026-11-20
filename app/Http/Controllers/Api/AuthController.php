<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AccountResource;
use App\Http\Resources\UserResource;
use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\PlanUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Login with email + password -> a Sanctum token (one per phone). The app sends it as:   Authorization: Bearer <token>
 * Logging out deletes the token on the server, so a lost phone can be cut off (see sessions()).
 */
class AuthController extends ApiController
{
    /** Same rules as the website's registration. The account waits for the admin's approval. */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|min:2|max:100',
            'email'        => 'required|email|max:150|unique:users,email|unique:accounts,email',
            'mobile'       => 'nullable|string|max:30',
            'country_code' => ['required', Rule::in(array_keys(config('warranty.countries')))],
            'language'     => 'required|in:en,bn',
            'account_type' => 'required|in:personal,family,business',
            'payment_schedule_id' => ['required', Rule::exists('payment_schedules', 'id')->where('is_active', true)],
            'password'     => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        DB::transaction(function () use ($data) {
            $account = Account::create([
                'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['mobile'] ?? null,
                'country_code' => $data['country_code'], 'account_type' => $data['account_type'],
                'payment_schedule_id' => $data['payment_schedule_id'], 'status' => 'pending',
                'plan_id' => Plan::where('is_active', true)->orderBy('monthly_price')->value('id'),
            ]);

            User::create([
                'account_id' => $account->id, 'name' => $data['name'], 'email' => $data['email'],
                'mobile' => $data['mobile'] ?? null, 'country_code' => $data['country_code'],
                'language' => $data['language'], 'password' => $data['password'], 'role' => 'owner', 'status' => 'active',
            ]);
        });

        return $this->created(['email' => $data['email'], 'status' => 'pending'],
            'Registration successful. Your account will be activated after admin approval.');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'nullable|string|max:100',          // shown in "my devices", e.g. "Samsung A54"
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->fail('Invalid email or password.', 'invalid_credentials');
        }
        if ($user->status !== 'active') {
            return $this->fail('Your user account is inactive.', 'account_inactive');
        }

        // The super admin has no customer (account). A customer user must belong to an ACTIVE account.
        $account = $user->account;
        if (! $user->isSuperAdmin()) {
            if (! $account || $account->status === 'pending') {
                return $this->fail('Your account is waiting for admin approval.', 'account_pending');
            }
            if ($account->status !== 'active') {
                return $this->fail($account->inactiveMessage(), 'account_inactive');
            }
        }

        $user->forceFill(['last_login_at' => now()])->save();

        // the super admin's phone is trusted for a shorter time than a customer's (default 7 days against 90)
        $minutes   = $user->isSuperAdmin() ? config('sanctum.admin_expiration') : config('sanctum.expiration');
        $expiresAt = $minutes ? now()->addMinutes((int) $minutes) : null;
        $token     = $user->createToken($data['device_name'] ?? 'android', ['*'], $expiresAt);

        return $this->ok([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => optional($expiresAt)->toIso8601String(),
            'user'       => $this->one(UserResource::class, $user->load('assignedRole')),
            'account'    => $account ? $this->one(AccountResource::class, $account->load('plan', 'schedule')) : null,   // null for the super admin
        ], 'Login successful.');
    }

    /** Logout this phone only. Send the fcm_token too, so this phone stops getting push messages. */
    public function logout(Request $request)
    {
        $user = $request->user();
        if ($request->filled('fcm_token')) {
            UserDevice::where('user_id', $user->id)->where('token', $request->input('fcm_token'))->delete();
        }
        $user->currentAccessToken()->delete();

        return $this->ok(null, 'Logged out.');
    }

    /** Logout every phone of this user. */
    public function logoutAll(Request $request)
    {
        $user = $request->user();
        UserDevice::where('user_id', $user->id)->delete();
        $user->tokens()->delete();

        return $this->ok(null, 'Logged out from all devices.');
    }

    /** The first call after the app starts: who am I, my permissions, my plan and its usage. */
    public function me(Request $request)
    {
        $user = $request->user()->load('assignedRole', 'account.plan', 'account.schedule');

        // the super admin has no customer: account and usage are null, there are no notifications
        return $this->ok([
            'user'                 => $this->one(UserResource::class, $user),
            'account'              => $user->account ? $this->one(AccountResource::class, $user->account) : null,
            'usage'                => $user->account ? PlanUsage::forAccount($user->account) : null,
            'unread_notifications' => $user->isSuperAdmin() ? 0 : $user->unreadCount(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|min:2|max:100',
            'mobile'       => 'nullable|string|max:30',
            'country_code' => ['required', Rule::in(array_keys(config('warranty.countries')))],
            'timezone'     => ['required', Rule::in(array_keys(config('warranty.timezones')))],
            'language'     => 'required|in:en,bn',
        ]);

        $request->user()->update($data);

        return $this->ok($this->one(UserResource::class, $request->user()->fresh('assignedRole')), 'Profile updated.');
    }

    /** After a password change every OTHER phone is logged out. */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();
        $user->update(['password' => $request->password]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return $this->ok(null, 'Password changed. Your other devices were logged out.');
    }

    /** My logged-in devices. */
    public function sessions(Request $request)
    {
        $currentId = $request->user()->currentAccessToken()->id;

        return $this->ok($request->user()->tokens()->latest('last_used_at')->get()->map(fn ($t) => [
            'id'           => $t->id,
            'device_name'  => $t->name,
            'last_used_at' => optional($t->last_used_at)->toIso8601String(),
            'created_at'   => $t->created_at->toIso8601String(),
            'expires_at'   => optional($t->expires_at)->toIso8601String(),
            'is_current'   => $t->id === $currentId,
        ])->values());
    }

    /** Logout one device from far away (a lost phone). */
    public function revokeSession(Request $request, int $id)
    {
        $deleted = $request->user()->tokens()->where('id', $id)->delete();

        return $deleted ? $this->ok(null, 'Device logged out.') : $this->fail('Not found.', 'not_found');
    }
}
