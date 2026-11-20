<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\User;
use App\Support\PlanUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Customers (accounts): the super admin creates, approves, edits, suspends and deletes them. Same rules as the website. */
class CustomerController extends ApiController
{
    private function rules(?Account $account = null): array
    {
        return [
            'name'         => 'required|string|max:150',
            'email'        => ['required', 'email', Rule::unique('accounts', 'email')->ignore($account)],
            'phone'        => 'nullable|string|max:30',
            'country_code' => ['required', Rule::in(array_keys(config('warranty.countries')))],
            'account_type' => 'required|in:personal,family,business',
            'plan_id'      => 'nullable|exists:plans,id',
            'payment_schedule_id' => ['required', Rule::exists('payment_schedules', 'id')],
            'status'       => 'required|in:pending,active,inactive',
            // Is the month of activation billed too? The admin MUST choose, until the billing has started.
            'bill_current_month' => [Rule::requiredIf(fn () => ! optional($account)->billing_starts_on), 'nullable', 'boolean'],
            'plan_ends_at' => 'nullable|date',
            'notes'        => 'nullable|string|max:1000',
        ];
    }

    /** One customer as a row of the list. */
    private function row(Account $a): array
    {
        return [
            'id'                  => $a->id,
            'name'                => $a->name,
            'email'               => $a->email,
            'phone'               => $a->phone,
            'country_code'        => $a->country_code,
            'account_type'        => $a->account_type,
            'status'              => $a->status,
            'plan'                => $a->plan ? ['id' => $a->plan->id, 'name' => $a->plan->name] : null,
            'payment_schedule'    => $a->schedule ? ['id' => $a->schedule->id, 'name' => $a->schedule->name] : null,
            'billing_starts_on'   => optional($a->billing_starts_on)->toDateString(),
            'bill_current_month'  => $a->bill_current_month,
            'plan_ends_at'        => optional($a->plan_ends_at)->toDateString(),
            'approved_at'         => optional($a->approved_at)->toIso8601String(),
            'created_at'          => optional($a->created_at)->toIso8601String(),
        ];
    }

    /** One customer with everything the details screen needs. */
    private function detail(Account $a): array
    {
        $a->load('plan', 'schedule', 'owner', 'users');
        $due = Invoice::where('account_id', $a->id)->open()->get();

        return $this->row($a) + [
            'notes' => $a->notes,
            'owner' => $a->owner ? ['id' => $a->owner->id, 'name' => $a->owner->name, 'email' => $a->owner->email, 'mobile' => $a->owner->mobile] : null,
            'users' => $a->users->map(fn ($u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'status' => $u->status,
            ])->values(),
            'products_count'  => $a->productCount(),
            'storage_used_mb' => round($a->storageUsedBytes() / 1048576, 1),
            'usage'           => PlanUsage::forAccount($a),
            'billing' => [
                'open_bills'  => $due->count(),
                'due_total'   => round($due->sum(fn ($i) => $i->balance), 2),
                'is_overdue'  => $a->isPaymentOverdue(),
            ],
        ];
    }

    public function index(Request $request)
    {
        $page = Account::with('plan', 'schedule')
            ->when($request->q, fn ($q, $t) => $q->where(fn ($q) => $q->where('name', 'like', "%$t%")->orWhere('email', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->plan_id, fn ($q, $p) => $q->where('plan_id', $p))
            ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))     // registered from
            ->when($request->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))       // registered until
            ->latest()->paginate($this->perPage($request, 10, 100));

        return $this->ok(
            $page->getCollection()->map(fn ($a) => $this->row($a))->values(),
            'OK',
            $this->pageMeta($page)
        );
    }

    public function show(int $id)
    {
        $account = Account::find($id);

        return $account ? $this->ok($this->detail($account)) : $this->fail('Customer not found.', 'not_found');
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'owner_name'     => 'required|string|max:100',
            'owner_email'    => 'required|email|unique:users,email',
            'owner_password' => ['required', Password::min(8)->letters()->numbers()],
        ]);

        // the customer and its owner user are saved together or not at all
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

        return $this->ok($this->detail($account->fresh()), 'Customer created successfully.');
    }

    public function update(Request $request, int $id)
    {
        $account = Account::find($id);
        if (! $account) {
            return $this->fail('Customer not found.', 'not_found');
        }

        $data = $request->validate($this->rules($account));
        if ($data['status'] === 'active' && ! $account->approved_at) {
            $data['approved_at'] = now();
        }
        if ($account->billing_starts_on) {
            unset($data['bill_current_month']);              // billing has started: the choice is final
        }
        $account->update($data);

        $message = $account->wasChanged('plan_id')
            ? "Customer updated successfully. The new plan is billed from next month's bill. To charge it for this month too, edit this month's bill."
            : 'Customer updated successfully.';

        return $this->ok($this->detail($account->fresh()), $message);
    }

    /** Quick status change: approve a pending customer, suspend or activate one. */
    public function status(Request $request, int $id)
    {
        $account = Account::find($id);
        if (! $account) {
            return $this->fail('Customer not found.', 'not_found');
        }

        $data = $request->validate([
            'status'             => 'required|in:pending,active,inactive',
            'bill_current_month' => 'nullable|boolean',
        ]);

        // A customer whose billing has not started needs the admin's choice: is the current month billed too?
        if ($data['status'] === 'active' && ! $account->billing_starts_on) {
            if (array_key_exists('bill_current_month', $data) && $data['bill_current_month'] !== null) {
                $account->bill_current_month = (bool) $data['bill_current_month'];
            } elseif ($account->bill_current_month === null) {
                return $this->fail('Please choose "Bill the current month too?" (Yes or No) before you activate this customer. Send bill_current_month as true or false.', 'billing_choice_required');
            }
        }

        $account->status = $data['status'];
        if ($data['status'] === 'active' && ! $account->approved_at) {
            $account->approved_at = now();
        }
        $account->save();

        return $this->ok($this->detail($account->fresh()), "Customer is now {$data['status']}.");
    }

    public function destroy(int $id)
    {
        $account = Account::find($id);
        if (! $account) {
            return $this->fail('Customer not found.', 'not_found');
        }
        $account->delete();                                  // its users, products, warranties and files are removed too

        return $this->ok(null, 'Customer deleted successfully.');
    }
}
