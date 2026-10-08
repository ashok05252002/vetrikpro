<?php

namespace App\Services;

use App\Models\EmployeeDocument;
use App\Models\Promotion;
use App\Support\Letterhead;
use App\Support\LetterTemplate;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The letter that tells someone about a promotion or a salary revision, on
 * the same letterhead as the offer letter. Kept with the employee's private
 * files and attached to the email. The wording of both is edited in
 * Configuration hub → Offer letter; the before/after table goes where the
 * wording puts {summary_table}, or at the end.
 */
final class PromotionLetter
{
    public const PROMOTION = 'promotion';

    public const REVISION = 'revision';

    public const TABLE = 'summary_table';

    public const DEFAULT_PROMOTION_BODY = <<<'TEXT'
Dear {first_name},

In recognition of your contribution and performance, we are pleased to promote you to the position of **{new_designation}**, with effect from **{effective_date}**.

Your revised terms are summarised below.

{summary_table}

All other terms and conditions of your employment remain unchanged. Salary is subject to applicable taxes and statutory deductions.

We thank you for your work and look forward to your continued success with us.
TEXT;

    public const DEFAULT_REVISION_BODY = <<<'TEXT'
Dear {first_name},

We are pleased to inform you that your compensation has been revised with effect from **{effective_date}**, in recognition of your contribution to {company_name}.

Your revised terms are summarised below.

{summary_table}

All other terms and conditions of your employment remain unchanged. Salary is subject to applicable taxes and statutory deductions.

We thank you for your work and look forward to your continued success with us.
TEXT;

    /**
     * @return array<string, string>
     */
    public static function placeholders(): array
    {
        return [
            'employee_name' => 'Full name',
            'first_name' => 'First name',
            'employee_code' => 'Employee code',
            'department' => 'Department',
            'previous_designation' => 'Designation before the change',
            'new_designation' => 'Designation from the effective date',
            'previous_salary' => 'Monthly salary before the change',
            'new_salary' => 'Monthly salary from the effective date',
            'new_annual_ctc' => 'New monthly salary × 12',
            'salary_increase' => 'Monthly increase, with the percentage',
            'effective_date' => 'Date the change takes effect',
            'company_name' => 'Company name',
            'today' => 'Date the letter is generated',
            self::TABLE => 'Where the before/after table goes (on a line of its own)',
        ];
    }

    /**
     * @return list<string>
     */
    public static function unknownPlaceholders(string $text): array
    {
        return LetterTemplate::unknownPlaceholders($text, array_keys(self::placeholders()));
    }

    public function __construct(private readonly Settings $settings, private readonly Letterhead $letterhead) {}

    /**
     * Generate the PDF and store it against the promotion.
     */
    public function generateFor(Promotion $promotion): void
    {
        $path = EmployeeDocument::directoryFor($promotion->employee_id).'/promotions/'.Str::random(40).'.pdf';

        Storage::disk(EmployeeDocument::DISK)->put($path, $this->pdf($promotion));

        $old = $promotion->letter_path;
        $promotion->forceFill(['letter_path' => $path])->save();

        if ($old && $old !== $path) {
            Storage::disk(EmployeeDocument::DISK)->delete($old);
        }
    }

    public function pdf(Promotion $promotion): string
    {
        return Pdf::loadHTML($this->html($promotion))->setPaper('a4')->output();
    }

