<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One sheet per school — every applicant to that school's programmes,
 * grouped by programme, ordered by submission date.
 */
class SchoolSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private string $school,
        private Collection $applications,
    ) {}

    public function array(): array
    {
        $rows = [];

        $rows[] = [$this->school];
        $rows[] = []; // spacer
        $rows[] = ['Programme', 'Applicant', 'Email', 'NRC', 'Phone', 'Province', 'Status', 'Submitted', 'Decision date'];

        $byProgramme = $this->applications
            ->groupBy(fn ($app) => $app->programme?->name ?? 'Unknown programme')
            ->sortKeys();

        foreach ($byProgramme as $programmeName => $apps) {
            foreach ($apps->sortBy('submitted_at') as $app) {
                $user = $app->user;
                $rows[] = [
                    $programmeName,
                    $user?->full_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    $user?->email ?? '',
                    $user?->nrc ?? '',
                    $user?->phone ?? '',
                    $user?->province ?? '',
                    $this->statusLabel($app->status),
                    $app->submitted_at?->format('d M Y') ?? '',
                    $app->decision_at?->format('d M Y') ?? '',
                ];
            }
        }

        return $rows;
    }

    /**
     * Sheet titles have a 31-char Excel limit and forbid : \ / ? * [ ]
     */
    public function title(): string
    {
        $clean = preg_replace('/[:\\\\\/\?\*\[\]]/', '', $this->school);
        return mb_substr($clean, 0, 31);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            3 => ['font' => ['bold' => true]],
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted'    => 'Submitted',
            'under_review' => 'Under review',
            'accepted'     => 'Accepted',
            'rejected'     => 'Rejected',
            'waitlisted'   => 'Waitlisted',
            default        => ucfirst($status),
        };
    }
}