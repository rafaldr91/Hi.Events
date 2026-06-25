<?php

namespace HiEvents\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EventReportExport implements FromArray, WithHeadings, WithStyles
{
    private array $headings;
    private array $rows;

    public function withData(array $headings, array $rows): self
    {
        $this->headings = $headings;
        $this->rows = $rows;

        return $this;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
