<?php

namespace App\Services\Identity;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class SafeSpreadsheet
{
    private const MAX_ARCHIVE_ENTRIES = 100;

    private const MAX_UNCOMPRESSED_BYTES = 20 * 1024 * 1024;

    /** @param array<int, string> $headers */
    public function createTemplate(array $headers): string
    {
        $relativePath = 'exports/account-templates/'.Str::uuid().'.xlsx';
        Storage::disk('local')->makeDirectory('exports/account-templates');
        $path = Storage::disk('local')->path($relativePath);
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw ValidationException::withMessages(['template' => 'The Excel template could not be generated.']);
        }

        $cells = collect($headers)->values()->map(function (string $header, int $index): string {
            $reference = $this->columnName($index + 1).'1';
            $value = htmlspecialchars($header, ENT_XML1 | ENT_QUOTES, 'UTF-8');

            return '<c r="'.$reference.'" t="inlineStr"><is><t>'.$value.'</t></is></c>';
        })->implode('');

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Accounts" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">'.$cells.'</row></sheetData></worksheet>');
        $zip->close();

        return $path;
    }

    /** @return array<int, array<int, string>> */
    public function read(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw $this->invalid('The Excel file is not a valid XLSX archive.');
        }

        try {
            $this->validateArchive($zip);
            $sharedStrings = $this->sharedStrings($zip);
            $sheetXml = $this->entry($zip, $this->firstWorksheetPath($zip));

            if (stripos($sheetXml, '<f') !== false) {
                throw $this->invalid('Spreadsheet formulas are not allowed in account imports.');
            }

            $document = $this->xml($sheetXml);
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rows = [];

            foreach ($xpath->query('//x:sheetData/x:row') ?: [] as $rowNode) {
                $values = [];
                $highestIndex = -1;

                foreach ($xpath->query('./x:c', $rowNode) ?: [] as $cell) {
                    $reference = (string) $cell->attributes?->getNamedItem('r')?->nodeValue;
                    $columnIndex = $this->columnIndex($reference);
                    $type = (string) $cell->attributes?->getNamedItem('t')?->nodeValue;
                    $valueNode = $xpath->query('./x:v', $cell)?->item(0);

                    if ($type === 'inlineStr') {
                        $value = '';

                        foreach ($xpath->query('./x:is//x:t', $cell) ?: [] as $textNode) {
                            $value .= $textNode->textContent;
                        }
                    } elseif ($type === 's') {
                        $value = $sharedStrings[(int) ($valueNode?->textContent ?? -1)] ?? '';
                    } else {
                        $value = $valueNode?->textContent ?? '';
                    }

                    $values[$columnIndex] = trim($value);
                    $highestIndex = max($highestIndex, $columnIndex);
                }

                $rows[] = $highestIndex < 0
                    ? []
                    : array_map(fn (int $index): string => $values[$index] ?? '', range(0, $highestIndex));
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function validateArchive(ZipArchive $zip): void
    {
        if ($zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
            throw $this->invalid('The Excel file contains too many internal entries.');
        }

        $totalSize = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = strtolower((string) ($stat['name'] ?? ''));
            $totalSize += (int) ($stat['size'] ?? 0);

            if ($totalSize > self::MAX_UNCOMPRESSED_BYTES) {
                throw $this->invalid('The expanded Excel file is too large.');
            }

            if (str_contains($name, 'vbaproject') || str_contains($name, 'externallinks') || str_contains($name, 'embeddings') || str_contains($name, 'activex')) {
                throw $this->invalid('Macros, embedded objects, and external spreadsheet links are not allowed.');
            }

            if (str_ends_with($name, '.rels')) {
                $relationships = $this->entry($zip, (string) ($stat['name'] ?? ''));

                if (stripos($relationships, 'TargetMode="External"') !== false) {
                    throw $this->invalid('External spreadsheet links are not allowed.');
                }
            }
        }
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (! is_string($xml)) {
            return [];
        }

        $document = $this->xml($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];

        foreach ($xpath->query('//x:si') ?: [] as $item) {
            $value = '';

            foreach ($xpath->query('.//x:t', $item) ?: [] as $textNode) {
                $value .= $textNode->textContent;
            }

            $strings[] = $value;
        }

        return $strings;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        $workbook = $this->xml($this->entry($zip, 'xl/workbook.xml'));
        $workbookPath = new DOMXPath($workbook);
        $workbookPath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbookPath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheet = $workbookPath->query('//x:sheets/x:sheet')?->item(0);
        $relationshipId = (string) $sheet?->attributes?->getNamedItemNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id')?->nodeValue;
        $relations = $this->xml($this->entry($zip, 'xl/_rels/workbook.xml.rels'));
        $relationsPath = new DOMXPath($relations);
        $relationsPath->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');

        foreach ($relationsPath->query('//r:Relationship') ?: [] as $relation) {
            if ((string) $relation->attributes?->getNamedItem('Id')?->nodeValue !== $relationshipId) {
                continue;
            }

            $target = str_replace('\\', '/', (string) $relation->attributes?->getNamedItem('Target')?->nodeValue);

            if ($target === '' || str_contains($target, '..')) {
                break;
            }

            return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.ltrim($target, '/');
        }

        throw $this->invalid('The Excel workbook does not contain a readable worksheet.');
    }

    private function xml(string $xml): DOMDocument
    {
        if (stripos($xml, '<!DOCTYPE') !== false) {
            throw $this->invalid('Spreadsheet document type declarations are not allowed.');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw $this->invalid('The Excel workbook contains invalid XML.');
        }

        return $document;
    }

    private function entry(ZipArchive $zip, string $name): string
    {
        $contents = $zip->getFromName($name);

        if (! is_string($contents)) {
            throw $this->invalid('The Excel workbook is missing required content.');
        }

        return $contents;
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/^([A-Z]+)/i', $reference, $matches);
        $letters = strtoupper($matches[1] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['accounts_file' => $message]);
    }
}
