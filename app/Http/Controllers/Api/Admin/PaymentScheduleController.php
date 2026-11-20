<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\PaymentSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Monthly / Quarterly / Half-yearly / Yearly ... and their discount. Customers choose one of them. */
class PaymentScheduleController extends ApiController
{
    private function rules(?PaymentSchedule $schedule = null): array
    {
        return [
            'name'             => ['required', 'string', 'max:60', Rule::unique('payment_schedules', 'name')->ignore($schedule)],
            'months'           => 'required|integer|min:1|max:36',      // one bill covers this many months
            'discount_percent' => 'required|numeric|min:0|max:50',
            'sort_order'       => 'nullable|integer|min:0',
            'is_active'        => 'nullable|boolean',
        ];
    }

    private function row(PaymentSchedule $s): array
    {
        return [
            'id'               => $s->id,
            'name'             => $s->name,
            'months'           => (int) $s->months,
            'discount_percent' => (float) $s->discount_percent,
            'bills_per_year'   => round(12 / max(1, $s->months), 2),
            'sort_order'       => (int) $s->sort_order,
            'is_active'        => (bool) $s->is_active,
            'customers_count'  => (int) ($s->accounts_count ?? $s->accounts()->count()),
        ];
    }

    public function index()
    {
        return $this->ok(PaymentSchedule::withCount('accounts')->orderBy('sort_order')->orderBy('months')->get()->map(fn ($s) => $this->row($s))->values());
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $schedule = PaymentSchedule::create([
            'name' => $data['name'], 'months' => $data['months'], 'discount_percent' => $data['discount_percent'],
            'sort_order' => $data['sort_order'] ?? 0, 'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return $this->ok($this->row($schedule->loadCount('accounts')), 'Payment schedule created successfully.');
    }

    public function update(Request $request, int $id)
    {
        $schedule = PaymentSchedule::find($id);
        if (! $schedule) {
            return $this->fail('Payment schedule not found.', 'not_found');
        }
        $data = $request->validate($this->rules($schedule));

        $schedule->update([
            'name' => $data['name'], 'months' => $data['months'], 'discount_percent' => $data['discount_percent'],
            'sort_order' => $data['sort_order'] ?? $schedule->sort_order,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $schedule->is_active,
        ]);

        return $this->ok($this->row($schedule->loadCount('accounts')), 'Payment schedule updated successfully. The new months and discount apply to the bills created from now on.');
    }

    public function destroy(int $id)
    {
        $schedule = PaymentSchedule::find($id);
        if (! $schedule) {
            return $this->fail('Payment schedule not found.', 'not_found');
        }
        if ($schedule->accounts()->exists()) {
            return $this->fail('Customers use this payment schedule, so it cannot be deleted. Set it inactive instead.', 'in_use');
        }
        $schedule->delete();

        return $this->ok(null, 'Payment schedule deleted successfully.');
    }
}
