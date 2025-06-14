<?php

declare(strict_types=1);

namespace Lelectrolux\ContentLinks\Exports;

use Closure;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Lelectrolux\ContentLinks\Contracts\HasContentLinks;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Hyperlink;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * @template TModel of Model&HasContentLinks
 */
final readonly class ContentLinksExport implements FromQuery, Responsable, WithColumnFormatting, WithColumnWidths, WithEvents, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(private string $class, private string $orderBy, private ?Closure $editUrl = null) {}

    public function query(): Builder
    {
        $instance = new ($this->class)();

        return $instance::query()->with('contentLinks')->orderBy($instance->qualifyColumn($this->orderBy));
    }

    public function toResponse($request): Response|BinaryFileResponse|SymfonyResponse
    {
        return $this->download(config('content-links.filename'));
    }

    /**
     * @param  Model&HasContentLinks  $row
     */
    public function map(mixed $row): array
    {
        $mapping = [];
        foreach ($row->contentLinks as $contentLink) {
            $mappedRow = [
                $row->getKey(),
                $row->{$this->orderBy},
                $contentLink->pivot->field,
                $contentLink->status ?? 'Err',
                $contentLink->url,
                $contentLink->redirect,
                Date::dateTimeToExcel($contentLink->updated_at),
            ];

            if ($this->editUrl) {
                $mappedRow[] = ($this->editUrl)($row);
            }

            $mapping[] = $mappedRow;
        }

        return $mapping;
    }

    public function headings(): array
    {
        $headings = [
            '#',
            $this->orderBy,
            'field',
            'status',
            'url',
            'redirect',
            'updated_at',
        ];

        if ($this->editUrl) {
            $headings[] = 'edit';
        }

        return $headings;
    }

    public function styles(Worksheet $sheet)
    {
        $max = $sheet->getHighestRow();

        foreach (['E', 'F', 'H'] as $col) {
            $sheet->getStyle("{$col}2:{$col}{$max}")->getFont()->getColor()->setARGB(Color::COLOR_BLUE);
            $sheet->getStyle("{$col}2:{$col}{$max}")->getFont()->setUnderline(true);
        }

        $ok = (new Conditional())
            ->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_EQUAL)
            ->addCondition(200);
        $ok->getStyle()->getFont()->getColor()->setARGB(Color::COLOR_DARKGREEN);
        $ok->getStyle()->getFont()->setBold(true);

        $nok = (new Conditional())
            ->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_NOTEQUAL)
            ->addCondition(200);
        $nok->getStyle()->getFont()->getColor()->setARGB(Color::COLOR_RED);
        $ok->getStyle()->getFont()->setBold(true);

        $sheet->getStyle("D2:D{$max}")->setConditionalStyles([$ok, $nok]);

        $sheet->setSelectedCell('A1');
    }

    public function columnFormats(): array
    {
        return [
            'G' => NumberFormat::FORMAT_DATE_DATETIME,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet;

                $max = $sheet->getHighestRow();

                for ($i = 1; $i <= $max; $i++) {
                    $urlCell = $sheet->getCell("E{$i}");
                    $redirectCell = $sheet->getCell("F{$i}");
                    $editCell = $sheet->getCell("H{$i}");

                    $this->addHyperlinkToCell($urlCell);
                    $this->addHyperlinkToCell($redirectCell);
                    $this->addHyperlinkToCell($editCell, 'edit');
                }
            },
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 7.5,
            'B' => 45,
            'C' => 15,
            'D' => 7.5,
            'E' => 60,
            'F' => 60,
            'G' => 15,
            'H' => 7.5,
        ];
    }

    private function addHyperlinkToCell(Cell $cell, ?string $label = null): void
    {
        $value = $cell->getValue();

        if ($value) {
            $cell->setHyperlink(new Hyperlink($cell->getValue()));

            if ($label) {
                $cell->setValue($label);
            }
        }
    }
}
