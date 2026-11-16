<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PaymentSchedule;
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
            'payment_schedule_id' => ['required', Rule::exists('payment_schedules', 'id')],
            'status' => 'required|in:pending,active,inactive',
            // is the month of activation billed too? The admin MUST choose, until the billing has started
            'bill_current_month' => [Rule::requiredIf(fn () => ! optional($account)->billing_starts_on), 'nullable', 'boolean'],
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
            ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))      // registered from (dashboard link)
            ->when($request->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))        // registered until
            ->latest()->paginate(10)->withQueryString();

        return view('admin.accounts.index', ['accounts' => $accounts, 'plans' => Plan::all()]);
    }

    public function create()
    {
        return view('admin.accounts.form', [
            'account' => new Account(['status' => 'pending', 'country_code' => 'BD', 'account_type' => 'personal', 'payment_schedule_id' => optional(PaymentSchedule::active()->first())->id]),
            'plans' => Plan::where('is_active', true)->get(),
            'schedules' => PaymentSchedule::active()->get(),
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
        return view('admin.accounts.form', ['account' => $account, 'plans' => Plan::all(), 'schedules' => PaymentSchedule::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, Account $account)
    {
        $data = $request->validate($this->rules($account));
        if ($data['status'] === 'active' && ! $account->approved_at) $data['approved_at'] = now();
        if ($account->billing_starts_on) unset($data['bill_current_month']);       // billing has started: the choice is final

        $account->update($data);
        $message = $account->wasChanged('plan_id')
            ? "Customer updated. The new plan is billed from next month's bill. To charge it for this month too, edit this month's bill."
            : 'Customer updated.';

        return redirect()->route('admin.accounts.show', $account)->with('success', $message);
    }

    public function status(Request $request, Account $account)
    {
        $data = $request->validate(['status' => 'required|in:pending,active,inactive']);

        // a customer whose billing has not started needs the admin's choice first: is this month billed too?
        if ($data['status'] === 'active' && ! $account->billing_starts_on && $account->bill_current_month === null) {
            return redirect()->route('admin.accounts.edit', $account)
                ->with('error', 'First choose "Bill the current month too?" (Yes / No), set the status to Active and save.');
        }

        $account->update($data + ($data['status'] === 'active' && ! $account->approved_at ? ['approved_at' => now()] : []));

        return back()->with('success', "Customer is now {$data['status']}.");
    }

    public function destroy(Account $account)
    {
        $account->delete(); // users, products, warranties, documents are removed by DB cascade
        return redirect()->route('admin.accounts.index')->with('success', 'Customer deleted.');
    }
}
