<?php

namespace App\Services\Requirements;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * The requirements Excel template, and the check an upload must pass before
 * anything is imported. The rules live here once: the template, the checker
 * and the "how to fill it in" notes on the page all come from them.
 */
final class RequirementSheet
{
    /** The header row, exactly, in this order. */
    public const HEADERS = ['Requirement S.No', 'Module', 'Description', 'Additional Notes'];

    public const MAX_ROWS = 2000;

    public const LIMITS = ['module' => 100, 'description' => 2000, 'notes' => 2000];

    /**
     * The norms, in words, for the "i" on the upload page and the template's
     * second sheet.
     *
     * @return list<string>
     */
    public static function norms(): array
    {
        return [
            'Download and use this template. Only .xlsx or .xls files, up to 5 MB.',
            'Keep the first row exactly as given: '.implode(' · ', self::HEADERS).' — same names, same order, no extra columns.',
            'Write one requirement per row, starting on row 2. Only the first sheet is read.',
            'Requirement S.No: a whole number (1, 2, 3 …). Each number used once. Numbers need not be continuous.',
            'Module: required, up to '.self::LIMITS['module'].' characters (e.g. Login, Payroll, Reports).',
            'Description: required, up to '.self::LIMITS['description'].' characters — what the requirement is.',
            'Additional Notes: optional, up to '.self::LIMITS['notes'].' characters.',
            'Empty rows are skipped. At most '.self::MAX_ROWS.' requirements per file.',
            'The file is checked first. If anything is wrong, every problem is listed with its row — fix them in Excel and upload again. Nothing is imported until the file is clean.',
        ];
    }

    /** The template workbook, as .xlsx bytes. */
    public function template(): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle('Requirements template');

        $sheet = $book->getActiveSheet()->setTitle('Requirements');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:D1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        $sheet->freezePane('A2');
        foreach (['A' => 18, 'B' => 24, 'C' => 70, 'D' => 45] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getStyle('C:D')->getAlignment()->setWrapText(true);

        // S.No: Excel itself refuses anything but a positive whole number.
        $rule = $sheet->getCell('A2')->getDataValidation();
        $rule->setType(DataValidation::TYPE_WHOLE)->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)
            ->setFormula1('1')->setAllowBlank(true)->setShowErrorMessage(true)
            ->setErrorTitle('Requirement S.No')->setError('Use a whole number: 1, 2, 3 …');
        $sheet->setDataValidation('A2:A'.(self::MAX_ROWS + 1), clone $rule);

        $notes = $book->createSheet()->setTitle('How to fill in');
        $notes->setCellValue('A1', 'How to fill in the Requirements sheet');
        $notes->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        foreach (self::norms() as $i => $norm) {
            $notes->setCellValue('A'.($i + 3), ($i + 1).'. '.$norm);
        }
        $notes->getColumnDimension('A')->setWidth(120);

        $book->setActiveSheetIndex(0);

        ob_start();
        (new Xlsx($book))->save('php://output');

