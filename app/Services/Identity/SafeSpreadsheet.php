<?php

namespace App\Services\Identity;

use App\Enums\ProfileOptionField;
use App\Exceptions\SpreadsheetRuntimeUnavailable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Throwable;
use ZipArchive;

/**
 * Generates verified ECRATS templates and parses untrusted uploads through a bounded OOXML contract.
 */
class SafeSpreadsheet
{
    /** Limits generated and imported account rows to a practical synchronous workload. */
    public const MAX_ACCOUNT_ROWS = 250;

    /** Bounds archive complexity and expanded data before any uploaded XML is parsed. */
    private const MAX_ARCHIVE_ENTRIES = 150;

    private const MAX_UNCOMPRESSED_BYTES = 20 * 1024 * 1024;

    private const MAX_OPTION_ROWS = 1000;

    private const MAX_SHARED_STRINGS = 10000;

    /** Bounds private generated-template retention when delivery is interrupted before response cleanup. */
    private const GENERATED_TEMPLATE_TTL_MINUTES = 60;

    /** Defines the only accepted worksheet names and order for official account templates. */
    private const SHEET_NAMES = ['Accounts', 'Options', 'Instructions'];

    /** Maps controlled profile fields to unique workbook-level names used by Excel dropdowns. */
    private const RANGE_NAMES = [
        'year_level' => 'EcratsYearLevelOptions',
        'institution' => 'EcratsInstitutionOptions',
        'department' => 'EcratsDepartmentOptions',
        'program' => 'EcratsProgramOptions',
        'reviewer_classification' => 'EcratsReviewerClassificationOptions',
    ];

    /** Lists the minimum Open XML parts required before a generated workbook may be downloaded. */
    private const REQUIRED_GENERATED_ENTRIES = [
        '[Content_Types].xml',
        '_rels/.rels',
        'docProps/app.xml',
        'docProps/core.xml',
        'xl/workbook.xml',
        'xl/_rels/workbook.xml.rels',
        'xl/styles.xml',
        'xl/worksheets/sheet1.xml',
        'xl/worksheets/sheet2.xml',
        'xl/worksheets/sheet3.xml',
    ];

    public function __construct(
        private readonly SpreadsheetRuntime $runtime,
    ) {}

