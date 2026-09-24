<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function edit(): Response
    {
        return Inertia::render('admin/settings/index', [
            'settings' => [
                'company_name' => $this->settings->get('company.name'),
                'company_legal_name' => $this->settings->get('company.legal_name'),
                'company_tax_id' => $this->settings->get('company.tax_id'),
                'company_email' => $this->settings->get('company.email'),
                'company_phone' => $this->settings->get('company.phone'),
                'company_website' => $this->settings->get('company.website'),
                'company_address' => $this->settings->get('company.address'),
                'display_timezone' => $this->settings->get('display.timezone'),
                'display_date_format' => $this->settings->get('display.date_format'),
                'display_currency' => $this->settings->get('display.currency'),
            ],
            'logoUrl' => $this->settings->logoUrl(),
            'timezones' => timezone_identifiers_list(),
            'dateFormats' => [
                ['value' => 'dmy', 'label' => 'Day Month Year — 24 Sep 2026'],
                ['value' => 'mdy', 'label' => 'Month Day Year — Sep 24, 2026'],
                ['value' => 'ymd', 'label' => 'Year Month Day — 2026-09-24'],
            ],
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $values = [
            'company.name' => $data['company_name'],
            'company.legal_name' => $data['company_legal_name'] ?? '',
            'company.tax_id' => $data['company_tax_id'] ?? '',
            'company.email' => $data['company_email'] ?? '',
            'company.phone' => $data['company_phone'] ?? '',
            'company.website' => $data['company_website'] ?? '',
            'company.address' => $data['company_address'] ?? '',
            'display.timezone' => $data['display_timezone'],
            'display.date_format' => $data['display_date_format'],
            'display.currency' => strtoupper($data['display_currency']),
        ];

        $existing = (string) $this->settings->get('company.logo');

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $path = 'company/logo-'.Str::random(12).'.'.$file->getClientOriginalExtension();

            Storage::disk('uploads')->put($path, $file->get());

            $this->deleteLogo($existing);
            $values['company.logo'] = $path;
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($existing);
            $values['company.logo'] = '';
        }

        $this->settings->set($values);

        return to_route('admin.settings.edit')->with('success', 'Settings saved.');
    }

    private function deleteLogo(string $path): void
    {
        if ($path !== '' && Storage::disk('uploads')->exists($path)) {
            Storage::disk('uploads')->delete($path);
        }
    }
}
