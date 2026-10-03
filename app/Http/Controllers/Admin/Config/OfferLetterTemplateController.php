<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Services\OfferLetter;
use App\Services\PromotionLetter;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuration hub → Offer letter: the wording of every letter the portal
 * generates as a PDF — the offer letter, the welcome letter (no salary), the
 * internship letters with and without a stipend, and the promotion and
 * salary revision letters — and the signatory they all share.
 */
class OfferLetterTemplateController extends Controller
{
    /**
     * Every editable letter, keyed by the settings prefix its title and body
     * are saved under.
     *
     * @return array<string, array{label: string, description: string, body: string, placeholders: array<string, string>}>
     */
    public static function letters(): array
    {
        $offer = OfferLetter::kinds();

        return [
            'offer' => [
                'label' => 'Offer letter — with salary',
                'description' => 'The standard letter for a new employee. The summary table states the monthly salary and annual CTC.',
                'body' => $offer[OfferLetter::OFFER]['body'],
                'placeholders' => OfferLetter::placeholders(),
            ],
            'welcome' => [
                'label' => 'Welcome letter — no salary',
                'description' => 'For staff whose pay isn’t put in writing, such as contract hires. The summary leaves out the salary, and {monthly_salary} and {annual_ctc} print as “as discussed”.',
                'body' => $offer[OfferLetter::WELCOME]['body'],
                'placeholders' => OfferLetter::placeholders(),
            ],
            'internship' => [
                'label' => 'Internship letter — with stipend',
                'description' => 'Sent to an intern with a stipend. The summary table states the monthly stipend; use {stipend} in the wording.',
                'body' => $offer[OfferLetter::INTERNSHIP]['body'],
                'placeholders' => OfferLetter::placeholders(),
            ],
            'internship_unpaid' => [
                'label' => 'Internship letter — without stipend',
                'description' => 'Sent to an unpaid intern. The summary says “Unpaid internship”, and no salary or stipend is printed.',
                'body' => $offer[OfferLetter::INTERNSHIP_UNPAID]['body'],
                'placeholders' => OfferLetter::placeholders(),
            ],
            PromotionLetter::PROMOTION => [
                'label' => 'Promotion letter',
                'description' => 'Sent when someone is promoted to a new designation. The before/after table goes where {summary_table} is.',
                'body' => PromotionLetter::DEFAULT_PROMOTION_BODY,
                'placeholders' => PromotionLetter::placeholders(),
            ],
            PromotionLetter::REVISION => [
                'label' => 'Salary revision letter',
                'description' => 'Sent when only the salary changes. The before/after table goes where {summary_table} is.',
                'body' => PromotionLetter::DEFAULT_REVISION_BODY,
                'placeholders' => PromotionLetter::placeholders(),
            ],
        ];
    }

    public function edit(Request $request, Settings $settings): Response
    {
        return Inertia::render('admin/config/offer-letter', [
            'template' => [
                'signatory_name' => $settings->get('offer.signatory_name'),
                'signatory_title' => $settings->get('offer.signatory_title'),
                'valid_days' => $settings->get('offer.valid_days'),
                'letters' => collect(self::letters())->map(fn ($letter, $key) => [
                    'title' => $settings->get("{$key}.title"),
                    'body' => $settings->get("{$key}.body"),
                ]),
            ],
            'letters' => collect(self::letters())->map(fn ($letter, $key) => [
                'key' => $key,
                'label' => $letter['label'],
                'description' => $letter['description'],
                'default_body' => $letter['body'],
                'placeholders' => collect($letter['placeholders'])->map(fn ($label, $k) => ['key' => $k, 'label' => $label])->values(),
                'preview_url' => route('admin.config.offer-letter.preview', ['kind' => $key]),
            ])->values(),
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
        $rules = [
            'signatory_name' => ['nullable', 'string', 'max:120'],
            'signatory_title' => ['nullable', 'string', 'max:120'],
            'valid_days' => ['required', 'integer', 'min:1', 'max:90'],
            'letters' => ['required', 'array'],
        ];

        foreach (array_keys(self::letters()) as $key) {
            $rules["letters.{$key}.title"] = ['required', 'string', 'max:120'];
            $rules["letters.{$key}.body"] = ['required', 'string', 'max:10000'];
        }

        $data = validator($request->all(), $rules, [
            'letters.*.title.required' => 'Give the letter a heading.',
            'letters.*.body.required' => 'The letter needs some wording.',
        ])->after(fn (Validator $v) => self::withPlaceholderCheck($v, $request))->validate();

        $values = [
            'offer.signatory_name' => $data['signatory_name'] ?? '',
            'offer.signatory_title' => $data['signatory_title'] ?? '',
            'offer.valid_days' => $data['valid_days'],
        ];

        foreach (array_keys(self::letters()) as $key) {
            $values["{$key}.title"] = $data['letters'][$key]['title'];
            $values["{$key}.body"] = $data['letters'][$key]['body'];
        }

        $settings->set($values);

        return back()->with('success', 'Letter templates saved. New letters use them from now on.');
    }

    /**
     * The saved template with sample data, as a PDF in the browser.
     */
    public function preview(Request $request, OfferLetter $offerLetter, PromotionLetter $promotionLetter): HttpResponse
    {
        $kind = $request->validate(['kind' => ['nullable', Rule::in(array_keys(self::letters()))]])['kind'] ?? OfferLetter::OFFER;

        $pdf = in_array($kind, [PromotionLetter::PROMOTION, PromotionLetter::REVISION], true)
            ? $promotionLetter->samplePdf($kind)
            : $offerLetter->pdf($offerLetter->sampleValues($kind), 'priya.raman@example.com', $kind);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$kind.'-letter-preview.pdf"',
        ]);
    }

    /**
     * Refuse a template with a placeholder nothing fills, e.g. {joinig_date}.
     */
    public static function withPlaceholderCheck(Validator $validator, Request $request): void
    {
        foreach (self::letters() as $key => $letter) {
            $text = (string) $request->input("letters.{$key}.title").' '.$request->input("letters.{$key}.body");
            preg_match_all('/\{([a-z_]+)\}/', $text, $m);
            $unknown = array_values(array_unique(array_diff($m[1], array_keys($letter['placeholders']))));

            if ($unknown !== []) {
                $validator->errors()->add("letters.{$key}.body", 'Unknown placeholder: {'.implode('}, {', $unknown).'}. Pick one from the list.');
            }
        }
    }
}