    /**
     * Build a bounded OOXML workbook and verify it with a trusted Xlsx reader before returning its path.
     *
     * @param  array<string, mixed>  $type
     * @param  array<string, array<int, string>>  $options
     */
    public function createTemplate(array $type, array $options): string
    {
        $this->runtime->assertAvailable(requireWritableStorage: true);

        // Create separate private assembly and delivery paths so the browser never receives the hand-built intermediate package.
        $identifier = (string) Str::uuid();
        $relativePath = 'exports/account-templates/'.$identifier.'.xlsx';
        $assemblyRelativePath = 'exports/account-templates/'.$identifier.'.assembly.xlsx';
        $this->cleanupExpiredTemplates();
        $path = Storage::disk('local')->path($relativePath);
        $assemblyPath = Storage::disk('local')->path($assemblyRelativePath);
        $zip = new ZipArchive;
        $archiveIsOpen = false;

        try {
            // Open a fresh ZIP package and fail before response headers are ever attached.
            if ($zip->open($assemblyPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw ValidationException::withMessages(['template' => 'The Excel template could not be generated.']);
            }
            $archiveIsOpen = true;

            // Normalize active options once so named ranges, validations, and instructions use identical values.
            $options = $this->normalizeOptions($options);
            [$optionsXml, $definedNames, $warnings] = $this->optionsWorksheet($options);
            $accountsXml = $this->accountsWorksheet($type, $options);
            $instructionsXml = $this->instructionsWorksheet($type, $options, $warnings);
            $definedNamesXml = $definedNames === []
                ? ''
                : '<definedNames>'.implode('', $definedNames).'</definedNames>';
            $generatedAt = now()->utc()->format('Y-m-d\TH:i:s\Z');

            // Declare worksheet, style, and document-property parts using standard macro-free XLSX content types.
            $this->addEntry($zip, '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');

            // Link the package root to the workbook and both standard document-property parts.
            $this->addEntry($zip, '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');

            // Store minimal standard metadata so desktop spreadsheet applications receive a complete package.
            $this->addEntry($zip, 'docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>ECRATS</Application><AppVersion>1.0</AppVersion></Properties>');
            $this->addEntry($zip, 'docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>ECRATS Account Import Template</dc:title><dc:creator>ECRATS</dc:creator><cp:lastModifiedBy>ECRATS</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">'.$generatedAt.'</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">'.$generatedAt.'</dcterms:modified></cp:coreProperties>');

            // Write workbook children in schema order: views and sheets must precede defined names.
            $this->addEntry($zip, 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="Accounts" sheetId="1" r:id="rId1"/><sheet name="Options" sheetId="2" state="hidden" r:id="rId2"/><sheet name="Instructions" sheetId="3" r:id="rId3"/></sheets>'.$definedNamesXml.'</workbook>');

            // Link each workbook relationship only to the three worksheets and shared style table.
            $this->addEntry($zip, 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/><Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');

            // Add the generated styles and role-specific worksheet XML to the open package.
            $this->addEntry($zip, 'xl/styles.xml', $this->stylesXml());
            $this->addEntry($zip, 'xl/worksheets/sheet1.xml', $accountsXml);
            $this->addEntry($zip, 'xl/worksheets/sheet2.xml', $optionsXml);
            $this->addEntry($zip, 'xl/worksheets/sheet3.xml', $instructionsXml);

            // Finalize the ZIP package before performing file, entry, and reader validation.
            if (! $zip->close()) {
                throw ValidationException::withMessages(['template' => 'The Excel template could not be finalized.']);
            }
            $archiveIsOpen = false;

            // Inspect the assembled package before asking the trusted Xlsx writer to canonicalize it.
            $this->verifyGeneratedTemplate($assemblyPath, $type, $options);

            // Save through PhpSpreadsheet's supported Xlsx writer so relationship ordering and package metadata are standardized.
            $this->writeCanonicalXlsx($assemblyPath, $path);

            // Verify the exact delivery file again because this is the binary the controller will attach to the response.
            $this->verifyGeneratedTemplate($path, $type, $options);
            Storage::disk('local')->delete($assemblyRelativePath);

            return $path;
        } catch (Throwable $exception) {
            // Close any unfinished archive and remove partial output so no invalid XLSX can be downloaded later.
            if ($archiveIsOpen) {
                $zip->close();
            }
            Storage::disk('local')->delete([$relativePath, $assemblyRelativePath]);

            // Preserve already-safe validation messages while hiding internal diagnostics from the response.
            if ($exception instanceof SpreadsheetRuntimeUnavailable || $exception instanceof ValidationException) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'template' => 'The Excel template could not be verified. Please try again.',
            ]);
        }
    }

    /**
     * Verify a generated workbook package, named ranges, dropdowns, and trusted-reader round trip.
     *
     * @param  array<string, mixed>  $type
     * @param  array<string, array<int, string>>  $options
     */
    public function verifyGeneratedTemplate(string $path, array $type, array $options): void
    {
        $this->runtime->assertAvailable();

        // Reject missing or empty temporary files before opening their ZIP container.
        if (! is_file($path) || filesize($path) === 0) {
            throw ValidationException::withMessages(['template' => 'The generated Excel template is empty.']);
        }

        // Confirm the XLSX ZIP signature so an HTML or JSON response cannot masquerade as a workbook.
        $signature = file_get_contents($path, false, null, 0, 4);

        if ($signature !== "PK\x03\x04") {
            throw ValidationException::withMessages(['template' => 'The generated Excel template is not a valid XLSX package.']);
        }

        // Open the completed package and require every standard workbook part used by ECRATS.
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['template' => 'The generated Excel template could not be reopened.']);
        }

        try {
            // Verify required entries before an XML reader attempts to resolve workbook relationships.
            foreach (self::REQUIRED_GENERATED_ENTRIES as $entry) {
                if ($zip->locateName($entry, ZipArchive::FL_NOCASE) === false) {
                    throw ValidationException::withMessages(['template' => 'The generated Excel template is missing required workbook content.']);
                }
            }

            // Assert workbook child ordering because defined names before sheets causes Microsoft Excel repair warnings.
            $workbookDocument = $this->xml($this->entry($zip, 'xl/workbook.xml'));
            $elementOrder = [];

            foreach ($workbookDocument->documentElement?->childNodes ?? [] as $node) {
                if ($node instanceof DOMElement) {
                    $elementOrder[] = $node->localName;
                }
            }

            $sheetsPosition = array_search('sheets', $elementOrder, true);
            $definedNamesPosition = array_search('definedNames', $elementOrder, true);

            if ($sheetsPosition === false
                || ($definedNamesPosition !== false && $definedNamesPosition < $sheetsPosition)) {
                throw ValidationException::withMessages(['template' => 'The generated Excel template has an invalid workbook structure.']);
            }
        } finally {
            // Release the ZIP handle before PhpSpreadsheet reopens the same private file.
            $zip->close();
        }

        // Load only the trusted application-generated workbook through PhpSpreadsheet's Xlsx reader.
        $reader = new XlsxReader;
        $reader->setReadDataOnly(false);
        $workbook = $reader->load($path);

