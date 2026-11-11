<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\RenewalRequest;
use App\Models\RenewalType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuration hub → Renewal types: the kinds of renewal (Domain, AMC…)
 * offered when adding one, each with its usual cycle and notice period. A
 * type in use can be renamed or switched off, never deleted.
 */
class RenewalTypeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/config/renewal-types', [
            'types' => RenewalType::ordered()->withCount('renewals')->get()
                ->map(fn (RenewalType $t) => $t->only('id', 'name', 'cycle_months', 'remind_days', 'is_active', 'renewals_count')),
            'cycles' => RenewalRequest::cycleOptions(),
            'can' => [
                'create' => $request->user()->can('renewal_types.create'),
                'edit' => $request->user()->can('renewal_types.edit'),
                'delete' => $request->user()->can('renewal_types.delete'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        RenewalType::create([...$data, 'sort_order' => (int) RenewalType::max('sort_order') + 1]);

        return back()->with('success', "“{$data['name']}” added.");
    }

    public function update(Request $request, RenewalType $renewalType): RedirectResponse
    {
        $renewalType->update($this->validated($request, $renewalType));

        return back()->with('success', "“{$renewalType->name}” updated. Existing renewals keep their own cycle and notice period.");
    }

    public function destroy(RenewalType $renewalType): RedirectResponse
    {
        if ($renewalType->isInUse()) {
            return back()->with('error', "Renewals use “{$renewalType->name}”. Switch it off instead, so it stops being offered.");
        }

        $renewalType->delete();

        return back()->with('success', "“{$renewalType->name}” removed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?RenewalType $type = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('renewal_types')->ignore($type?->id)],
            'cycle_months' => ['required', 'integer', Rule::in(RenewalRequest::CYCLES)],
            'remind_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
