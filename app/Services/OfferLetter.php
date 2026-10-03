<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Support\Clock;
use App\Support\Letterhead;
use App\Support\LetterTemplate;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds an employee's offer letter as a PDF from the template in
 * Configuration hub → Offer letter: the company letterhead (logo, name,
 * address), the template's wording with {placeholders} filled in, an offer
 * summary table and signature blocks.
 *
 * There are four kinds, each with its own wording in Configuration hub →
 * Offer letter: the offer letter states the salary; the welcome letter is
 * the same letter without the package — for contract staff and anyone whose
 * pay is not put in writing; and interns get an internship letter, which
 * states the stipend, or says there is none.
 *
 * The generated file is stored exactly where an uploaded offer letter would
 * be, so the invite email, the onboarding page and HR's review treat all of
 * them the same way.
 */
final class OfferLetter
{
    public const OFFER = 'offer';

    public const WELCOME = 'welcome';

    public const INTERNSHIP = 'internship';

    public const INTERNSHIP_UNPAID = 'internship_unpaid';

    public const KINDS = [self::OFFER, self::WELCOME, self::INTERNSHIP, self::INTERNSHIP_UNPAID];

    public const DEFAULT_BODY = <<<'TEXT'
Dear {employee_name},

We are pleased to offer you the position of **{designation}** at {company_name}. We were impressed by your background and are confident you will be a valuable addition to the team.

Your employment will begin on **{joining_date}**. Your compensation will be **{monthly_salary} per month**, an annual cost to company (CTC) of **{annual_ctc}**, subject to applicable taxes and statutory deductions. The full terms of the offer are summarised below.

This offer is subject to satisfactory verification of the documents you submit during onboarding, and to the company's policies as they apply from time to time.

To accept, please sign this letter and upload the signed copy on the employee portal by **{offer_valid_until}**.

We look forward to welcoming you.
TEXT;

    public const DEFAULT_WELCOME_BODY = <<<'TEXT'
Dear {employee_name},

Greetings from {company_name}! We are delighted to welcome you to the team as **{designation}** ({employment_type}), and look forward to working with you.

Your engagement will begin on **{joining_date}**. The terms we have discussed with you continue to apply, and the details of your engagement are summarised below.

This letter is subject to satisfactory verification of the documents you submit during onboarding, and to the company's policies as they apply from time to time.

Please sign this letter and upload the signed copy on the employee portal by **{offer_valid_until}** to confirm.

Welcome aboard.
TEXT;

    public const DEFAULT_INTERNSHIP_BODY = <<<'TEXT'
Dear {employee_name},

We are pleased to offer you an internship at {company_name} as **{designation}**, beginning on **{joining_date}**.

During the internship you will receive a stipend of **{stipend} per month**, subject to applicable deductions. The stipend is paid for the duration of the internship only, and this letter is not an offer of permanent employment.

You will work alongside our team on live projects, with guidance from your mentor. We expect you to follow the company's policies and to keep its confidential information private, during the internship and after it.

This offer is subject to satisfactory verification of the documents you submit during onboarding.

To accept, please sign this letter and upload the signed copy on the employee portal by **{offer_valid_until}**.

We look forward to having you with us.
TEXT;

    public const DEFAULT_INTERNSHIP_UNPAID_BODY = <<<'TEXT'
Dear {employee_name},

We are pleased to offer you an internship at {company_name} as **{designation}**, beginning on **{joining_date}**.

This internship is a learning opportunity and carries no stipend or salary. It is not an offer of permanent employment.

You will work alongside our team on live projects, with guidance from your mentor. We expect you to follow the company's policies and to keep its confidential information private, during the internship and after it.

This offer is subject to satisfactory verification of the documents you submit during onboarding.

To accept, please sign this letter and upload the signed copy on the employee portal by **{offer_valid_until}**.

We look forward to having you with us.
TEXT;

