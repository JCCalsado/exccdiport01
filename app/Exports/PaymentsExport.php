<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaymentsExport implements FromCollection, WithHeadings, WithStyles
{
    public function __construct(
        protected array $data
    ) {}

    public function collection(): Collection
    {
        return new Collection($this->data['methods'] ?? []);
    }

    public function headings(): array
    {
        return [
            'Payment Method',
            'Amount',
            'Count',
            'Percentage',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}