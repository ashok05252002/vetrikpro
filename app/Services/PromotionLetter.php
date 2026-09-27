<?php

namespace App\Services;

use App\Models\EmployeeDocument;
use App\Models\Promotion;
use App\Support\Letterhead;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The letter that tells someone about a promotion or a salary revision, on
 * the same letterhead as the offer letter. Kept with the employee's private
 * files and attached to the email.
 */
final class PromotionLetter
{
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
        $promoted = $promotion->isDesignationChange();
        $from = $promotion->from_salary !== null ? (float) $promotion->from_salary : null;
        $to = (float) $promotion->to_salary;
        $percent = $promotion->incrementPercent();

        return view('pdf.promotion-letter', [
            'company' => $this->letterhead->company(),
            'title' => $promoted ? 'Letter of Promotion' : 'Salary Revision',
            'promoted' => $promoted,
            'employee' => [
                'name' => $name,
                'first_name' => Str::before($name, ' ') ?: $name,
                'email' => $employee->user->email,
                'code' => $employee->employee_code,
                'department' => $employee->department?->name,
            ],
            'rows' => array_filter([
                'Designation' => [$promotion->from_designation_name ?? '—', $promotion->to_designation_name],
                'Monthly salary' => [$from !== null ? $this->letterhead->money($from) : '—', $this->letterhead->money($to)],
                'Annual CTC' => [$from !== null ? $this->letterhead->money($from * 12) : '—', $this->letterhead->money($to * 12)],
            ]),
            'increase' => $from !== null && $to > $from
                ? $this->letterhead->money($to - $from).' per month'.($percent !== null ? " ({$percent}%)" : '')
                : null,
            'effective' => $this->letterhead->date($promotion->effective_date),
            'today' => $this->letterhead->date($promotion->created_at ?? now()),
            'reference' => ($promoted ? 'PR/' : 'SR/').$employee->employee_code.'/'.$promotion->effective_date->format('Y').'/'.$promotion->id,
            'signatory' => ['name' => (string) $this->settings->get('offer.signatory_name'), 'title' => (string) $this->settings->get('offer.signatory_title')],
        ])->render();
    }
}
