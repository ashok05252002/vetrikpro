<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Mail\TestMail;
use App\Support\IndianStates;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /** Upload field => setting key, for each branding image. */
    private const IMAGES = [
        'logo' => 'company.logo',
        'logo_dark' => 'company.logo_dark',
        'favicon' => 'company.favicon',
    ];

    public function __construct(private readonly Settings $settings) {}

    public function edit(): Response
    {
        return Inertia::render('admin/settings/index', [
            'settings' => [
                'company_name' => $this->settings->get('company.name'),
                'company_legal_name' => $this->settings->get('company.legal_name'),
                'company_tax_id' => $this->settings->get('company.tax_id'),
                'company_state' => $this->settings->get('company.state'),
                'invoice_due_days' => $this->settings->get('invoice.due_days'),
                'invoice_terms' => $this->settings->get('invoice.terms'),
                'invoice_bank_details' => $this->settings->get('invoice.bank_details'),
                'company_email' => $this->settings->get('company.email'),
                'company_phone' => $this->settings->get('company.phone'),
                'company_website' => $this->settings->get('company.website'),
                'company_address' => $this->settings->get('company.address'),
                'display_timezone' => $this->settings->get('display.timezone'),
                'display_date_format' => $this->settings->get('display.date_format'),
                'display_currency' => $this->settings->get('display.currency'),
            ],
            'images' => collect(self::IMAGES)->mapWithKeys(fn (string $key, string $field) => [$field => $this->settings->imageUrl($key)]),
            'timezones' => timezone_identifiers_list(),
            'states' => IndianStates::options(),
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
            'company.state' => $data['company_state'] ?? '',
            'invoice.due_days' => (int) ($data['invoice_due_days'] ?? 15),
            'invoice.terms' => $data['invoice_terms'] ?? '',
            'invoice.bank_details' => $data['invoice_bank_details'] ?? '',
            'company.email' => $data['company_email'] ?? '',
            'company.phone' => $data['company_phone'] ?? '',
            'company.website' => $data['company_website'] ?? '',
            'company.address' => $data['company_address'] ?? '',
            'display.timezone' => $data['display_timezone'],
            'display.date_format' => $data['display_date_format'],
            'display.currency' => strtoupper($data['display_currency']),
        ];

        foreach (self::IMAGES as $field => $key) {
            $existing = (string) $this->settings->get($key);

            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $path = 'company/'.str_replace('_', '-', $field).'-'.Str::random(12).'.'.strtolower($file->getClientOriginalExtension());

                Storage::disk('uploads')->put($path, $file->get());

                $this->deleteImage($existing);
                $values[$key] = $path;
            } elseif ($request->boolean("remove_{$field}")) {
                $this->deleteImage($existing);
                $values[$key] = '';
            }
        }

        $this->settings->set($values);

        return to_route('admin.settings.edit')->with('success', 'Settings saved.');
    }

    /**
     * Sends one email straight away, not queued, so the answer on screen is
     * the mail server's own: accepted, or the reason it refused.
     */
    public function testMail(Request $request): RedirectResponse
    {
        $email = $request->validate(['test_email' => ['required', 'email', 'max:255']])['test_email'];

        try {
            Mail::to($email)->send(new TestMail($request->user()->name));
        } catch (\Throwable $e) {
            Log::error('Test email failed', ['to' => $email, 'exception' => $e]);

            return back()->with('error', 'The test email could not be sent: '.$e->getMessage());
        }

        return back()->with('success', "Test email sent to {$email}. If it doesn't arrive, check spam and the mail provider's logs.");
    }

    private function deleteImage(string $path): void
    {
        if ($path !== '' && Storage::disk('uploads')->exists($path)) {
            Storage::disk('uploads')->delete($path);
        }
    }
}
