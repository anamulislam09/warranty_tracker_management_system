<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:100', 'description' => 'nullable|string|max:255',
            'monthly_price' => 'required|numeric|min:0', 'annual_price' => 'required|numeric|min:0',
            'max_products' => 'required|integer|min:0', 'storage_limit_mb' => 'required|integer|min:1',
            'max_users' => 'required|integer|min:1',
        ];
    }

    private function flags(Request $r): array
    {
        return [
            'reminder_enabled' => $r->boolean('reminder_enabled'),
            'document_upload_enabled' => $r->boolean('document_upload_enabled'),
            'is_active' => $r->boolean('is_active'),
        ];
    }

    public function index()
    {
        return view('admin.plans.index', ['plans' => Plan::withCount('accounts')->orderBy('monthly_price')->get()]);
    }

    public function create() { return view('admin.plans.form', ['plan' => new Plan(['reminder_enabled' => true, 'document_upload_enabled' => true, 'is_active' => true, 'max_users' => 1, 'storage_limit_mb' => 500])]); }

    public function store(Request $request)
    {
        Plan::create($request->validate($this->rules()) + $this->flags($request));
        return redirect()->route('admin.plans.index')->with('success', 'Plan created.');
    }

    public function edit(Plan $plan) { return view('admin.plans.form', compact('plan')); }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($request->validate($this->rules()) + $this->flags($request));
        return redirect()->route('admin.plans.index')->with('success', 'Plan updated.');
    }

    public function destroy(Plan $plan)
    {
        if ($plan->accounts()->exists()) {
            return back()->with('error', 'This plan is used by customers. Deactivate it instead of deleting.');
        }
        $plan->delete();
        return back()->with('success', 'Plan deleted.');
    }
}
