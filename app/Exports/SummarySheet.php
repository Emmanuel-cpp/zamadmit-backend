<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * First sheet of the workbook — totals per school and per status,
 * so a registrar gets the headline numbers before the detail sheets.
 */
class SummarySheet implements FromArray, WithTitle, WithStyles
{
    public function __construct(
        private Collection $bySchool,      // school => Collection<Application>
        private string $institutionName,
    ) {}

    public function array(): array
    {
        $rows = [];

        $rows[] = [$this->institutionName . ' — Applications summary'];
        $rows[] = ['Generated', now()->format('d M Y H:i')];
        $rows[] = []; // spacer

        $rows[] = ['School', 'Total', 'Submitted', 'Under review', 'Accepted', 'Rejected', 'Waitlisted'];

        $grand = ['total' => 0, 'submitted' => 0, 'under_review' => 0, 'accepted' => 0, 'rejected' => 0, 'waitlisted' => 0];

        foreach ($this->bySchool as $school => $apps) {
            $counts = [
                'submitted'    => $apps->where('status', 'submitted')->count(),
                'under_review' => $apps->where('status', 'under_review')->count(),
                'accepted'     => $apps->where('status', 'accepted')->count(),
                'rejected'     => $apps->where('status', 'rejected')->count(),
                'waitlisted'   => $apps->where('status', 'waitlisted')->count(),
            ];

            $rows[] = [
                $school,
                $apps->count(),
                $counts['submitted'],
                $counts['under_review'],
                $counts['accepted'],
                $counts['rejected'],
                $counts['waitlisted'],
            ];

            $grand['total'] += $apps->count();
            foreach (['submitted', 'under_review', 'accepted', 'rejected', 'waitlisted'] as $k) {
                $grand[$k] += $counts[$k];
            }
        }

        $rows[] = []; // spacer
        $rows[] = [
            'TOTAL',
            $grand['total'],
            $grand['submitted'],
            $grand['under_review'],
            $grand['accepted'],
            $grand['rejected'],
            $grand['waitlisted'],
        ];

        return $rows;
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            4 => ['font' => ['bold' => true]],
        ];
    }
}