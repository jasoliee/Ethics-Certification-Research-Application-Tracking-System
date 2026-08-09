<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Identifies a server-readiness failure without exposing filesystem or library diagnostics.
 */
class SpreadsheetRuntimeUnavailable extends RuntimeException
{
    public const USER_MESSAGE = 'Excel workbook support is not configured on this server. Please contact the system administrator and ask them to run the ECRATS runtime preflight.';

    /** @var list<string> */
    private array $missingRequirements;

    /**
     * @param  list<string>  $missingRequirements
     */
    public function __construct(array $missingRequirements)
    {
        $allowedRequirements = [
            'ext-zip',
            'ZipArchive',
            'phpoffice/phpspreadsheet',
            'private-export-storage',
            'spreadsheet-runtime',
        ];

        $this->missingRequirements = array_values(array_unique(array_intersect(
            $allowedRequirements,
            $missingRequirements,
        )));

        if ($this->missingRequirements === []) {
            $this->missingRequirements = ['spreadsheet-runtime'];
        }

        parent::__construct('Required spreadsheet runtime capabilities are unavailable.');
    }

    /** @return list<string> */
    public function missingRequirements(): array
    {
        return $this->missingRequirements;
    }
}
