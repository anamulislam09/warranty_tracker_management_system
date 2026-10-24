<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|min:2|max:100',
            'mobile'       => 'nullable|string|max:30',
            'country_code' => ['required', Rule::in(array_keys(config('warranty.countries')))],
            'timezone'     => ['required', Rule::in(array_keys(config('warranty.timezones')))],
            'language'     => 'required|in:en,bn',
        ]);

        $request->user()->update($data);
        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->update(['password' => $request->password]);
        return back()->with('success', 'Password changed.');
    }
}
