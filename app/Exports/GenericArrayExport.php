<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GenericArrayExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected array|object $data,
        protected string       $sheetTitle = 'Report'
    ) {}

    public function array(): array
    {
        $rows = is_array($this->data) ? $this->data : (array) $this->data;

        // Flatten nested objects/arrays one level deep
        return array_map(function ($row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            return array_map(fn ($v) => is_array($v) || is_object($v) ? json_encode($v) : $v, $row);
        }, $rows);
    }

    public function headings(): array
    {
        $rows = is_array($this->data) ? $this->data : (array) $this->data;

        if (empty($rows)) {
            return [];
        }

        $first = is_object($rows[0]) ? (array) $rows[0] : (array) $rows[0];

        return array_map(
            fn ($key) => ucwords(str_replace('_', ' ', $key)),
            array_keys($first)
        );
    }

    public function title(): string
    {
        return ucwords(str_replace('-', ' ', $this->sheetTitle));
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Bold + background on heading row
            1 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F4E79']],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }
}
