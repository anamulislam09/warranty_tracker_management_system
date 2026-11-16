<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Monthly / Quarterly / Half-yearly / Yearly ... with their discount. Customers choose one of them. */
class PaymentScheduleController extends Controller
{
    private function rules(?PaymentSchedule $schedule = null): array
    {
        return [
            'name'             => ['required', 'string', 'max:60', Rule::unique('payment_schedules', 'name')->ignore($schedule)],
            'months'           => 'required|integer|min:1|max:36',
            'discount_percent' => 'required|numeric|min:0|max:50',
            'sort_order'       => 'nullable|integer|min:0',
        ];
    }

    private function extra(Request $r): array
    {
        return ['is_active' => $r->boolean('is_active'), 'sort_order' => $r->integer('sort_order')];
    }

    public function index()
    {
        return view('admin.schedules.index', [
            'schedules' => PaymentSchedule::withCount('accounts')->orderBy('sort_order')->orderBy('months')->paginate(10)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('admin.schedules.form', ['schedule' => new PaymentSchedule(['is_active' => true, 'months' => 1, 'discount_percent' => 0])]);
    }

    public function store(Request $request)
    {
        PaymentSchedule::create($request->validate($this->rules()) + $this->extra($request));
        return redirect()->route('admin.schedules.index')->with('success', 'Payment schedule created.');
    }

    public function edit(PaymentSchedule $schedule)
    {
        return view('admin.schedules.form', ['schedule' => $schedule->loadCount('accounts')]);
    }

    public function update(Request $request, PaymentSchedule $schedule)
    {
        $schedule->update($request->validate($this->rules($schedule)) + $this->extra($request));
        return redirect()->route('admin.schedules.index')->with('success', 'Payment schedule updated. New months / discount apply to the bills created from now on.');
    }

    public function destroy(PaymentSchedule $schedule)
    {
        if ($schedule->accounts()->exists()) {
            return back()->with('error', 'Customers use this payment schedule. Set it inactive instead of deleting it.');
        }
        $schedule->delete();
        return back()->with('success', 'Payment schedule deleted.');
    }
}
