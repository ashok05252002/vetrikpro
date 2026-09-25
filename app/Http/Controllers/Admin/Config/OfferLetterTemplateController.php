<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Services\OfferLetter;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuration hub → Offer letter: the wording and signatory every
 * generated offer letter uses.
 */
class OfferLetterTemplateController extends Controller
{
    public function edit(Request $request, Settings $settings): Response
    {
        return Inertia::render('admin/config/offer-letter', [
            'template' => [
                'title' => $settings->get('offer.title'),
                'body' => $settings->get('offer.body'),
                'signatory_name' => $settings->get('offer.signatory_name'),
                'signatory_title' => $settings->get('offer.signatory_title'),
                'valid_days' => $settings->get('offer.valid_days'),
            ],
            'defaultBody' => OfferLetter::DEFAULT_BODY,
            'placeholders' => collect(OfferLetter::placeholders())->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            // The letterhead is built from these; missing ones are flagged.
            'letterhead' => [
                'logo' => $settings->logoUrl() !== null,
                'address' => filled($settings->get('company.address')),
                'legal_name' => filled($settings->get('company.legal_name')),
            ],
            'can' => ['edit' => $request->user()->can('settings.edit')],
        ]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $validator = validator($request->all(), [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:10000'],
            'signatory_name' => ['nullable', 'string', 'max:120'],
            'signatory_title' => ['nullable', 'string', 'max:120'],
            'valid_days' => ['required', 'integer', 'min:1', 'max:90'],
        ])->after(fn (Validator $v) => self::withPlaceholderCheck($v, $request));

        $data = $validator->validate();

        $settings->set([
            'offer.title' => $data['title'],
            'offer.body' => $data['body'],
            'offer.signatory_name' => $data['signatory_name'] ?? '',
            'offer.signatory_title' => $data['signatory_title'] ?? '',
            'offer.valid_days' => $data['valid_days'],
        ]);

        return back()->with('success', 'Offer letter template saved. New letters use it from now on.');
    }

    /**
     * The saved template with sample data, as a PDF in the browser.
     */
    public function preview(OfferLetter $offerLetter): HttpResponse
    {
        return response($offerLetter->pdf($offerLetter->sampleValues(), 'priya.raman@example.com'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="offer-letter-preview.pdf"',
        ]);
    }

    /**
     * Refuse a template with a placeholder nothing fills, e.g. {joinig_date}.
     */
    public static function withPlaceholderCheck(Validator $validator, Request $request): void
    {
        $unknown = OfferLetter::unknownPlaceholders((string) $request->input('title').' '.$request->input('body'));

        if ($unknown !== []) {
            $validator->errors()->add('body', 'Unknown placeholder: {'.implode('}, {', $unknown).'}. Pick one from the list.');
        }
    }
}
