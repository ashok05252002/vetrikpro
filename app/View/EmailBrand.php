<?php

namespace App\View;

use App\Support\Settings;
use Illuminate\View\View;

/**
 * Gives every email view the organisation's branding: name, address,
 * contact line, and the logo as a file path to embed.
 *
 * The logo is embedded in the message (a CID attachment) rather than linked:
 * a link to this server would not load for anyone outside it, and many mail
 * clients block remote images by default anyway.
 */
class EmailBrand
{
    public function __construct(private readonly Settings $settings) {}

    public function compose(View $view): void
    {
        $s = $this->settings;
        $logo = (string) $s->get('company.logo');
        $path = $logo !== '' ? public_path('uploads/'.ltrim($logo, '/')) : null;

        $view->with('brand', [
            'name' => (string) $s->get('company.name'),
            'legal_name' => (string) ($s->get('company.legal_name') ?: $s->get('company.name')),
            'address' => (string) $s->get('company.address'),
            'contact' => collect([$s->get('company.phone'), $s->get('company.email'), $s->get('company.website')])->filter()->implode('  ·  '),
            'logo_path' => $path && is_file($path) && ! str_ends_with($path, '.svg') ? $path : null,
            'app_url' => config('app.url'),
        ]);
    }
}
