<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Support\Clock;
use App\Support\Letterhead;
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
 * The generated file is stored exactly where an uploaded offer letter would
 * be, so the invite email, the onboarding page and HR's review treat both
 * the same way.
 */
final class OfferLetter
{
    public const DEFAULT_BODY = <<<'TEXT'
Dear {employee_name},

We are pleased to offer you the position of **{designation}** at {company_name}. We were impressed by your background and are confident you will be a valuable addition to the team.

Your employment will begin on **{joining_date}**. Your compensation will be **{monthly_salary} per month**, an annual cost to company (CTC) of **{annual_ctc}**, subject to applicable taxes and statutory deductions. The full terms of the offer are summarised below.

This offer is subject to satisfactory verification of the documents you submit during onboarding, and to the company's policies as they apply from time to time.

To accept, please sign this letter and upload the signed copy on the employee portal by **{offer_valid_until}**.

We look forward to welcoming you.
TEXT;

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
            'monthly_salary' => 'Monthly salary, formatted in the company currency',
            'annual_ctc' => 'Monthly salary × 12',
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
            ...$this->commonValues(),
        ];
    }

    /**
     * Believable sample data, for previewing the template in Configuration.
     *
     * @return array<string, string>
     */
    public function sampleValues(): array
    {
        return [
            'employee_name' => 'Priya Raman',
            'first_name' => 'Priya',
            'employee_code' => 'EMP-0042',
            'designation' => 'Software Engineer',
            'department' => 'Engineering',
            'employment_type' => 'Full time',
            'joining_date' => $this->date(Clock::today()->addWeeks(3)),
            'monthly_salary' => $this->money(65000),
            'annual_ctc' => $this->money(65000 * 12),
            ...$this->commonValues(),
        ];
    }

    /**
     * Generate the PDF and keep it as the employee's offer letter.
     */
    public function generateFor(Employee $employee): void
    {
        $values = $this->valuesFor($employee);
        $path = EmployeeDocument::directoryFor($employee->id).'/offer-letter/'.Str::random(40).'.pdf';

        Storage::disk(EmployeeDocument::DISK)->put($path, $this->pdf($values, $employee->user->email));

        $old = $employee->offer_letter_path;

        $employee->forceFill([
            'offer_letter_path' => $path,
            'offer_letter_name' => 'Offer letter - '.$employee->user->name.'.pdf',
        ])->save();

        if ($old && $old !== $path) {
            Storage::disk(EmployeeDocument::DISK)->delete($old);
        }
    }

    /**
     * @param  array<string, string>  $values
     */
    public function pdf(array $values, ?string $email = null): string
    {
        return Pdf::loadHTML($this->html($values, $email))->setPaper('a4')->output();
    }

    /**
     * @param  array<string, string>  $values
     */
    public function html(array $values, ?string $email = null): string
    {
        $s = $this->settings;

        return view('pdf.offer-letter', [
            'company' => $this->letterhead->company(),
            'title' => $this->fill((string) $s->get('offer.title'), $values),
            'paragraphs' => $this->paragraphs((string) $s->get('offer.body'), $values),
            'values' => $values,
            'email' => $email,
            'reference' => 'OL/'.$values['employee_code'].'/'.Clock::today()->format('Y'),
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
        preg_match_all('/\{([a-z_]+)\}/', $text, $m);

        return array_values(array_unique(array_diff($m[1], array_keys(self::placeholders()))));
    }

    /**
     * Blank lines separate paragraphs, single line breaks are kept, and
     * **text** is bold. Everything is escaped before any markup is added, so
     * nothing in the template or an employee's name can inject HTML.
     *
     * @param  array<string, string>  $values
     * @return list<string>
     */
    private function paragraphs(string $body, array $values): array
    {
        $blocks = preg_split('/\R\s*\R/', trim($body)) ?: [];

        return array_values(array_filter(array_map(function (string $block) use ($values) {
            $html = e($this->fill(trim($block), $values));
            $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);

            return nl2br($html, false);
        }, $blocks)));
    }

    /**
     * @param  array<string, string>  $values
     */
    private function fill(string $text, array $values): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => $values[$m[1]] ?? $m[0], $text);
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
