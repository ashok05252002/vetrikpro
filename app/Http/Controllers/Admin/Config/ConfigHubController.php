<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Configuration hub: one screen showing every area that shapes the app,
 * whether each is set up, and what still needs attention.
 */
class ConfigHubController extends Controller
{
    public function __invoke(Request $request, Settings $settings): Response
    {
        $user = $request->user();
        abort_unless($user->canAny(['settings.view', 'document_types.view']), 403);
        $missing = fn (string $key) => blank($settings->get($key));

        return Inertia::render('admin/config/hub', [
            'areas' => [
                'organisation' => $user->can('settings.view') ? [
                    'name' => $settings->get('company.name'),
                    'logo' => $settings->logoUrl(),
                    'missing' => array_values(array_filter([
                        $settings->logoUrl() === null ? 'Logo' : null,
                        $missing('company.legal_name') ? 'Legal name' : null,
                        $missing('company.address') ? 'Address' : null,
                        $missing('company.email') ? 'Email' : null,
                        $missing('company.phone') ? 'Phone' : null,
                    ])),
                ] : null,
                'documents' => $user->can('document_types.view') ? [
                    'active' => DocumentType::active()->count(),
                    'required' => DocumentType::active()->where('is_required', true)->count(),
                ] : null,
                'offer' => $user->can('settings.view') ? [
                    'title' => $settings->get('offer.title'),
                    'signatory' => $settings->get('offer.signatory_name'),
                    'valid_days' => $settings->get('offer.valid_days'),
                ] : null,
            ],
        ]);
    }
}
