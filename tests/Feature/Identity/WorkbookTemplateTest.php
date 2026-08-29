<?php

namespace Tests\Feature\Identity;

use App\Enums\ProfileOptionField;
use App\Enums\UserRole;
use App\Exceptions\SpreadsheetRuntimeUnavailable;
use App\Models\ProfileOption;
use App\Models\User;
use App\Services\Identity\AccountTypeCatalog;
use App\Services\Identity\ProfileOptionCatalog;
use App\Services\Identity\SafeSpreadsheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class WorkbookTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->name() !== 'test_template_generation_failure_returns_a_safe_application_error'
            && $this->name() !== 'test_missing_workbook_runtime_returns_a_safe_error_without_xlsx_headers'
            && (! class_exists(ZipArchive::class) || ! class_exists(Spreadsheet::class))) {
            $this->markTestSkipped('The ZIP extension and installed PhpSpreadsheet package are required for XLSX round-trip coverage.');
        }
    }

    /** Lists the minimum package parts asserted before any test reader opens a workbook. */
    private const REQUIRED_ENTRIES = [
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

    /**
     * Verify every supported role template survives a complete Xlsx writer and reader round trip.
     */
    #[DataProvider('accountTypeProvider')]
    public function test_each_role_template_survives_a_verified_xlsx_round_trip(string $accountType): void
    {
        // Arrange a permitted REU Lead, role definition, and the current active database options.
        Storage::fake('local');
        $actor = User::factory()->create(['role' => UserRole::ResLead]);
        $type = app(AccountTypeCatalog::class)->authorized($actor, $accountType);
        $options = app(ProfileOptionCatalog::class)->grouped();

        // Act by generating the same private workbook that the HTTP controller would download.
        $path = app(SafeSpreadsheet::class)->createTemplate($type, $options);

        // Assert both the generated and writer-resaved workbooks satisfy the complete package contract.
        $this->assertVerifiedRoundTrip($path, $type, $options);
    }

    /** @return array<string, array{string}> */
    public static function accountTypeProvider(): array
    {
        // Keep provider labels aligned with the user-facing account types for useful failure output.
        return [
            'Student Researcher' => ['student_researcher'],
            'Faculty Researcher' => ['faculty_researcher'],
            'Research Adviser' => ['adviser'],
        ];
    }

    /**
     * Verify an empty Program group omits its range and adds instructions instead of a broken dropdown.
     */
    public function test_empty_program_options_remain_valid_after_round_trip(): void
    {
        // Arrange explicitly empty active option groups regardless of future seed-data additions.
        Storage::fake('local');
        $actor = User::factory()->create(['role' => UserRole::ResLead]);
        ProfileOption::query()
            ->where('field', ProfileOptionField::Program->value)
            ->update(['is_active' => false]);
        $type = app(AccountTypeCatalog::class)->authorized($actor, 'student_researcher');
        $options = app(ProfileOptionCatalog::class)->grouped();

        // Act by generating a student template with both optional groups empty.
        $path = app(SafeSpreadsheet::class)->createTemplate($type, $options);

        // Assert both reader passes omit invalid names and retain clear Instructions warnings.
        $this->assertVerifiedRoundTrip($path, $type, $options, function (Spreadsheet $workbook): void {
            $this->assertNull($workbook->getNamedRange('EcratsDepartmentOptions'));
            $this->assertNull($workbook->getNamedRange('EcratsProgramOptions'));
            $instructions = $this->worksheetText($workbook, 'Instructions');
            $this->assertStringContainsString('No active Program options are configured.', $instructions);

            // Assert no surviving validation formula references either intentionally omitted name.
            foreach ($workbook->getSheetByName('Accounts')?->getDataValidationCollection() ?? [] as $validation) {
                $this->assertNotContains(ltrim($validation->getFormula1(), '='), [
                    'EcratsProgramOptions',
                ]);
            }
        });
    }

    /**
     * Verify current additions appear, deactivated values disappear, and long Institute names remain intact.
     */
    public function test_active_deactivated_and_long_dropdown_values_survive_round_trip(): void
    {
        // Arrange one new Institute option, one deactivated Program, and a long but valid Institute label.
        Storage::fake('local');
        $actor = User::factory()->create(['role' => UserRole::ResLead]);
        $catalog = app(ProfileOptionCatalog::class);
        $newInstitute = 'Institute for Applied Research and Community Ethics';
        $deactivatedProgram = 'Legacy Research Program';
        $longInstitute = 'Kolehiyo ng Lungsod ng Dasmarinas Institute for Interdisciplinary Research, Community Engagement, and International Collaboration';
        $catalog->create($actor, ProfileOptionField::Institute, $newInstitute, 'IARCE');
        $inactive = $catalog->create($actor, ProfileOptionField::Program, $deactivatedProgram);
        $catalog->setActive($actor, $inactive, false);
        $catalog->create($actor, ProfileOptionField::Institute, $longInstitute, 'KIIR');
        $type = app(AccountTypeCatalog::class)->authorized($actor, 'student_researcher');
        $options = $catalog->grouped();

        // Act by generating a workbook from the updated active-option snapshot.
        $path = app(SafeSpreadsheet::class)->createTemplate($type, $options);

        // Assert both reader passes contain active values exactly and exclude the deactivated value.
        $this->assertVerifiedRoundTrip($path, $type, $options, function (Spreadsheet $workbook) use ($newInstitute, $deactivatedProgram, $longInstitute): void {
            $optionsText = $this->worksheetText($workbook, 'Options');
            $this->assertStringContainsString($newInstitute, $optionsText);
            $this->assertStringContainsString($longInstitute, $optionsText);
            $this->assertStringNotContainsString($deactivatedProgram, $optionsText);
        });
    }

    /**
     * Verify text-formatted institutional identifiers retain leading zeros across two Xlsx writer saves.
     */
    public function test_leading_zero_identifier_survives_xlsx_round_trip(): void
    {
        // Arrange the official student template and three private paths used only by this test.
        Storage::fake('local');
        $actor = User::factory()->create(['role' => UserRole::ResLead]);
        $type = app(AccountTypeCatalog::class)->authorized($actor, 'student_researcher');
        $options = app(ProfileOptionCatalog::class)->grouped();
        $generatedPath = app(SafeSpreadsheet::class)->createTemplate($type, $options);
        Storage::disk('local')->makeDirectory('tests/workbook-round-trips');
        $firstPath = Storage::disk('local')->path('tests/workbook-round-trips/leading-zero-first.xlsx');
        $secondPath = Storage::disk('local')->path('tests/workbook-round-trips/leading-zero-second.xlsx');

        try {
            // Act by loading the verified template, writing an explicit text identifier, and saving with the Xlsx writer.
            $generated = $this->loadAndAssertPackage($generatedPath);
            $generated->getSheetByName('Accounts')?->setCellValueExplicit('F3', '0000601', DataType::TYPE_STRING);
            (new XlsxWriter($generated))->save($firstPath);
            $generated->disconnectWorksheets();

            // Assert the first saved workbook remains valid and exposes the identifier as text.
            $first = $this->loadAndAssertPackage($firstPath);
            $this->assertSame('0000601', $first->getSheetByName('Accounts')?->getCell('F3')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $first->getSheetByName('Accounts')?->getCell('F3')->getDataType());

            // Act again through the supported writer to exercise the required second-file round trip.
            (new XlsxWriter($first))->save($secondPath);
            $first->disconnectWorksheets();

            // Assert the second reader sees the same text value and the service accepts its workbook contract.
            $second = $this->loadAndAssertPackage($secondPath);
            $this->assertSame('0000601', $second->getSheetByName('Accounts')?->getCell('F3')->getValue());
            app(SafeSpreadsheet::class)->verifyGeneratedTemplate($secondPath, $type, $options);
            $second->disconnectWorksheets();
        } finally {
            // Clean all private artifacts even when an assertion or writer operation fails.
            $this->deleteTestFiles([$generatedPath, $firstPath, $secondPath]);
        }
    }

    /**
     * Verify the successful HTTP response is an XLSX attachment whose actual body starts with a ZIP signature.
     */
    public function test_template_download_returns_only_a_valid_xlsx_binary(): void
    {
        // Arrange an authorized actor, role-specific URL, and stale artifact that fallback cleanup must remove.
        Storage::fake('local');
        $actor = User::factory()->create(['role' => UserRole::ResLead]);
        $url = route('res.users.import.template', ['account_type' => 'student_researcher']);
        Storage::disk('local')->put('exports/account-templates/stale-download.xlsx', 'stale');
        $stalePath = Storage::disk('local')->path('exports/account-templates/stale-download.xlsx');
        touch($stalePath, now()->subHours(2)->timestamp);

        // Act by requesting the same binary response used by the browser.
        $response = $this->actingAs($actor)->get($url);

        // Assert status and attachment headers before sending the response body into a local output buffer.
        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type'),
        );
        $this->assertMatchesRegularExpression(
            '/attachment;\s*filename=ecrats-student_researcher-template\.xlsx/i',
            (string) $response->headers->get('content-disposition'),
        );

        // Capture the delivered binary so the assertions cover response bytes rather than only a temporary path.
        ob_start();
        $response->baseResponse->sendContent();
        $body = (string) ob_get_clean();

        // Assert the body is a non-empty ZIP package and not an HTML or JSON error payload with an XLSX name.
        $this->assertNotSame('', $body);
        $this->assertStringStartsWith("PK\x03\x04", $body);
        $this->assertFalse(str_starts_with(ltrim($body), '<'));
        $this->assertNull(json_decode($body, true));
        $this->assertFileDoesNotExist($stalePath);
    }

    /**
     * Verify generator exceptions return an ordinary neutral redirect without spreadsheet headers or internal details.
     */
    public function test_template_generation_failure_returns_a_safe_application_error(): void
    {
        // Arrange an authorized actor and a generator failure containing diagnostics that must never reach the response.
        Storage::fake('local');
        $actor = User::factory()->create(['role' => UserRole::ResLead]);
        $returnUrl = route('res.users.import.form', ['account_type' => 'student_researcher']);
        $this->mock(SafeSpreadsheet::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createTemplate')
                ->once()
                ->andThrow(new RuntimeException('C:\\private\\template.xlsx token=internal-secret'));
        });

        // Act by requesting the template from the normal import page context.
        $response = $this->actingAs($actor)
            ->from($returnUrl)
            ->get(route('res.users.import.template', ['account_type' => 'student_researcher']));

        // Assert the failure remains a normal application redirect with a neutral session error.
        $response->assertRedirect($returnUrl)
            ->assertSessionHasErrors([
                'template' => 'The Excel template could not be generated. Please try again.',
            ]);
        $this->assertNull($response->headers->get('content-disposition'));
        $this->assertNotSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type'),
        );
        $this->assertStringNotContainsString('internal-secret', (string) $response->getContent());
        $this->assertStringNotContainsString('private\\template', (string) $response->getContent());
    }

    public function test_missing_workbook_runtime_returns_a_safe_error_without_xlsx_headers(): void
    {
        // Arrange a deterministic capability failure independent of the PHP extensions on the test host.
        Storage::fake('local');
        $actor = User::factory()->create(['role' => UserRole::ResLead]);
        Log::spy();
        $this->mock(SafeSpreadsheet::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createTemplate')
                ->once()
                ->andThrow(new SpreadsheetRuntimeUnavailable([
                    'ext-zip',
                    'ZipArchive',
                    'C:\\private\\php.ini token=internal-secret',
                ]));
        });

        $response = $this->actingAs($actor)
            ->from(route('res.users.import.form', ['account_type' => 'student_researcher']))
            ->get(route('res.users.import.template', ['account_type' => 'student_researcher']));

        $response->assertRedirect()->assertSessionHasErrors([
            'template' => SpreadsheetRuntimeUnavailable::USER_MESSAGE,
        ]);
        $this->assertNull($response->headers->get('content-disposition'));
        $this->assertNotSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type'),
        );
        $this->assertStringNotContainsString('internal-secret', (string) $response->getContent());
        $this->assertSame([], Storage::disk('local')->allFiles());

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Account template runtime unavailable.', \Mockery::on(
                fn (array $context): bool => $context === [
                    'actor_user_id' => $actor->id,
                    'account_type' => 'student_researcher',
                    'missing_requirements' => ['ext-zip', 'ZipArchive'],
                ],
            ));
    }

    /**
     * Assert a generated workbook and its writer-resaved copy satisfy the same structural and semantic contract.
     *
     * @param  array<string, mixed>  $type
     * @param  array<string, array<int, string>>  $options
     */
    private function assertVerifiedRoundTrip(string $path, array $type, array $options, ?callable $extraAssertions = null): void
    {
        // Arrange a unique private destination for the required second Xlsx file.
        Storage::disk('local')->makeDirectory('tests/workbook-round-trips');
        $roundTripPath = Storage::disk('local')->path('tests/workbook-round-trips/'.uniqid('round-trip-', true).'.xlsx');

        try {
            // Assert the generated package, worksheet set, names, and dropdowns before resaving it.
            $generated = $this->loadAndAssertPackage($path);
            app(SafeSpreadsheet::class)->verifyGeneratedTemplate($path, $type, $options);
            $this->assertNamedRangesAndValidations($generated);
            $extraAssertions?->__invoke($generated);

            // Act through PhpSpreadsheet's supported Xlsx writer to create an independent second package.
            $writer = new XlsxWriter($generated);
            $writer->setPreCalculateFormulas(false);
            $writer->save($roundTripPath);
            $generated->disconnectWorksheets();

            // Assert the second package can be reopened and retains the same ranges, dropdowns, and scenario data.
            $reopened = $this->loadAndAssertPackage($roundTripPath);
            app(SafeSpreadsheet::class)->verifyGeneratedTemplate($roundTripPath, $type, $options);
            $this->assertNamedRangesAndValidations($reopened);
            $extraAssertions?->__invoke($reopened);
            $reopened->disconnectWorksheets();
        } finally {
            // Delete both private test files regardless of writer, reader, or assertion outcomes.
            $this->deleteTestFiles([$path, $roundTripPath]);
        }
    }

    /**
     * Assert file, ZIP, Open XML, and worksheet prerequisites before returning a trusted-reader workbook.
     */
    private function loadAndAssertPackage(string $path): Spreadsheet
    {
        // Assert the private file exists, is non-empty, and starts with the standard ZIP local-file signature.
        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));
        $this->assertSame("PK\x03\x04", file_get_contents($path, false, null, 0, 4));

        // Assert every required Open XML part exists before loading workbook relationships.
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        foreach (self::REQUIRED_ENTRIES as $entry) {
            $this->assertNotFalse($zip->locateName($entry, ZipArchive::FL_NOCASE), "Missing XLSX entry: {$entry}");
        }

        $zip->close();

        // Act through PhpSpreadsheet's Xlsx reader and assert the exact visible/hidden worksheet contract.
        $reader = new XlsxReader;
        $reader->setReadDataOnly(false);
        $workbook = $reader->load($path);
        $this->assertSame(['Accounts', 'Options', 'Instructions'], $workbook->getSheetNames());
        $this->assertSame(Worksheet::SHEETSTATE_VISIBLE, $workbook->getSheetByName('Accounts')?->getSheetState());
        $this->assertSame(Worksheet::SHEETSTATE_HIDDEN, $workbook->getSheetByName('Options')?->getSheetState());
        $this->assertSame(Worksheet::SHEETSTATE_VISIBLE, $workbook->getSheetByName('Instructions')?->getSheetState());

        return $workbook;
    }

    /**
     * Assert every workbook name is unique and every list validation references one valid forward range.
     */
    private function assertNamedRangesAndValidations(Spreadsheet $workbook): void
    {
        // Collect names case-insensitively while checking worksheet scope, quoting, and non-reversed ranges.
        $rangeNames = [];

        foreach ($workbook->getNamedRanges() as $range) {
            $this->assertSame('Options', $range->getWorksheet()?->getTitle());
            $this->assertMatchesRegularExpression(
                "/^'Options'!\\$([A-Z]+)\\$2:\\$([A-Z]+)\\$([2-9]|[1-9][0-9]+)$/",
                $range->getRange(),
            );
            preg_match(
                "/^'Options'!\\$([A-Z]+)\\$2:\\$([A-Z]+)\\$([2-9]|[1-9][0-9]+)$/",
                $range->getRange(),
                $matches,
            );
            $this->assertSame($matches[1], $matches[2]);
            $rangeNames[mb_strtolower($range->getName())] = $range->getName();
        }

        $this->assertNotEmpty($rangeNames);
        $this->assertCount(count($workbook->getNamedRanges()), $rangeNames);

        // Assert each Accounts validation is a list targeting account rows and resolving to one verified workbook name.
        $validations = $workbook->getSheetByName('Accounts')?->getDataValidationCollection() ?? [];
        $this->assertNotEmpty($validations);

        foreach ($validations as $validation) {
            $this->assertSame(DataValidation::TYPE_LIST, $validation->getType());
            $this->assertTrue($validation->getShowErrorMessage());
            $this->assertMatchesRegularExpression('/^([A-Z]+)3:([A-Z]+)'.(SafeSpreadsheet::MAX_ACCOUNT_ROWS + 2).'$/', (string) $validation->getSqref());
            preg_match('/^([A-Z]+)3:([A-Z]+)'.(SafeSpreadsheet::MAX_ACCOUNT_ROWS + 2).'$/', (string) $validation->getSqref(), $validationMatches);
            $this->assertSame($validationMatches[1], $validationMatches[2]);
            $this->assertArrayHasKey(mb_strtolower(ltrim($validation->getFormula1(), '=')), $rangeNames);
        }
    }

    /**
     * Flatten one worksheet into searchable text without relying on shared-string XML representation details.
     */
    private function worksheetText(Spreadsheet $workbook, string $sheetName): string
    {
        // Join only reader-decoded cell values so assertions remain stable across valid writer implementations.
        return collect($workbook->getSheetByName($sheetName)?->toArray() ?? [])
            ->flatten()
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->implode("\n");
    }

    /** @param array<int, string> $paths */
    private function deleteTestFiles(array $paths): void
    {
        // Remove only explicit files created inside Storage's private test root; never recurse or accept directory paths.
        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
