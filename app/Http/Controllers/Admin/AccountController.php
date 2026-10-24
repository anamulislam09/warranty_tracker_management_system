<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    private function rules(?Account $account = null): array
    {
        return [
            'name' => 'required|string|max:150',
            'email' => ['required', 'email', Rule::unique('accounts', 'email')->ignore($account)],
            'phone' => 'nullable|string|max:30',
            'country_code' => ['required', Rule::in(array_keys(config('warranty.countries')))],
            'account_type' => 'required|in:personal,family,business',
            'plan_id' => 'nullable|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,annual',
            'status' => 'required|in:pending,active,inactive',
            'plan_ends_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function index(Request $request)
    {
        $accounts = Account::with('plan')
            ->when($request->q, fn ($q, $t) => $q->where(fn ($q) => $q->where('name', 'like', "%$t%")->orWhere('email', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->plan_id, fn ($q, $p) => $q->where('plan_id', $p))
            ->latest()->paginate(10)->withQueryString();

        return view('admin.accounts.index', ['accounts' => $accounts, 'plans' => Plan::all()]);
    }

    public function create()
    {
        return view('admin.accounts.form', [
            'account' => new Account(['status' => 'pending', 'country_code' => 'BD', 'account_type' => 'personal', 'billing_cycle' => 'monthly']),
            'plans' => Plan::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'owner_name' => 'required|string|max:100',
            'owner_email' => 'required|email|unique:users,email',
            'owner_password' => ['required', Password::min(8)->letters()->numbers()],
        ]);

        $account = DB::transaction(function () use ($data) {
            $account = Account::create(Arr::except($data, ['owner_name', 'owner_email', 'owner_password'])
                + ['approved_at' => $data['status'] === 'active' ? now() : null]);

            User::create([
                'account_id' => $account->id, 'name' => $data['owner_name'], 'email' => $data['owner_email'],
                'password' => $data['owner_password'], 'mobile' => $data['phone'] ?? null,
                'country_code' => $data['country_code'], 'role' => 'owner', 'status' => 'active',
            ]);
            return $account;
        });

        return redirect()->route('admin.accounts.show', $account)->with('success', 'Customer created.');
    }

    public function show(Account $account)
    {
        $account->load('plan', 'owner', 'users');
        return view('admin.accounts.show', compact('account'));
    }

    public function edit(Account $account)
    {
        return view('admin.accounts.form', ['account' => $account, 'plans' => Plan::all()]);
    }

    public function update(Request $request, Account $account)
    {
        $data = $request->validate($this->rules($account));
        if ($data['status'] === 'active' && ! $account->approved_at) $data['approved_at'] = now();

        $account->update($data);
        $message = $account->wasChanged('plan_id')
            ? "Customer updated. The new plan is billed from next month's bill. To charge it for this month too, edit this month's bill."
            : 'Customer updated.';

        return redirect()->route('admin.accounts.show', $account)->with('success', $message);
    }

    public function status(Request $request, Account $account)
    {
        $data = $request->validate(['status' => 'required|in:pending,active,inactive']);
        $account->update($data + ($data['status'] === 'active' && ! $account->approved_at ? ['approved_at' => now()] : []));

        return back()->with('success', "Customer is now {$data['status']}.");
    }

    public function destroy(Account $account)
    {
        $account->delete(); // users, products, warranties, documents are removed by DB cascade
        return redirect()->route('admin.accounts.index')->with('success', 'Customer deleted.');
    }
}
