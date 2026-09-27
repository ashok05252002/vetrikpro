<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\ProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Products and services, with the price and GST rate an invoice line starts
 * from. Once invoiced, one can only be switched off.
 */
class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->withCount('invoiceItems')
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('hsn_sac', 'like', "%{$search}%")))
            ->when($request->string('type')->value(), fn ($query, string $type) => $query->where('type', $type))
            ->when($request->string('state')->value(), fn ($query, string $state) => $query->where('is_active', $state === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('accounts/products', [
            'products' => $products,
            'types' => collect(Product::TYPES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'gstRates' => Product::GST_RATES,
            'filters' => $request->only('search', 'type', 'state'),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->validated());

        return back()->with('success', "“{$product->name}” added.");
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return back()->with('success', "“{$product->name}” updated. Invoices already made keep their own prices.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->isInUse()) {
            return back()->with('error', "“{$product->name}” is on invoices. Mark it inactive instead.");
        }

        $product->delete();

        return back()->with('success', "“{$product->name}” deleted.");
    }

    public function active(Request $request, Product $product): RedirectResponse
    {
        $product->update($request->validate(['is_active' => ['required', 'boolean']]));

        return back()->with('success', $product->is_active ? "“{$product->name}” is active again." : "“{$product->name}” is now inactive.");
    }
}
