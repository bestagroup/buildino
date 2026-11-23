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
            $styles = $this->document($zip, 'xl/styles.xml');
            $sheet = $this->document($zip, 'xl/worksheets/sheet1.xml');
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
                $before = $xpath->query('//s:pageMargins | //s:pageSetup | //s:headerFooter')->item(0);
                $sheet->documentElement->insertBefore($validations, $before);
            }
            $formats->setAttribute('count', (string) $formats->getElementsByTagNameNS(self::NS, 'xf')->length);
            if (! $zip->addFromString('xl/styles.xml', $styles->saveXML())
                || ! $zip->addFromString('xl/worksheets/sheet1.xml', $sheet->saveXML())) {
                throw new RuntimeException('Cannot protect generated workbook.');
            }
        } finally {
            if (! $zip->close()) {
                throw new RuntimeException('Cannot save protected workbook.');
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