    /**
     * Each kind's name, the settings its wording is saved under, its
     * reference prefix and its standard wording.
     *
     * @return array<string, array{label: string, settings: string, ref: string, body: string}>
     */
    public static function kinds(): array
    {
        return [
            self::OFFER => ['label' => 'Offer letter', 'settings' => 'offer', 'ref' => 'OL', 'body' => self::DEFAULT_BODY],
            self::WELCOME => ['label' => 'Welcome letter', 'settings' => 'welcome', 'ref' => 'WL', 'body' => self::DEFAULT_WELCOME_BODY],
            self::INTERNSHIP => ['label' => 'Internship letter', 'settings' => 'internship', 'ref' => 'IL', 'body' => self::DEFAULT_INTERNSHIP_BODY],
            self::INTERNSHIP_UNPAID => ['label' => 'Internship letter', 'settings' => 'internship_unpaid', 'ref' => 'IL', 'body' => self::DEFAULT_INTERNSHIP_UNPAID_BODY],
        ];
    }

    /**
     * What a kind is called in emails and on screen; an uploaded letter is
     * an offer letter.
     */
    public static function label(?string $kind): string
    {
        return self::kinds()[$kind ?? self::OFFER]['label'] ?? 'Offer letter';
    }

    /**
     * The letter an employee gets by default: interns the internship letter
     * that matches their stipend, everyone else the offer letter.
     */
    public static function kindFor(Employee $employee): string
    {
        if (! $employee->isIntern()) {
            return self::OFFER;
        }

        return $employee->has_stipend ? self::INTERNSHIP : self::INTERNSHIP_UNPAID;
    }

    /**
     * Every placeholder the template may use, with what it becomes.
     *
     * @return array<string, string>
     */
    public static function placeholders(): array
    {
        return [
            'employee_name' => 'Full name',
            'first_name' => 'First name',
            'employee_code' => 'Employee code, e.g. EMP-0012',
            'designation' => 'Designation, e.g. Software Engineer',
            'department' => 'Department',
            'employment_type' => 'Full time, part time, contract or intern',
            'joining_date' => 'Date of joining',
            'monthly_salary' => 'Monthly salary, formatted in the company currency (offer letter only)',
            'annual_ctc' => 'Monthly salary × 12 (offer letter only)',
            'stipend' => 'Monthly stipend, formatted in the company currency (paid internship only)',
            'offer_valid_until' => 'Today plus the validity period',
            'company_name' => 'Company name',
            'today' => 'Date the letter is generated',
        ];
    }

    public function __construct(private readonly Settings $settings, private readonly Letterhead $letterhead) {}

    /**
     * @return array<string, string>
     */
    public function valuesFor(Employee $employee): array
    {
        $employee->loadMissing(['user:id,name,email', 'department:id,name', 'designation:id,name']);
        $salary = $employee->salary !== null ? (float) $employee->salary : null;
        $name = $employee->user->name;

        return [
            'employee_name' => $name,
            'first_name' => Str::before($name, ' ') ?: $name,
            'employee_code' => $employee->employee_code,
            'designation' => $employee->designation?->name ?? 'the offered position',
            'department' => $employee->department?->name ?? '—',
            'employment_type' => ['full_time' => 'Full time', 'part_time' => 'Part time', 'contract' => 'Contract', 'intern' => 'Internship'][$employee->employment_type] ?? $employee->employment_type,
            'joining_date' => $employee->date_of_joining ? $this->date($employee->date_of_joining) : 'a date to be confirmed',
            'monthly_salary' => $salary !== null ? $this->money($salary) : 'as discussed',
            'annual_ctc' => $salary !== null ? $this->money($salary * 12) : 'as discussed',
            'stipend' => $employee->has_stipend && $employee->stipend !== null ? $this->money((float) $employee->stipend) : 'no stipend',
            ...$this->commonValues(),
        ];
    }

    /**
     * Believable sample data, for previewing the template in Configuration.
     *
     * @return array<string, string>
     */
    public function sampleValues(string $kind = self::OFFER): array
    {
        $intern = in_array($kind, [self::INTERNSHIP, self::INTERNSHIP_UNPAID], true);

        return [
            'employee_name' => 'Priya Raman',
            'first_name' => 'Priya',
            'employee_code' => 'EMP-0042',
            'designation' => $intern ? 'Software Engineering Intern' : 'Software Engineer',
            'department' => 'Engineering',
            'employment_type' => $intern ? 'Internship' : 'Full time',
            'joining_date' => $this->date(Clock::today()->addWeeks(3)),
            'monthly_salary' => $this->money(65000),
            'annual_ctc' => $this->money(65000 * 12),
            'stipend' => $kind === self::INTERNSHIP_UNPAID ? 'no stipend' : $this->money(10000),
            ...$this->commonValues(),
        ];
    }

