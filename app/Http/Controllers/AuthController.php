<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin() { return view('auth.login'); }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
        }

        $user = Auth::user();
        $error = null;

        if ($user->status !== 'active') {
            $error = 'Your user account is inactive.';
        } elseif (! $user->isSuperAdmin()) {
            $error = match (optional($user->account)->status) {
                'active' => null,
                'pending' => 'Your account is waiting for admin approval.',
                default => $user->account->inactiveMessage(),
            };
        }

        if ($error) {
            Auth::logout();
            return back()->withErrors(['email' => $error])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'))->with('success', 'Welcome back, ' . $user->name . '!');
    }

    public function showRegister() { return view('auth.register'); }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|min:2|max:100',
            'email'        => 'required|email|max:150|unique:users,email|unique:accounts,email',
            'mobile'       => 'nullable|string|max:30',
            'country_code' => 'required|in:' . implode(',', array_keys(config('warranty.countries'))),
            'language'     => 'required|in:en,bn',
            'account_type' => 'required',
            'billing_cycle' => ['required', \Illuminate\Validation\Rule::in(array_keys(config('billing.cycles')))],
            'password'     => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        DB::transaction(function () use ($data) {
            $account = Account::create([
                'name'         => $data['name'],
                'email'        => $data['email'],
                'phone'        => $data['mobile'] ?? null,
                'country_code' => $data['country_code'],
                'account_type' => $data['account_type'],
                'billing_cycle' => $data['billing_cycle'],
                'status'       => 'pending',                                   // waits for super admin approval
                'plan_id'      => Plan::where('is_active', true)->orderBy('monthly_price')->value('id'),
            ]);

            User::create([
                'account_id' => $account->id, 'name' => $data['name'], 'email' => $data['email'],
                'mobile' => $data['mobile'] ?? null, 'country_code' => $data['country_code'],
                'language' => $data['language'], 'password' => $data['password'],
                'role' => 'owner', 'status' => 'active',
            ]);
        });

        return redirect()->route('login')->with('success', 'Registration successful! Your account will be activated after admin approval.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'You have been logged out.');
    }
}