        try {
            // Require the exact approved worksheet set, keeping only Options hidden from account editors.
            if ($workbook->getSheetNames() !== self::SHEET_NAMES
                || $workbook->getSheetByName('Accounts')?->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE
                || $workbook->getSheetByName('Options')?->getSheetState() !== Worksheet::SHEETSTATE_HIDDEN
                || $workbook->getSheetByName('Instructions')?->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE) {
                throw ValidationException::withMessages(['template' => 'The generated Excel template has invalid worksheets.']);
            }

            // Validate every workbook-level option range and every expected active-option range.
            $this->validateGeneratedNamedRanges($workbook, $options);

            // Validate role-specific dropdown formulas and target cells against the verified named ranges.
            $this->validateGeneratedDataValidations($workbook, $type, $options);
        } finally {
            // Disconnect worksheet objects promptly to keep repeated template downloads memory-bounded.
            $workbook->disconnectWorksheets();
            unset($workbook);
        }
    }

    /**
     * Add one required XML part and fail if ZipArchive cannot persist it.
     */
    private function addEntry(ZipArchive $zip, string $name, string $contents): void
    {
        // A failed write must abort generation rather than leave a truncated package with an XLSX filename.
        if (! $zip->addFromString($name, $contents)) {
            throw ValidationException::withMessages(['template' => 'The Excel template could not be assembled.']);
        }
    }

    /**
     * Re-save an inspected intermediate workbook using PhpSpreadsheet's supported Xlsx writer.
     */
    private function writeCanonicalXlsx(string $sourcePath, string $destinationPath): void
    {
        // Reopen the private intermediate package with formulas preserved so dropdown definitions survive the writer pass.
        $reader = new XlsxReader;
        $reader->setReadDataOnly(false);
        $workbook = $reader->load($sourcePath);

        try {
            // Disable formula precalculation because templates contain no cell formulas and must not invoke calculation work.
            $writer = new XlsxWriter($workbook);
            $writer->setPreCalculateFormulas(false);
            $writer->save($destinationPath);
        } finally {
            // Release worksheet references after writing to keep repeated downloads memory-bounded.
            $workbook->disconnectWorksheets();
            unset($workbook);
        }
    }

    /**
     * Remove only stale generated XLSX artifacts left by interrupted private download responses.
     */
    private function cleanupExpiredTemplates(): void
    {
        // Scan the bounded export directory and delete files older than the fallback retention window.
        $disk = Storage::disk('local');
        $cutoff = now()->subMinutes(self::GENERATED_TEMPLATE_TTL_MINUTES)->timestamp;

        foreach ($disk->files('exports/account-templates') as $file) {
            if (str_ends_with(Str::lower($file), '.xlsx') && $disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }

    /**
     * Confirm that named ranges are unique, non-empty, and point to active values on Options.
     *
     * @param  array<string, array<int, string>>  $options
     */
    private function validateGeneratedNamedRanges(Spreadsheet $workbook, array $options): void
    {
        // Build exact references from the stable Options column order, omitting groups with no active values.
        $expectedRanges = [];

        foreach (ProfileOptionField::cases() as $index => $field) {
            $values = $options[$field->value] ?? [];

            if ($values === []) {
                continue;
            }

            $column = $this->columnName($index + 1);
            $expectedRanges[self::RANGE_NAMES[$field->value]] = [
                'column' => $column,
                'reference' => "'Options'!\${$column}\$2:\${$column}\$".(count($values) + 1),
                'values' => $values,
            ];
        }

        $actualRanges = [];

        // Inspect every workbook-level range for exact naming, scope, quoting, column, and forward row bounds.
        foreach ($workbook->getNamedRanges() as $namedRange) {
            $name = $namedRange->getName();
            $reference = $namedRange->getRange();
            $expected = $expectedRanges[$name] ?? null;
            $normalizedName = Str::lower($name);

            if (isset($actualRanges[$normalizedName])
                || $expected === null
                || $namedRange->getWorksheet()?->getTitle() !== 'Options'
                || $reference !== $expected['reference']) {
                throw ValidationException::withMessages(['template' => 'The generated Excel template contains an invalid named range.']);
            }

            $actualRanges[$normalizedName] = $name;

            // Confirm every cell addressed by the range contains the normalized active value in the same order.
            foreach ($expected['values'] as $valueIndex => $expectedValue) {
                $coordinate = $expected['column'].($valueIndex + 2);
                $actualValue = (string) $workbook->getSheetByName('Options')?->getCell($coordinate)->getValue();

                if ($actualValue !== $expectedValue) {
                    throw ValidationException::withMessages(['template' => 'The generated Excel template contains an invalid dropdown value.']);
                }
            }
        }

        // Empty option groups intentionally omit names, while every populated group must have exactly one.
        $expectedNames = array_map(Str::lower(...), array_keys($expectedRanges));
        sort($expectedNames);
        $actualNames = array_keys($actualRanges);
        sort($actualNames);

        if ($actualNames !== $expectedNames) {
            throw ValidationException::withMessages(['template' => 'The generated Excel template has incomplete dropdown ranges.']);
        }
    }

    /**
     * Confirm that Accounts dropdowns target the correct columns and verified workbook names.
     *
     * @param  array<string, mixed>  $type
     * @param  array<string, array<int, string>>  $options
     */
    private function validateGeneratedDataValidations(Spreadsheet $workbook, array $type, array $options): void
    {
        // Derive exact validation targets, formulas, and blank rules from role columns with active options.
        $fields = array_values($type['template_columns']);
        $expectedValidations = [];

        foreach (self::RANGE_NAMES as $field => $rangeName) {
            $fieldIndex = array_search($field, $fields, true);

            if ($fieldIndex === false || ($options[$field] ?? []) === []) {
                continue;
            }

            $column = $this->columnName($fieldIndex + 1);
            $expectedValidations[$column.'3:'.$column.(self::MAX_ACCOUNT_ROWS + 2)] = [
                'formula' => $rangeName,
                'allow_blank' => ! in_array($field, $type['required_fields'], true),
            ];
        }

        // Normalize PhpSpreadsheet's formula representation before comparing it with workbook-level names.
        $actualValidations = [];

        foreach ($workbook->getSheetByName('Accounts')?->getDataValidationCollection() ?? [] as $validation) {
            $reference = (string) $validation->getSqref();
            $rangeName = ltrim($validation->getFormula1(), '=');

            if ($reference === ''
                || $rangeName === ''
                || isset($actualValidations[$reference])
                || $validation->getType() !== DataValidation::TYPE_LIST
                || ! $validation->getShowErrorMessage()) {
                throw ValidationException::withMessages(['template' => 'The generated Excel template contains invalid dropdown validation.']);
            }

            $actualValidations[$reference] = [
                'formula' => $rangeName,
                'allow_blank' => $validation->getAllowBlank(),
            ];
        }

        // Require an exact match so no broken or unexpected dropdown survives generation.
        ksort($actualValidations);
        ksort($expectedValidations);

        if ($actualValidations !== $expectedValidations) {
            throw ValidationException::withMessages(['template' => 'The generated Excel template has incomplete dropdown validation.']);
        }
    }

    /**
     * Validate the complete workbook contract before returning only approved Accounts rows.
     *
     * @param  array<string, mixed>  $type
     * @return array{
     *     rows: array<int, array{row: int, values: array<int, string>}>,
     *     example_row_marked: bool
     * }
     */
    public function read(string $path, array $type): array
    {
        try {
            $this->runtime->assertAvailable();
        } catch (SpreadsheetRuntimeUnavailable) {
            // Upload validation retains its field-level UX while template downloads preserve the typed diagnostic.
            throw ValidationException::withMessages([
                'accounts_file' => SpreadsheetRuntimeUnavailable::USER_MESSAGE,
            ]);
        }

        $signature = file_get_contents($path, false, null, 0, 8);

        if (! is_string($signature) || ! str_starts_with($signature, "PK\x03\x04")) {
            throw $this->invalid('The uploaded file is not an unencrypted XLSX workbook.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw $this->invalid('The Excel file is corrupted or password-protected.');
        }

        try {
            $this->validateArchive($zip);
            $sharedStrings = $this->sharedStrings($zip);
            $sheets = $this->workbookSheets($zip);
            $this->validateWorkbookStructure($sheets);
            $sheetXml = [];

            foreach ($sheets as $sheet) {
                $xml = $this->entry($zip, $sheet['path']);

                if (preg_match('/<f(?:\s|>)/i', $xml) === 1) {
                    throw $this->invalid('Spreadsheet formulas are not allowed anywhere in the account template.');
                }

                $sheetXml[$sheet['name']] = $xml;
            }

            $this->validateOptionsSheet($sheetXml['Options'], $sharedStrings);
            $exampleRowMarked = $this->validateInstructionsSheet(
                $sheetXml['Instructions'],
                $sharedStrings,
                $type,
            );

            return [
                'rows' => $this->validateAccountsSheet($sheetXml['Accounts'], $sharedStrings, $type),
                'example_row_marked' => $exampleRowMarked,
            ];
        } finally {
            $zip->close();
        }
    }

    /** @param array<string, array<int, string>> $options @return array<string, array<int, string>> */
    private function normalizeOptions(array $options): array
    {
        $normalized = [];

        foreach (ProfileOptionField::cases() as $field) {
            $normalized[$field->value] = collect($options[$field->value] ?? [])
                ->map(fn ($value): string => Str::squish((string) $value))
                ->filter()
                ->unique(fn (string $value): string => Str::lower($value))
                ->values()
                ->take(self::MAX_OPTION_ROWS)
                ->all();
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<int, string>>  $options
     * @return array{0: string, 1: array<int, string>, 2: array<int, string>}
     */
    private function optionsWorksheet(array $options): array
    {
        $fields = ProfileOptionField::cases();
        $headerCells = '';
        $definedNames = [];
        $warnings = [];
        $maxValues = 0;

        foreach ($fields as $index => $field) {
            $column = $this->columnName($index + 1);
            $headerCells .= $this->inlineCell($column.'1', $field->label(), 1);
            $values = $options[$field->value] ?? [];
            $maxValues = max($maxValues, count($values));

            if ($values === []) {
                $warnings[] = "No active {$field->label()} options are configured.";

                continue;
            }

            $rangeName = self::RANGE_NAMES[$field->value];
            $definedNames[] = '<definedName name="'.$rangeName.'">\'Options\'!$'.$column.'$2:$'.$column.'$'.(count($values) + 1).'</definedName>';
        }

        $rows = '<row r="1" ht="28" customHeight="1">'.$headerCells.'</row>';

        for ($valueIndex = 0; $valueIndex < $maxValues; $valueIndex++) {
            $rowNumber = $valueIndex + 2;
            $cells = '';

            foreach ($fields as $columnIndex => $field) {
                $value = $options[$field->value][$valueIndex] ?? null;

                if ($value !== null) {
                    $cells .= $this->inlineCell($this->columnName($columnIndex + 1).$rowNumber, $value, 3);
                }
            }

            $rows .= '<row r="'.$rowNumber.'" ht="22" customHeight="1">'.$cells.'</row>';
        }

        $lastColumn = $this->columnName(count($fields));
        $columns = collect($fields)->values()->map(function (ProfileOptionField $field, int $index): string {
            $width = match ($field) {
                ProfileOptionField::YearLevel => 20,
                ProfileOptionField::Institution => 48,
                ProfileOptionField::Department => 36,
                ProfileOptionField::Program => 46,
                ProfileOptionField::ReviewerClassification => 30,
            };
            $column = $index + 1;

            return '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        })->implode('');

        return [
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$lastColumn.max(1, $maxValues + 1).'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'.$columns.'</cols><sheetData>'.$rows.'</sheetData><sheetProtection sheet="1" objects="1" scenarios="1"/><autoFilter ref="A1:'.$lastColumn.max(1, $maxValues + 1).'"/></worksheet>',
            $definedNames,
            $warnings,
        ];
    }

    /** @param array<string, mixed> $type @param array<string, array<int, string>> $options */
    private function accountsWorksheet(array $type, array $options): string
    {
        $headers = $type['template_headers'];
        $fields = array_values($type['template_columns']);
        $lastColumn = $this->columnName(count($headers));
        $lastRow = self::MAX_ACCOUNT_ROWS + 2;
        $headerCells = '';
        $exampleCells = '';

        foreach ($headers as $index => $header) {
            $column = $this->columnName($index + 1);
            $headerCells .= $this->inlineCell($column.'1', $header, 1);
            $exampleCells .= $this->inlineCell($column.'2', (string) ($type['example_row'][$header] ?? ''), 2);
        }

        $rows = '<row r="1" ht="30" customHeight="1">'.$headerCells.'</row>'
            .'<row r="2" ht="42" customHeight="1">'.$exampleCells.'</row>';

        for ($row = 3; $row <= $lastRow; $row++) {
            $cells = '';

            foreach ($headers as $index => $header) {
                $cells .= $this->inlineCell($this->columnName($index + 1).$row, '', 3);
            }

            $rows .= '<row r="'.$row.'" ht="22" customHeight="1">'.$cells.'</row>';
        }

        $columns = collect($fields)->values()->map(function (string $field, int $index): string {
            $width = match ($field) {
                'first_name', 'middle_name' => 20,
                'last_name' => 22,
                'suffix' => 12,
                'email' => 34,
                'student_number', 'employee_id' => 30,
                'phone_number' => 20,
                'year_level' => 18,
                'institution' => 48,
                'department', 'position_title' => 36,
                'program' => 46,
                'reviewer_classification' => 30,
                default => 20,
            };
            $column = $index + 1;

            return '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        })->implode('');
        $validations = [];

        foreach (self::RANGE_NAMES as $field => $rangeName) {
            $fieldIndex = array_search($field, $fields, true);

            if ($fieldIndex === false || ($options[$field] ?? []) === []) {
                continue;
            }

            $column = $this->columnName($fieldIndex + 1);
            $allowBlank = in_array($field, $type['required_fields'], true) ? '0' : '1';
            $validations[] = '<dataValidation type="list" allowBlank="'.$allowBlank.'" showInputMessage="1" showErrorMessage="1" errorTitle="Invalid option" error="Select an option from the current official list." promptTitle="Approved values" prompt="Choose a value from this dropdown. The server validates it again during upload." sqref="'.$column.'3:'.$column.$lastRow.'"><formula1>'.$rangeName.'</formula1></dataValidation>';
        }

        $validationXml = $validations === []
            ? ''
            : '<dataValidations count="'.count($validations).'">'.implode('', $validations).'</dataValidations>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$lastColumn.$lastRow.'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'.$columns.'</cols><sheetData>'.$rows.'</sheetData><autoFilter ref="A1:'.$lastColumn.$lastRow.'"/>'.$validationXml.'</worksheet>';
    }

    /** @param array<string, mixed> $type @param array<string, array<int, string>> $options @param array<int, string> $warnings */
    private function instructionsWorksheet(array $type, array $options, array $warnings): string
    {
        $acceptedValues = collect(ProfileOptionField::cases())
            ->map(fn (ProfileOptionField $field): string => $field->label().': '.(($options[$field->value] ?? []) === []
                ? 'No active options configured'
                : implode(', ', $options[$field->value])))
            ->implode("\n");
        $instructions = [
            ['ECRATS Official Excel Account Template', 'Complete only the Accounts worksheet and upload this workbook through ECRATS.'],
            ['Account Type Key', $type['key']],
            ['Account Type', $type['label']],
            ['Purpose', 'Create authorized '.$type['label'].' accounts after validation and explicit confirmation.'],
            ['Required Fields', implode(', ', $type['required_headers'])],
            ['Optional Fields', implode(', ', $type['optional_headers'])],
            ['Worksheet Names', 'Accounts, Options, Instructions. Do not rename, add, remove, hide, or duplicate worksheets.'],
            ['Header Requirements', 'Row 1 must keep the exact headers and order supplied in the Accounts worksheet.'],
            ['Expected Formats', 'Names are plain text; identifiers and email addresses are text; controlled fields must use active approved values.'],
            ['Accepted Dropdown Values', $acceptedValues],
            ['Institutional Identifier', 'Keep Student Numbers and Employee IDs as text. Leading zeros must be preserved.'],
            ['Student Number', 'Required for Student Researcher accounts. Use the official institutional value exactly.'],
            ['Employee ID', 'Required for employee-based accounts. Use the official institutional value exactly.'],
            ['Email', 'Use one valid, unique email address. Gmail, institutional, .edu, Outlook, Hotmail, Yahoo, and other valid domains are supported.'],
            ['Example Row Marker', AccountTypeCatalog::EXAMPLE_MARKER],
            ['Example Row', 'Row 2 is ignored only while the Example Row Marker above remains exact. Remove the marker to validate Row 2 as ordinary account data.'],
            ['Maximum Upload Size', '2 MB'],
            ['Maximum Account Rows', (string) self::MAX_ACCOUNT_ROWS],
            ['Upload Steps', 'Download a current template, complete Accounts starting on Row 3, save as .xlsx, upload one file, then select Validate.'],
            ['Validation Steps', 'Review totals, generated usernames, errors, warnings, duplicate rows, and existing accounts before confirmation.'],
            ['Confirmation Steps', 'Accounts are created only after an authorized user explicitly confirms the valid preview.'],
            ['Duplicate Handling', 'The first valid identity is eligible. Later workbook duplicates are skipped; conflicting identities are reported.'],
            ['Existing Accounts', 'Existing accounts are skipped and are never overwritten or merged automatically.'],
            ['Workbook Safety', 'Do not insert macros, formulas, embedded files, external workbook links, extra columns, or unexpected worksheets.'],
            ['Dropdown Security', 'Excel dropdowns are a convenience only. ECRATS validates every uploaded value again on the server.'],
            ['Current Template', 'Previously downloaded templates do not update automatically. Download a new template when options change.'],
        ];

        foreach ($warnings as $warning) {
            $instructions[] = ['Configuration Warning', $warning.' Contact the RES Lead before importing accounts that require this field.'];
        }

        $rows = '';

        foreach ($instructions as $index => [$label, $value]) {
            $row = $index + 1;
            $style = $row === 1 ? 4 : 3;
            $rows .= '<row r="'.$row.'" ht="'.($row === 1 ? 34 : 32).'" customHeight="1">'
                .$this->inlineCell('A'.$row, $label, $style)
                .$this->inlineCell('B'.$row, $value, $style)
                .'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:B'.count($instructions).'"/><cols><col min="1" max="1" width="34" customWidth="1"/><col min="2" max="2" width="100" customWidth="1"/></cols><sheetData>'.$rows.'</sheetData></worksheet>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/><family val="2"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/><family val="2"/></font><font><b/><color rgb="FF176F3D"/><sz val="12"/><name val="Calibri"/><family val="2"/></font></fonts><fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF176F3D"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF3CD"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF6EE"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD8E2DC"/></left><right style="thin"><color rgb="FFD8E2DC"/></right><top style="thin"><color rgb="FFD8E2DC"/></top><bottom style="thin"><color rgb="FFD8E2DC"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="49" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="49" fontId="0" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="49" fontId="2" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function validateArchive(ZipArchive $zip): void
    {
        if ($zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
            throw $this->invalid('The Excel file contains too many internal entries.');
        }

        $totalSize = 0;
        $blockedParts = ['vbaproject', 'externallinks', 'embeddings', 'activex', 'oleobjects', 'customui', 'querytables', 'connections'];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $entryName = (string) ($stat['name'] ?? '');
            $name = strtolower(str_replace('\\', '/', $entryName));
            $totalSize += (int) ($stat['size'] ?? 0);

            if ($totalSize > self::MAX_UNCOMPRESSED_BYTES) {
                throw $this->invalid('The expanded Excel file is too large.');
            }

            $encryption = method_exists($zip, 'getEncryptionName') ? $zip->getEncryptionName($index) : false;

            if (is_string($encryption) && ! in_array(strtolower($encryption), ['', 'none'], true)) {
                throw $this->invalid('Password-protected or encrypted workbooks are not supported.');
            }

            if (collect($blockedParts)->contains(fn (string $part): bool => str_contains($name, $part))) {
                throw $this->invalid('Macros, embedded objects, connections, and external workbook links are not allowed.');
            }

            if (str_ends_with($name, '.rels')) {
                $relationships = $this->entry($zip, $entryName);

                if (preg_match('/TargetMode\s*=\s*["\']External["\']/i', $relationships) === 1) {
                    throw $this->invalid('External workbook links are not allowed.');
                }
            }
        }

        $contentTypes = $this->entry($zip, '[Content_Types].xml');

        if (! str_contains($contentTypes, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml')
            || stripos($contentTypes, 'macroEnabled') !== false) {
            throw $this->invalid('The uploaded file is not a standard macro-free XLSX workbook.');
        }
    }

    /** @return array<int, array{name: string, state: string, path: string}> */
    private function workbookSheets(ZipArchive $zip): array
    {
        $workbook = $this->xml($this->entry($zip, 'xl/workbook.xml'));
        $workbookPath = new DOMXPath($workbook);
        $workbookPath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $relations = $this->xml($this->entry($zip, 'xl/_rels/workbook.xml.rels'));
        $relationsPath = new DOMXPath($relations);
        $relationsPath->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $relationTargets = [];

        foreach ($relationsPath->query('//r:Relationship') ?: [] as $relation) {
            $id = (string) $relation->attributes?->getNamedItem('Id')?->nodeValue;
            $target = str_replace('\\', '/', (string) $relation->attributes?->getNamedItem('Target')?->nodeValue);

            if ($id === '' || $target === '' || str_contains($target, '..')) {
                continue;
            }

            $relationTargets[$id] = str_starts_with($target, '/')
                ? ltrim($target, '/')
                : 'xl/'.ltrim($target, '/');
        }

        $sheets = [];

        foreach ($workbookPath->query('//x:sheets/x:sheet') ?: [] as $sheet) {
            $relationshipId = (string) $sheet->attributes?->getNamedItemNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'id',
            )?->nodeValue;
            $path = $relationTargets[$relationshipId] ?? null;

            if (! is_string($path) || ! str_starts_with($path, 'xl/worksheets/')) {
                throw $this->invalid('The Excel workbook contains an invalid worksheet relationship.');
            }

            $sheets[] = [
                'name' => (string) $sheet->attributes?->getNamedItem('name')?->nodeValue,
                'state' => strtolower((string) $sheet->attributes?->getNamedItem('state')?->nodeValue),
                'path' => $path,
            ];
        }

        return $sheets;
    }

    /** @param array<int, array{name: string, state: string, path: string}> $sheets */
    private function validateWorkbookStructure(array $sheets): void
    {
        $names = array_column($sheets, 'name');

        if (count($sheets) !== count(self::SHEET_NAMES)
            || count($names) !== count(array_unique($names))
            || $names !== self::SHEET_NAMES) {
            throw $this->invalid('The workbook must contain exactly these worksheets in order: Accounts, Options, Instructions.');
        }

        foreach ($sheets as $sheet) {
            if ($sheet['name'] === 'Options' && ! in_array($sheet['state'], ['hidden', 'veryhidden'], true)) {
                throw $this->invalid('The Options worksheet must remain hidden in the official template.');
            }

            if ($sheet['name'] !== 'Options' && $sheet['state'] !== '') {
                throw $this->invalid("The {$sheet['name']} worksheet must remain visible.");
            }
        }
    }

    /** @param array<int, string> $sharedStrings */
    private function validateOptionsSheet(string $xml, array $sharedStrings): void
    {
        $expectedHeaders = collect(ProfileOptionField::cases())->map->label()->all();
        $rows = $this->sheetRows($xml, $sharedStrings, count($expectedHeaders), self::MAX_OPTION_ROWS + 1);
        $headers = array_slice($rows[1] ?? [], 0, count($expectedHeaders));

        if ($headers !== $expectedHeaders || count($rows[1] ?? []) > count($expectedHeaders)) {
            throw $this->invalid('The Options worksheet structure does not match the current official template.');
        }

        if ($rows !== [] && max(array_keys($rows)) > self::MAX_OPTION_ROWS + 1) {
            throw $this->invalid('The Options worksheet contains too many rows.');
        }
    }

    /**
     * Validate stable workbook identity and report whether the visible Row 2 sentinel remains intact.
     *
     * @param  array<int, string>  $sharedStrings
     * @param  array<string, mixed>  $type
     */
    private function validateInstructionsSheet(string $xml, array $sharedStrings, array $type): bool
    {
        $rows = $this->sheetRows($xml, $sharedStrings, 2, 100);

        if (($rows[1][0] ?? null) !== 'ECRATS Official Excel Account Template') {
            throw $this->invalid('The Instructions worksheet does not match the official ECRATS template.');
        }

        $metadata = [];

        foreach ($rows as $values) {
            if (count($values) > 2) {
                throw $this->invalid('The Instructions worksheet contains unexpected columns.');
            }

            if (filled($values[0] ?? null)) {
                $metadata[(string) $values[0]] = (string) ($values[1] ?? '');
            }
        }

        if (($metadata['Account Type Key'] ?? null) !== $type['key']) {
            throw $this->invalid('The uploaded workbook belongs to a different account type. Download the correct official template.');
        }

        // Removing this exact marker intentionally converts the realistic Row 2 example into normal validated data.
        return hash_equals(
            AccountTypeCatalog::EXAMPLE_MARKER,
            (string) ($metadata['Example Row Marker'] ?? ''),
        );
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @param  array<string, mixed>  $type
     * @return array<int, array{row: int, values: array<int, string>}>
     */
    private function validateAccountsSheet(string $xml, array $sharedStrings, array $type): array
    {
        $expectedHeaders = $type['template_headers'];
        $lastAllowedRow = self::MAX_ACCOUNT_ROWS + 2;
        $rows = $this->sheetRows($xml, $sharedStrings, count($expectedHeaders), $lastAllowedRow);
        $headerValues = $rows[1] ?? [];

        if ($headerValues !== $expectedHeaders) {
            throw $this->invalid('The Accounts worksheet headers must exactly match the selected role template, including order and spelling.');
        }

        if ($rows !== [] && max(array_keys($rows)) > $lastAllowedRow) {
            throw $this->invalid('The Accounts worksheet exceeds the maximum configured account-entry row.');
        }

        $accountRows = [];

        foreach ($rows as $rowNumber => $values) {
            if ($rowNumber === 1) {
                continue;
            }

            if (count($values) > count($expectedHeaders)) {
                throw $this->invalid("Excel row {$rowNumber} contains unexpected columns outside the approved template.");
            }

            $values = array_pad($values, count($expectedHeaders), '');

            if (collect($values)->contains(fn (string $value): bool => trim($value) !== '')) {
                $accountRows[] = ['row' => $rowNumber, 'values' => $values];
            }
        }

        if (count($accountRows) > self::MAX_ACCOUNT_ROWS + 1) {
            throw $this->invalid('A single Excel import may contain at most '.self::MAX_ACCOUNT_ROWS.' account rows plus the marked example row.');
        }

        return $accountRows;
    }

    /** @param array<int, string> $sharedStrings @return array<int, array<int, string>> */
    private function sheetRows(string $xml, array $sharedStrings, int $maxColumns, int $maxRow): array
    {
        $document = $this->xml($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];

        foreach ($xpath->query('//x:sheetData/x:row') ?: [] as $rowNode) {
            if (! $rowNode instanceof DOMElement) {
                continue;
            }

            $rowNumber = (int) $rowNode->getAttribute('r');

            if ($rowNumber < 1 || $rowNumber > $maxRow || isset($rows[$rowNumber])) {
                throw $this->invalid('The Excel workbook contains duplicate or invalid row references.');
            }

            $values = [];
            $highestIndex = -1;

            foreach ($xpath->query('./x:c', $rowNode) ?: [] as $cell) {
                if (! $cell instanceof DOMElement) {
                    continue;
                }

                $reference = $cell->getAttribute('r');
                $columnIndex = $this->columnIndex($reference, $rowNumber);

                // Enforce the approved width before constructing a sparse row array from attacker-controlled references.
                if ($columnIndex >= $maxColumns) {
                    throw $this->invalid("Excel row {$rowNumber} contains unexpected columns outside the approved template.");
                }

                if (array_key_exists($columnIndex, $values)) {
                    throw $this->invalid("Excel row {$rowNumber} contains a duplicate cell reference.");
                }

                $type = $cell->getAttribute('t');
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

            $rows[$rowNumber] = $highestIndex < 0
                ? []
                : array_map(fn (int $index): string => $values[$index] ?? '', range(0, $highestIndex));
        }

        ksort($rows);

        return $rows;
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
            if (count($strings) >= self::MAX_SHARED_STRINGS) {
                throw $this->invalid('The Excel workbook contains too many shared text values.');
            }

            $value = '';

            foreach ($xpath->query('.//x:t', $item) ?: [] as $textNode) {
                $value .= $textNode->textContent;
            }

            $strings[] = $value;
        }

        return $strings;
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
            throw $this->invalid('The Excel workbook is missing required content or contains an encrypted entry.');
        }

        return $contents;
    }

    private function columnIndex(string $reference, int $expectedRow): int
    {
        if (preg_match('/^([A-Z]+)([1-9][0-9]*)$/i', $reference, $matches) !== 1
            || (int) $matches[2] !== $expectedRow) {
            throw $this->invalid('The Excel workbook contains an invalid cell reference.');
        }

        $index = 0;

        foreach (str_split(strtoupper($matches[1])) as $letter) {
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

    private function inlineCell(string $reference, string $value, int $style = 0): string
    {
        $safeValue = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $styleAttribute = $style > 0 ? ' s="'.$style.'"' : '';

        return '<c r="'.$reference.'"'.$styleAttribute.' t="inlineStr"><is><t xml:space="preserve">'.$safeValue.'</t></is></c>';
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['accounts_file' => $message]);
    }
}