    public function html(Promotion $promotion): string
    {
        $promotion->loadMissing(['employee.user:id,name,email', 'employee.department:id,name']);
        $employee = $promotion->employee;
        $name = $employee->user->name;
        $from = $promotion->from_salary !== null ? (float) $promotion->from_salary : null;
        $to = $promotion->to_salary !== null ? (float) $promotion->to_salary : null;
        $percent = $promotion->incrementPercent();
        $increase = $from !== null && $to !== null && $to > $from
            ? $this->letterhead->money($to - $from).' per month'.($percent !== null ? " ({$percent}%)" : '')
            : null;

        return $this->render($promotion->isDesignationChange() ? self::PROMOTION : self::REVISION, [
            'employee_name' => $name,
            'first_name' => Str::before($name, ' ') ?: $name,
            'employee_code' => $employee->employee_code,
            'department' => $employee->department?->name ?? '—',
            'previous_designation' => $promotion->from_designation_name ?? '—',
            'new_designation' => (string) $promotion->to_designation_name,
            'previous_salary' => $from !== null ? $this->letterhead->money($from) : '—',
            'new_salary' => $to !== null ? $this->letterhead->money($to) : '—',
            'previous_annual_ctc' => $from !== null ? $this->letterhead->money($from * 12) : '—',
            'new_annual_ctc' => $to !== null ? $this->letterhead->money($to * 12) : '—',
            'salary_increase' => $increase ?? 'no change',
            'effective_date' => $this->letterhead->date($promotion->effective_date),
            'company_name' => (string) $this->settings->get('company.name'),
            'today' => $this->letterhead->date($promotion->created_at ?? now()),
        ], $employee->user->email, ($promotion->isDesignationChange() ? 'PR/' : 'SR/').$employee->employee_code.'/'.$promotion->effective_date->format('Y').'/'.$promotion->id, $increase !== null);
    }

    /**
     * Believable sample data, for previewing the templates in Configuration.
     */
    public function sampleHtml(string $kind): string
    {
        $promoted = $kind === self::PROMOTION;

        return $this->render($kind, [
            'employee_name' => 'Priya Raman',
            'first_name' => 'Priya',
            'employee_code' => 'EMP-0042',
            'department' => 'Engineering',
            'previous_designation' => 'Software Engineer',
            'new_designation' => $promoted ? 'Senior Software Engineer' : 'Software Engineer',
            'previous_salary' => $this->letterhead->money(65000),
            'new_salary' => $this->letterhead->money(75000),
            'previous_annual_ctc' => $this->letterhead->money(65000 * 12),
            'new_annual_ctc' => $this->letterhead->money(75000 * 12),
            'salary_increase' => $this->letterhead->money(10000).' per month (15.4%)',
            'effective_date' => $this->letterhead->date(now()->addWeeks(2)),
            'company_name' => (string) $this->settings->get('company.name'),
            'today' => $this->letterhead->date(now()),
        ], 'priya.raman@example.com', ($promoted ? 'PR/' : 'SR/').'EMP-0042/'.now()->format('Y').'/1', true);
    }

    public function samplePdf(string $kind): string
    {
        return Pdf::loadHTML($this->sampleHtml($kind))->setPaper('a4')->output();
    }

    /**
     * @param  array<string, string>  $values
     */
    private function render(string $kind, array $values, string $email, string $reference, bool $hasIncrease): string
    {
        $body = (string) $this->settings->get($kind.'.body');
        // Wording before {summary_table} goes above the table, the rest below.
        [$before, $after] = array_pad(preg_split('/^[ \t]*\{'.self::TABLE.'\}[ \t]*$/m', $body, 2), 2, '');

        return view('pdf.promotion-letter', [
            'company' => $this->letterhead->company(),
            'title' => LetterTemplate::fill((string) $this->settings->get($kind.'.title'), $values),
            'before' => LetterTemplate::paragraphs($before, $values),
            'after' => LetterTemplate::paragraphs($after, $values),
            'employee' => ['name' => $values['employee_name'], 'code' => $values['employee_code'], 'department' => $values['department'] === '—' ? null : $values['department'], 'email' => $email],
            'rows' => [
                'Designation' => [$values['previous_designation'], $values['new_designation']],
                'Monthly salary' => [$values['previous_salary'], $values['new_salary']],
                'Annual CTC' => [$values['previous_annual_ctc'], $values['new_annual_ctc']],
            ],
            'increase' => $hasIncrease ? $values['salary_increase'] : null,
            'effective' => $values['effective_date'],
            'today' => $values['today'],
            'reference' => $reference,
            'signatory' => ['name' => (string) $this->settings->get('offer.signatory_name'), 'title' => (string) $this->settings->get('offer.signatory_title')],
        ])->render();
    }
}
