<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\CustomerRequest;
use App\Models\Customer;
use App\Support\IndianStates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customers are added and edited in a dialog on the list, like document types.
 * Once invoiced, a customer can only be switched off.
 */
class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $customers = Customer::query()
            ->withCount('invoices')
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('gstin', 'like', "%{$search}%")))
            ->when($request->string('state')->value(), fn ($query, string $state) => $query->where('is_active', $state === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Customer $customer) => [
                ...$customer->toArray(),
                'state_name' => IndianStates::name($customer->state_code),
            ]);

        return Inertia::render('accounts/customers', [
            'customers' => $customers,
            'states' => IndianStates::options(),
            'filters' => $request->only('search', 'state'),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());

        return back()->with('success', "“{$customer->name}” added.");
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return back()->with('success', "“{$customer->name}” updated. Invoices already issued keep the details they were sent with.");
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->isInUse()) {
            return back()->with('error', "“{$customer->name}” has invoices. Mark them inactive instead.");
        }

        $customer->delete();

        return back()->with('success', "“{$customer->name}” deleted.");
    }

    public function active(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validate(['is_active' => ['required', 'boolean']]));

        return back()->with('success', $customer->is_active ? "“{$customer->name}” is active again." : "“{$customer->name}” is now inactive.");
    }
}