        return (string) ob_get_clean();
    }

    /**
     * Read and check an upload. Returns the rows it found and every problem,
     * each tied to a row and column, so the person can fix the file in one go.
     *
     * @return array{header_errors: list<string>, errors: list<array{row: int, column: string, message: string}>, points: list<array{number: int, module: string, description: string, notes: ?string}>}
     */
    public function check(UploadedFile $file): array
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $grid = $reader->load($file->getRealPath())->getSheet(0)->toArray(null, true, false, false);
        } catch (Throwable) {
            return ['header_errors' => ['This file could not be read as an Excel workbook. Save it as .xlsx from the template and try again.'], 'errors' => [], 'points' => []];
        }

        $header = array_map(fn ($cell) => trim((string) $cell), $grid[0] ?? []);
        while ($header !== [] && end($header) === '') {
            array_pop($header);
        }

        $headerErrors = [];
        if ($header === []) {
            $headerErrors[] = 'Row 1 is empty. It must hold the headers: '.implode(', ', self::HEADERS).'.';
        } else {
            foreach (self::HEADERS as $i => $expected) {
                $found = $header[$i] ?? '';
                if (strcasecmp($found, $expected) !== 0) {
                    $column = chr(65 + $i);
                    $headerErrors[] = $found === ''
                        ? "Column {$column} header is missing — it should be “{$expected}”."
                        : "Column {$column} header is “{$found}” — it should be “{$expected}”.";
                }
            }
            if (count($header) > count(self::HEADERS)) {
                $extra = array_slice($header, count(self::HEADERS));
                $headerErrors[] = 'Extra columns after “Additional Notes”: '.implode(', ', array_map(fn ($h) => $h === '' ? '(blank)' : "“{$h}”", $extra)).'. Remove them.';
            }
        }

        if ($headerErrors !== []) {
            // With the wrong columns, row checks would only mislead.
            return ['header_errors' => $headerErrors, 'errors' => [], 'points' => []];
        }

        $errors = [];
        $points = [];
        $seen = [];
        $dataRows = 0;

        foreach (array_slice($grid, 1, null, true) as $index => $cells) {
            $row = $index + 1;
            [$sno, $module, $description, $notes] = array_map(fn ($c) => is_string($c) ? trim($c) : $c, array_pad(array_slice($cells, 0, 4), 4, null));

            if (($sno === null || $sno === '') && ($module ?? '') === '' && ($description ?? '') === '' && ($notes ?? '') === '') {
                continue; // blank row
            }

            if (++$dataRows > self::MAX_ROWS) {
                $errors[] = ['row' => $row, 'column' => '—', 'message' => 'More than '.self::MAX_ROWS.' requirements. Split the file.'];
                break;
            }

            $number = null;
            if ($sno === null || $sno === '') {
                $errors[] = ['row' => $row, 'column' => 'Requirement S.No', 'message' => 'Missing. Give each requirement a number.'];
            } elseif (! is_numeric($sno) || (float) $sno != (int) $sno || (int) $sno < 1) {
                $errors[] = ['row' => $row, 'column' => 'Requirement S.No', 'message' => "“{$sno}” is not a whole number from 1 up."];
            } else {
                $number = (int) $sno;
                if (isset($seen[$number])) {
                    $errors[] = ['row' => $row, 'column' => 'Requirement S.No', 'message' => "{$number} is already used on row {$seen[$number]}."];
                } else {
                    $seen[$number] = $row;
                }
            }

            $module = (string) ($module ?? '');
            $description = (string) ($description ?? '');
            $notes = $notes === null || $notes === '' ? null : (string) $notes;

            if ($module === '') {
                $errors[] = ['row' => $row, 'column' => 'Module', 'message' => 'Missing.'];
            } elseif (mb_strlen($module) > self::LIMITS['module']) {
                $errors[] = ['row' => $row, 'column' => 'Module', 'message' => 'Longer than '.self::LIMITS['module'].' characters.'];
            }

            if ($description === '') {
                $errors[] = ['row' => $row, 'column' => 'Description', 'message' => 'Missing.'];
            } elseif (mb_strlen($description) > self::LIMITS['description']) {
                $errors[] = ['row' => $row, 'column' => 'Description', 'message' => 'Longer than '.self::LIMITS['description'].' characters.'];
            }

            if ($notes !== null && mb_strlen($notes) > self::LIMITS['notes']) {
                $errors[] = ['row' => $row, 'column' => 'Additional Notes', 'message' => 'Longer than '.self::LIMITS['notes'].' characters.'];
            }

            if ($number !== null) {
                $points[] = ['number' => $number, 'module' => $module, 'description' => $description, 'notes' => $notes];
            }
        }

        if ($points === [] && $errors === []) {
            $errors[] = ['row' => 2, 'column' => '—', 'message' => 'No requirements found. Add them from row 2.'];
        }

        usort($points, fn ($a, $b) => $a['number'] <=> $b['number']);

        return ['header_errors' => [], 'errors' => $errors, 'points' => $points];
    }
}