    /**
     * Generate the PDF and keep it as the employee's offer letter.
     */
    public function generateFor(Employee $employee, string $kind = self::OFFER): void
    {
        $values = $this->valuesFor($employee);
        $path = EmployeeDocument::directoryFor($employee->id).'/offer-letter/'.Str::random(40).'.pdf';

        Storage::disk(EmployeeDocument::DISK)->put($path, $this->pdf($values, $employee->user->email, $kind));

        $old = $employee->offer_letter_path;

        $employee->forceFill([
            'offer_letter_path' => $path,
            'offer_letter_name' => self::label($kind).' - '.$employee->user->name.'.pdf',
            'offer_letter_kind' => $kind,
        ])->save();

        if ($old && $old !== $path) {
            Storage::disk(EmployeeDocument::DISK)->delete($old);
        }
    }

    /**
     * @param  array<string, string>  $values
     */
    public function pdf(array $values, ?string $email = null, string $kind = self::OFFER): string
    {
        return Pdf::loadHTML($this->html($values, $email, $kind))->setPaper('a4')->output();
    }

    /**
     * @param  array<string, string>  $values
     */
    public function html(array $values, ?string $email = null, string $kind = self::OFFER): string
    {
        $s = $this->settings;
        $meta = self::kinds()[$kind] ?? self::kinds()[self::OFFER];
        $offer = $kind === self::OFFER;
        $intern = in_array($kind, [self::INTERNSHIP, self::INTERNSHIP_UNPAID], true);

        // Only the offer letter states the salary, and only the paid
        // internship letter the stipend, whatever the wording asks for.
        if (! $offer) {
            $values = [...$values, 'monthly_salary' => 'as discussed', 'annual_ctc' => 'as discussed'];
        }
        if ($kind !== self::INTERNSHIP) {
            $values = [...$values, 'stipend' => 'no stipend'];
        }

        $summary = [
            ['Position', $values['designation']],
            ['Department', $values['department']],
            [$intern ? 'Internship starts' : 'Date of joining', $values['joining_date']],
            ['Employment type', $values['employment_type']],
            ...($offer ? [['Monthly salary', $values['monthly_salary']], ['Annual CTC', $values['annual_ctc']]] : []),
            ...($kind === self::INTERNSHIP ? [['Monthly stipend', $values['stipend']]] : []),
            ...($kind === self::INTERNSHIP_UNPAID ? [['Stipend', 'Unpaid internship']] : []),
            [$intern ? 'Intern code' : 'Employee code', $values['employee_code']],
            [$offer || $intern ? 'Offer valid until' : 'Please confirm by', $values['offer_valid_until']],
        ];

        return view('pdf.offer-letter', [
            'company' => $this->letterhead->company(),
            'title' => LetterTemplate::fill((string) $s->get($meta['settings'].'.title'), $values),
            'paragraphs' => LetterTemplate::paragraphs((string) $s->get($meta['settings'].'.body'), $values),
            'summaryTitle' => $offer ? 'Offer summary' : ($intern ? 'Internship summary' : 'Summary'),
            'summary' => array_chunk($summary, 2),
            'acceptance' => $offer || $intern ? 'I accept this offer on the terms set out above.' : 'I accept this engagement on the terms discussed.',
            'values' => $values,
            'email' => $email,
            'reference' => $meta['ref'].'/'.$values['employee_code'].'/'.Clock::today()->format('Y'),
            'signatory' => ['name' => (string) $s->get('offer.signatory_name'), 'title' => (string) $s->get('offer.signatory_title')],
        ])->render();
    }

    /**
     * Placeholders in a template that this class does not know, so a typo
     * like {joinig_date} is caught when saving, not printed on a letter.
     *
     * @return list<string>
     */
    public static function unknownPlaceholders(string $text): array
    {
        return LetterTemplate::unknownPlaceholders($text, array_keys(self::placeholders()));
    }

    /**
     * @return array<string, string>
     */
    private function commonValues(): array
    {
        return [
            'offer_valid_until' => $this->date(Clock::today()->addDays((int) $this->settings->get('offer.valid_days', 7))),
            'company_name' => (string) $this->settings->get('company.name'),
            'today' => $this->date(Clock::today()),
        ];
    }

    private function date(Carbon $date): string
    {
        return $this->letterhead->date($date);
    }

    private function money(float $amount): string
    {
        return $this->letterhead->money($amount);
    }
}
