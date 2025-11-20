<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AgingReportExport implements FromCollection, WithHeadings, WithStyles
{
    public function __construct(
        protected array $data
    ) {}

    public function collection(): Collection
    {
        $rows = [];

        // Summary section
        $rows[] = ['Aging Report Summary'];
        $rows[] = ['Total Students', count($this->data['students'])];
        $rows[] = ['Total Outstanding', number_format($this->data['summary']['total_outstanding'], 2)];
        $rows[] = ['Average Balance', number_format($this->data['summary']['average_balance'], 2)];
        $rows[] = ['']; // Empty row

        // Student details
        $rows[] = ['Student Details'];
        $rows[] = ['Student Name', 'Student ID', 'Outstanding Balance', 'Days Outstanding', 'Age Bucket', 'Last Payment', 'Course', 'Year Level'];

        foreach ($this->data['students'] as $student) {
            $rows[] = [
                $student['student_name'],
                $student['student_id_number'],
                number_format($student['outstanding_balance'], 2),
                $student['days_outstanding'],
                $student['age_bucket'],
                $student['last_payment_date'] ? date('Y-m-d', strtotime($student['last_payment_date'])) : 'Never',
                $student['course'] ?? '',
                $student['year_level'] ?? '',
            ];
        }

        // Bucket summary
        $rows[] = ['']; // Empty row
        $rows[] = ['Bucket Summary'];
        $rows[] = ['Age Bucket', 'Student Count', 'Total Amount'];

        foreach ($this->data['bucket_summary'] as $bucket => $summary) {
            $rows[] = [$bucket, $summary['count'], number_format($summary['amount'], 2)];
        }

        return new Collection($rows);
    }

    public function headings(): array
    {
        return [
            'Aging Report Data',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
            5 => ['font' => ['bold' => true]],
        ];
    }
}