<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentPatternsExport implements FromCollection, WithHeadings, WithStyles
{
    public function __construct(
        protected array $data
    ) {}

    public function collection(): Collection
    {
        $rows = [];

        if (isset($this->data['timeliness'])) {
            $rows[] = ['Timeliness Analysis Results'];
            foreach ($this->data['timeliness'] as $item) {
                $rows[] = [$item['student_name'], $item['on_time_rate'], $item['late_rate']];
            }
        }

        if (isset($this->data['frequency'])) {
            $rows[] = ['Frequency Analysis Results'];
            foreach ($this->data['frequency'] as $item) {
                $rows[] = [$item['student_name'], $item['payment_count'], $item['average_days']];
            }
        }

        if (isset($this->data['amount_patterns'])) {
            $rows[] = ['Amount Pattern Analysis Results'];
            foreach ($this->data['amount_patterns'] as $item) {
                $rows[] = [$item['student_name'], $item['average_amount'], $item['min_amount'], $item['max_amount']];
            }
        }

        if (isset($this->data['delinquency'])) {
            $rows[] = ['Delinquency Analysis Results'];
            foreach ($this->data['delinquency'] as $item) {
                $rows[] = [$item['student_name'], $item['overdue_count'], $item['average_days_overdue']];
            }
        }

        return new Collection($rows);
    }

    public function headings(): array
    {
        return [
            'Student Name',
            'Metric 1',
            'Metric 2',
            'Metric 3',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}