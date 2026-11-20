<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Plan;
use Illuminate\Http\Request;

/** Subscription plans. Only the MONTHLY price is kept: a bill is monthly price x months - the schedule's discount. */
class PlanController extends ApiController
{
    private function rules(): array
    {
        return [
            'name'             => 'required|string|max:100',
            'description'      => 'nullable|string|max:255',
            'monthly_price'    => 'required|numeric|min:0',
            'max_products'     => 'required|integer|min:0',          // 0 = unlimited
            'storage_limit_mb' => 'required|integer|min:1',
            'max_users'        => 'required|integer|min:1',
            'reminder_enabled'        => 'nullable|boolean',
            'document_upload_enabled' => 'nullable|boolean',
            'is_active'               => 'nullable|boolean',
        ];
    }

    /** true / false from the request; when it is not sent, keep the old value (or the default for a new plan). */
    private function flag(Request $request, string $key, bool $old): bool
    {
        return $request->has($key) ? $request->boolean($key) : $old;
    }

    private function row(Plan $p): array
    {
        return [
            'id'               => $p->id,
            'name'             => $p->name,
            'description'      => $p->description,
            'monthly_price'    => (float) $p->monthly_price,
            'max_products'     => (int) $p->max_products,
            'storage_limit_mb' => (int) $p->storage_limit_mb,
            'max_users'        => (int) $p->max_users,
            'reminder_enabled'        => (bool) $p->reminder_enabled,
            'document_upload_enabled' => (bool) $p->document_upload_enabled,
            'is_active'        => (bool) $p->is_active,
            'customers_count'  => (int) ($p->accounts_count ?? $p->accounts()->count()),
        ];
    }

    public function index()
    {
        return $this->ok(Plan::withCount('accounts')->orderBy('monthly_price')->get()->map(fn ($p) => $this->row($p))->values());
    }

    public function show(int $id)
    {
        $plan = Plan::withCount('accounts')->find($id);

        return $plan ? $this->ok($this->row($plan)) : $this->fail('Plan not found.', 'not_found');
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $plan = Plan::create([
            'name' => $data['name'], 'description' => $data['description'] ?? null,
            'monthly_price' => $data['monthly_price'], 'max_products' => $data['max_products'],
            'storage_limit_mb' => $data['storage_limit_mb'], 'max_users' => $data['max_users'],
            'reminder_enabled' => $this->flag($request, 'reminder_enabled', true),
            'document_upload_enabled' => $this->flag($request, 'document_upload_enabled', true),
            'is_active' => $this->flag($request, 'is_active', true),
        ]);

        return $this->ok($this->row($plan->loadCount('accounts')), 'Plan created successfully.');
    }

    public function update(Request $request, int $id)
    {
        $plan = Plan::find($id);
        if (! $plan) {
            return $this->fail('Plan not found.', 'not_found');
        }
        $data = $request->validate($this->rules());

        $plan->update([
            'name' => $data['name'], 'description' => $data['description'] ?? null,
            'monthly_price' => $data['monthly_price'], 'max_products' => $data['max_products'],
            'storage_limit_mb' => $data['storage_limit_mb'], 'max_users' => $data['max_users'],
            'reminder_enabled' => $this->flag($request, 'reminder_enabled', (bool) $plan->reminder_enabled),
            'document_upload_enabled' => $this->flag($request, 'document_upload_enabled', (bool) $plan->document_upload_enabled),
            'is_active' => $this->flag($request, 'is_active', (bool) $plan->is_active),
        ]);

        return $this->ok($this->row($plan->loadCount('accounts')), 'Plan updated successfully.');
    }

    public function destroy(int $id)
    {
        $plan = Plan::find($id);
        if (! $plan) {
            return $this->fail('Plan not found.', 'not_found');
        }
        if ($plan->accounts()->exists()) {
            return $this->fail('This plan is used by customers, so it cannot be deleted. Set it inactive instead.', 'in_use');
        }
        $plan->delete();

        return $this->ok(null, 'Plan deleted successfully.');
    }
}
