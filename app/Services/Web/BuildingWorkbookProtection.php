<?php

namespace App\Services\Web;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/** OpenSpout supports sheet protection but not per-cell locked styles. */
final class BuildingWorkbookProtection
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function unlockInputCells(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Cannot open generated workbook for protection.');
        }
        try {
            $workbook = $this->document($zip, 'xl/workbook.xml');
            $this->normalizeProtectionAttributes($workbook, 'workbookProtection', ['lockRevisions' => 'lockRevision']);
            $this->normalizePrintTitles($workbook);
            $worksheets = [];
            // Read and transform every entry before writing: some libzip versions cannot
            // read a replaced compressed entry until the archive has been closed.
            // Apply view/protection compatibility to visible and hidden worksheets alike.
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->getNameIndex($index);
                if (! is_string($entry) || ! preg_match('~^xl/worksheets/sheet[0-9]+\.xml$~', $entry)) {
                    continue;
                }
                $worksheet = $this->document($zip, $entry);
                $this->normalizeProtectionAttributes($worksheet, 'sheetView', ['showZeroes' => 'showZeros']);
                $this->normalizeProtectionAttributes($worksheet, 'sheetProtection', ['hyperlink' => 'insertHyperlinks']);
                $worksheets[$entry] = $worksheet;
            }
            $styles = $this->document($zip, 'xl/styles.xml');
            $sheet = $worksheets['xl/worksheets/sheet1.xml'] ?? throw new RuntimeException('Generated workbook has no input sheet.');
            $xpath = new DOMXPath($sheet);
            $xpath->registerNamespace('s', self::NS);
            $formats = $styles->getElementsByTagNameNS(self::NS, 'cellXfs')->item(0);
            $original = iterator_to_array($formats->getElementsByTagNameNS(self::NS, 'xf'));
            $unlocked = [];
            foreach ($xpath->query('//s:sheetData/s:row/s:c') as $cell) {
                /** @var DOMElement $cell */
                // Only existing input cells E:T below the header are editable.
                if (! preg_match('/^([E-T])([0-9]+)$/', $cell->getAttribute('r'), $match) || (int) $match[2] < 2) {
                    continue;
                }
                $id = (int) $cell->getAttribute('s');
                if (! isset($unlocked[$id])) {
                    $style = $original[$id]->cloneNode(true);
                    $style->setAttribute('applyProtection', '1');
                    $protection = $styles->createElementNS(self::NS, 'protection');
                    $protection->setAttribute('locked', '0');
                    $style->appendChild($protection);
                    $unlocked[$id] = $formats->getElementsByTagNameNS(self::NS, 'xf')->length;
                    $formats->appendChild($style);
                }
                $cell->setAttribute('s', (string) $unlocked[$id]);
            }
            $lastRow = $xpath->query('//s:sheetData/s:row')->length;
            if ($lastRow > 1) {
                $validations = $sheet->createElementNS(self::NS, 'dataValidations');
                $validations->setAttribute('count', '1');
                $validation = $sheet->createElementNS(self::NS, 'dataValidation');
                foreach (['type' => 'list', 'allowBlank' => '0', 'showErrorMessage' => '1', 'errorStyle' => 'stop', 'sqref' => 'J2:J'.$lastRow] as $key => $value) {
                    $validation->setAttribute($key, $value);
                }
                $formula = $sheet->createElementNS(self::NS, 'formula1');
                $formula->appendChild($sheet->createTextNode('"مسکونی,اداری,تجاری"'));
                $validation->appendChild($formula);
                $validations->appendChild($validation);
                $before = $xpath->query('/s:worksheet/s:hyperlinks | /s:worksheet/s:printOptions | /s:worksheet/s:pageMargins | /s:worksheet/s:pageSetup | /s:worksheet/s:headerFooter | /s:worksheet/s:rowBreaks | /s:worksheet/s:colBreaks | /s:worksheet/s:customProperties | /s:worksheet/s:cellWatches | /s:worksheet/s:ignoredErrors | /s:worksheet/s:smartTags | /s:worksheet/s:drawing | /s:worksheet/s:legacyDrawing | /s:worksheet/s:legacyDrawingHF | /s:worksheet/s:picture | /s:worksheet/s:oleObjects | /s:worksheet/s:controls | /s:worksheet/s:webPublishItems | /s:worksheet/s:tableParts | /s:worksheet/s:extLst')->item(0);
                $sheet->documentElement->insertBefore($validations, $before);
            }
            $formats->setAttribute('count', (string) $formats->getElementsByTagNameNS(self::NS, 'xf')->length);
            $documents = [
                'xl/workbook.xml' => $workbook,
                'xl/styles.xml' => $styles,
                ...$worksheets,
            ];
            foreach ($documents as $entry => $document) {
                $xml = $document->saveXML();
                if ($xml === false || ! $zip->addFromString($entry, $xml)) {
                    throw new RuntimeException('Cannot protect generated workbook.');
                }
            }
        } finally {
            if (! $zip->close()) {
                throw new RuntimeException('Cannot save protected workbook.');
            }
        }
    }

    private function normalizePrintTitles(DOMDocument $workbook): void
    {
        $sheets = $workbook->getElementsByTagNameNS(self::NS, 'sheet');
        foreach ($workbook->getElementsByTagNameNS(self::NS, 'definedName') as $name) {
            if ($name->getAttribute('name') !== '_xlnm.Print_Titles') {
                continue;
            }
            $sheet = $sheets->item((int) $name->getAttribute('localSheetId'));
            if (! $sheet instanceof DOMElement || ! preg_match('/!\$?([0-9]+):\$?([0-9]+)$/', $name->textContent, $rows)) {
                throw new RuntimeException('Invalid generated print title reference.');
            }
            // Sheet names containing spaces must be quoted in Excel formulas.
            $reference = "'".str_replace("'", "''", $sheet->getAttribute('name'))."'!$".$rows[1].':$'.$rows[2];
            $name->textContent = $reference;
        }
    }

    /** Normalize legacy OpenSpout attribute names to SpreadsheetML without changing protection values. */
    private function normalizeProtectionAttributes(DOMDocument $document, string $element, array $attributes): void
    {
        foreach ($document->getElementsByTagNameNS(self::NS, $element) as $protection) {
            foreach ($attributes as $legacy => $standard) {
                if ($protection->hasAttribute($legacy)) {
                    if (! $protection->hasAttribute($standard)) {
                        $protection->setAttribute($standard, $protection->getAttribute($legacy));
                    }
                    $protection->removeAttribute($legacy);
                }
            }
        }
    }

    private function document(ZipArchive $zip, string $entry): DOMDocument
    {
        $document = new DOMDocument;
        $xml = $zip->getFromName($entry);
        if ($xml === false || ! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Invalid generated workbook XML.');
        }

        return $document;
    }
}
