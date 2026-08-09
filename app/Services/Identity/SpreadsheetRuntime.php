<?php

namespace App\Services\Identity;

use App\Exceptions\SpreadsheetRuntimeUnavailable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Throwable;
use ZipArchive;

/**
 * Owns the executable and private-storage requirements shared by XLSX generation and parsing.
 */
class SpreadsheetRuntime
{
    private const EXPORT_DIRECTORY = 'exports/account-templates';

    /**
     * @return list<string>
     */
    public function missingRequirements(bool $requireWritableStorage = false): array
    {
        $missing = [];

        if (! $this->extensionLoaded('zip')) {
            $missing[] = 'ext-zip';
        }

        if (! $this->classAvailable(ZipArchive::class)) {
            $missing[] = 'ZipArchive';
        }

        if (! $this->classAvailable(Spreadsheet::class)
            || ! $this->classAvailable(XlsxReader::class)
            || ! $this->classAvailable(XlsxWriter::class)) {
            $missing[] = 'phpoffice/phpspreadsheet';
        }

        if ($requireWritableStorage && ! $this->privateExportStorageReady()) {
            $missing[] = 'private-export-storage';
        }

        return array_values(array_unique($missing));
    }

    /**
     * Fail before any XLSX is opened or created, with only bounded capability identifiers attached.
     */
    public function assertAvailable(bool $requireWritableStorage = false): void
    {
        $missing = $this->missingRequirements($requireWritableStorage);

        if ($missing !== []) {
            throw new SpreadsheetRuntimeUnavailable($missing);
        }
    }

    protected function extensionLoaded(string $extension): bool
    {
        return extension_loaded($extension);
    }

    protected function classAvailable(string $class): bool
    {
        return class_exists($class);
    }

    /**
     * Prove the same private directory used for downloads can create, persist, and delete a bounded probe.
     */
    protected function privateExportStorageReady(): bool
    {
        $disk = Storage::disk('local');
        $probe = self::EXPORT_DIRECTORY.'/.runtime-probe-'.Str::uuid();

        try {
            if (! $disk->directoryExists(self::EXPORT_DIRECTORY)
                && ! $disk->makeDirectory(self::EXPORT_DIRECTORY)) {
                return false;
            }

            if (! $disk->put($probe, 'ecrats-spreadsheet-runtime')) {
                return false;
            }

            if (! $disk->exists($probe) || (int) $disk->size($probe) === 0) {
                return false;
            }

            return $disk->delete($probe) && ! $disk->exists($probe);
        } catch (Throwable) {
            return false;
        } finally {
            try {
                if ($disk->exists($probe)) {
                    $disk->delete($probe);
                }
            } catch (Throwable) {
                // A failed cleanup keeps the storage capability in the unavailable state.
            }
        }
    }
}
