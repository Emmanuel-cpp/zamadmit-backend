<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Workbook container: one Summary sheet + one sheet per school.
 *
 * Receives the already-filtered application collection from the controller
 * (so the export respects whatever filters the admin has applied on screen)
 * and splits it into per-school sheets.
 */
class ApplicationsBySchoolExport implements WithMultipleSheets
{
    public function __construct(
        private Collection $applications,
        private string $institutionName,
    ) {}

    public function sheets(): array
    {
        // Group by the programme's school. Programmes without a school
        // fall into "Other".
        $bySchool = $this->applications->groupBy(
            fn ($app) => $app->programme?->school ?: 'Other',
        )->sortKeys();

        $sheets = [
            new SummarySheet($bySchool, $this->institutionName),
        ];

        foreach ($bySchool as $school => $apps) {
            $sheets[] = new SchoolSheet($school, $apps);
        }

        return $sheets;
    }
}