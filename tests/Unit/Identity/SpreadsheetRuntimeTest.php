<?php

namespace Tests\Unit\Identity;

use App\Exceptions\SpreadsheetRuntimeUnavailable;
use App\Services\Identity\SafeSpreadsheet;
use App\Services\Identity\SpreadsheetRuntime;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;
use ZipArchive;

class SpreadsheetRuntimeTest extends TestCase
{
    public function test_capability_failures_are_reported_as_bounded_identifiers(): void
    {
        $runtime = $this->runtime(
            extensions: ['zip' => false],
            classes: [
                ZipArchive::class => false,
                Spreadsheet::class => true,
                XlsxReader::class => false,
                XlsxWriter::class => true,
            ],
            storageReady: false,
        );

        $this->assertSame([
            'ext-zip',
            'ZipArchive',
            'phpoffice/phpspreadsheet',
            'private-export-storage',
        ], $runtime->missingRequirements(requireWritableStorage: true));

        try {
            $runtime->assertAvailable(requireWritableStorage: true);
            $this->fail('The unavailable spreadsheet runtime did not fail closed.');
        } catch (SpreadsheetRuntimeUnavailable $exception) {
            $this->assertSame([
                'ext-zip',
                'ZipArchive',
                'phpoffice/phpspreadsheet',
                'private-export-storage',
            ], $exception->missingRequirements());
            $this->assertSame(
                'Required spreadsheet runtime capabilities are unavailable.',
                $exception->getMessage(),
            );
        }
    }

    public function test_exception_rejects_unbounded_diagnostics(): void
    {
        $exception = new SpreadsheetRuntimeUnavailable([
            'C:\\private\\php.ini token=internal-secret',
        ]);

        $this->assertSame(['spreadsheet-runtime'], $exception->missingRequirements());
        $this->assertStringNotContainsString('internal-secret', $exception->getMessage());
        $this->assertStringNotContainsString('php.ini', $exception->getMessage());
    }

    public function test_private_export_storage_probe_cleans_its_artifact(): void
    {
        Storage::fake('local');
        $runtime = $this->runtime(
            extensions: ['zip' => true],
            classes: [
                ZipArchive::class => true,
                Spreadsheet::class => true,
                XlsxReader::class => true,
                XlsxWriter::class => true,
            ],
            storageReady: null,
        );

        $this->assertSame([], $runtime->missingRequirements(requireWritableStorage: true));
        Storage::disk('local')->assertDirectoryEmpty('exports/account-templates');
    }

    public function test_template_generation_delegates_to_readiness_before_creating_files(): void
    {
        Storage::fake('local');
        $runtime = Mockery::mock(SpreadsheetRuntime::class);
        $runtime->shouldReceive('assertAvailable')
            ->once()
            ->with(true)
            ->andThrow(new SpreadsheetRuntimeUnavailable(['private-export-storage']));

        $spreadsheets = new SafeSpreadsheet($runtime);

        try {
            $spreadsheets->createTemplate([], []);
            $this->fail('Template generation continued after a storage-readiness failure.');
        } catch (SpreadsheetRuntimeUnavailable $exception) {
            $this->assertSame(['private-export-storage'], $exception->missingRequirements());
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_upload_read_converts_runtime_failure_to_a_safe_field_error(): void
    {
        $runtime = Mockery::mock(SpreadsheetRuntime::class);
        $runtime->shouldReceive('assertAvailable')
            ->once()
            ->withNoArgs()
            ->andThrow(new SpreadsheetRuntimeUnavailable(['ext-zip', 'ZipArchive']));

        $spreadsheets = new SafeSpreadsheet($runtime);

        try {
            $spreadsheets->read('this-file-must-never-be-opened.xlsx', []);
            $this->fail('Workbook parsing continued after a runtime failure.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'accounts_file' => [SpreadsheetRuntimeUnavailable::USER_MESSAGE],
            ], $exception->errors());
        }
    }

    /**
     * @param  array<string, bool>  $extensions
     * @param  array<class-string, bool>  $classes
     */
    private function runtime(array $extensions, array $classes, ?bool $storageReady): SpreadsheetRuntime
    {
        return new class($extensions, $classes, $storageReady) extends SpreadsheetRuntime
        {
            /**
             * @param  array<string, bool>  $extensions
             * @param  array<class-string, bool>  $classes
             */
            public function __construct(
                private readonly array $extensions,
                private readonly array $classes,
                private readonly ?bool $storageReady,
            ) {}

            protected function extensionLoaded(string $extension): bool
            {
                return $this->extensions[$extension] ?? false;
            }

            protected function classAvailable(string $class): bool
            {
                return $this->classes[$class] ?? false;
            }

            protected function privateExportStorageReady(): bool
            {
                return $this->storageReady ?? parent::privateExportStorageReady();
            }
        };
    }
}
