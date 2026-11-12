<?php

namespace App\Services\Web;

use App\Models\Building;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

final class BuildingWorkbookExporter
{
    public function write(string $path, Building $building, User $actor): void
    {
        $snapshots = app(BuildingWorkbookSnapshot::class);
        $units = Unit::with('floor')->whereHas('floor.block', fn ($q) => $q->where('building_id', $building->id))
            ->get()->sortBy(fn ($unit) => [$unit->floor->floor_number, $unit->unit_number])->values();
        if ($units->count() > 500) {
            throw ValidationException::withMessages(['file' => 'حداکثر ۵۰۰ واحد در هر فایل پشتیبانی می‌شود.']);
        }
        $options = new Options;
        $options->DEFAULT_ROW_STYLE = (new Style)->setFontName('B Nazanin')->setFontSize(12)->setFontColor('1F2937')->setCellAlignment('center')->setShouldWrapText();
        $options->DEFAULT_ROW_HEIGHT = 30;
        $writer = new Writer($options);
        $writer->openToFile($path);
        try {
            $sheet = $writer->getCurrentSheet()->setName('اطلاعات واحدها');
            $sheet->setSheetView((new SheetView)->setRightToLeft(true)->setShowGridLines(false)->setFreezeRow(2));
            $sheet->setColumnWidth(9, 1, 4, 5);
            $sheet->setColumnWidth(25, 2, 3, 9, 10, 12, 13);
            $sheet->setColumnWidth(20, 6, 7, 8, 11, 14, 15);
            $sheet->setAutoFilter(new AutoFilter(0, 1, 14, max(1, $units->count() + 1)));
            $sheet->setPrintTitleRows('1:1');
            $header = Row::fromValues(BuildingWorkbookService::HEADERS, (new Style)->setFontName('B Nazanin')->setFontSize(12)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('24476B')->setShouldWrapText()->setCellAlignment('center'));
            $header->setHeight(52);
            $writer->addRow($header);
            $metadata = [];
            foreach ($units as $index => $unit) {
                $state = $snapshots->state($unit, $building);
                $values = [$index + 1, ...$state['values']];
                $cells = [];
                foreach ($values as $column => $value) {
                    $style = (new Style)->setFontName('B Nazanin')->setFontSize(12)->setFontColor('1F2937')->setCellAlignment('center')->setShouldWrapText()
                        ->setBackgroundColor($column < 5 ? 'E2E8F0' : ($index % 2 ? 'EDF6FF' : 'FFFFFF'));
                    if (in_array($column, [0, 3, 5, 6, 7, 14], true)) {
                        $cells[] = new NumericCell($column === 5 ? (float) $value : (int) $value, $style->setFormat($column === 5 ? '0.00' : '0'));
                    } else {
                        // Explicit StringCell prevents spreadsheet formula injection and lost leading zeroes.
                        $cells[] = new StringCell((string) $value, $style->setFormat('@'));
                    }
                }
                $writer->addRow(new Row($cells));
                $metadata[] = Row::fromValues([(string) ($index + 1), $snapshots->token($state, $building, $actor)]);
            }
            $writer->addNewSheetAndMakeItCurrent()->setName('_buildino')->setIsVisible(false);
            $writer->addRows($metadata);
        } finally {
            $writer->close();
        }
    }
}
