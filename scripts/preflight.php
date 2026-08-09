<?php

/**
 * Vendor-independent ECRATS runtime preflight.
 *
 * This file intentionally does not load Composer or Laravel so it can diagnose a fresh PHP
 * installation before `composer install` is allowed to proceed.
 */
final class EcratsRuntimePreflight
{
    private const MINIMUM_PHP_VERSION = '8.3.0';

    /** @var list<string> */
    private const REQUIRED_EXTENSIONS = [
        'dom',
        'fileinfo',
        'filter',
        'gd',
        'hash',
        'iconv',
        'json',
        'libxml',
        'openssl',
        'pcre',
        'session',
        'simplexml',
        'tokenizer',
        'xml',
        'xmlreader',
        'xmlwriter',
        'zip',
        'zlib',
    ];

    /**
     * @param  array{
     *     php_version: string,
     *     integer_size: int,
     *     php_binary: string,
     *     php_sapi: string,
     *     ini_file: string|null,
     *     extensions: array<string, bool>,
     *     zip_archive: bool,
     *     storage_ready: bool,
     *     storage_path: string
     * }  $state
     * @return array{exit_code: 0|1, lines: list<string>}
     */
    public static function evaluate(array $state): array
    {
        $problems = [];

        if (version_compare($state['php_version'], self::MINIMUM_PHP_VERSION, '<')) {
            $problems[] = 'PHP '.self::MINIMUM_PHP_VERSION.' or newer is required; detected '.$state['php_version'].'.';
        }

        if ($state['integer_size'] < 8) {
            $problems[] = 'A 64-bit PHP runtime is required for bounded XLSX processing.';
        }

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            if (! ($state['extensions'][$extension] ?? false)) {
                $problems[] = 'Missing PHP extension: ext-'.$extension.'.';
            }
        }

        if (! $state['zip_archive']) {
            $problems[] = 'The ZipArchive class is unavailable; enable ext-zip for this PHP runtime.';
        }

        if (! $state['storage_ready']) {
            $problems[] = 'Private export storage is not writable and removable: '.$state['storage_path'].'.';
        }

        $iniFile = is_string($state['ini_file']) && $state['ini_file'] !== ''
            ? $state['ini_file']
            : 'No php.ini is loaded';
        $runtime = [
            'PHP binary: '.$state['php_binary'],
            'PHP SAPI: '.$state['php_sapi'],
            'Active php.ini: '.$iniFile,
        ];

        if ($problems === []) {
            return [
                'exit_code' => 0,
                'lines' => [
                    '[ECRATS] Runtime preflight passed.',
                    ...$runtime,
                    'Required PHP capabilities and private XLSX export storage are ready.',
                ],
            ];
        }

        return [
            'exit_code' => 1,
            'lines' => [
                '[ECRATS] Runtime preflight failed.',
                ...$runtime,
                ...array_map(static fn (string $problem): string => '- '.$problem, $problems),
                'For Laragon on Windows, enable each missing extension in the active php.ini (for ZIP, uncomment extension=zip), then restart Laragon and every PHP/artisan process.',
                'Rerun `composer run verify` after correcting the runtime.',
            ],
        ];
    }

    /** @return array{exit_code: 0|1, lines: list<string>} */
    public static function inspect(string $projectRoot): array
    {
        $extensionState = [];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $extensionState[$extension] = extension_loaded($extension);
        }

        $storagePath = $projectRoot.DIRECTORY_SEPARATOR.'storage'
            .DIRECTORY_SEPARATOR.'app'
            .DIRECTORY_SEPARATOR.'private'
            .DIRECTORY_SEPARATOR.'exports'
            .DIRECTORY_SEPARATOR.'account-templates';

        return self::evaluate([
            'php_version' => PHP_VERSION,
            'integer_size' => PHP_INT_SIZE,
            'php_binary' => PHP_BINARY,
            'php_sapi' => PHP_SAPI,
            'ini_file' => php_ini_loaded_file() ?: null,
            'extensions' => $extensionState,
            'zip_archive' => class_exists('ZipArchive', false),
            'storage_ready' => self::probeStorage($storagePath),
            'storage_path' => $storagePath,
        ]);
    }

    public static function render(array $result): string
    {
        return implode(PHP_EOL, $result['lines']).PHP_EOL;
    }

    private static function probeStorage(string $directory): bool
    {
        $probe = null;

        try {
            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return false;
            }

            $probe = $directory.DIRECTORY_SEPARATOR.'.runtime-probe-'.bin2hex(random_bytes(12));
            $written = @file_put_contents($probe, 'ecrats-runtime-preflight', LOCK_EX);

            if ($written === false || ! is_file($probe) || filesize($probe) === 0) {
                return false;
            }

            return @unlink($probe) && ! file_exists($probe);
        } catch (Throwable) {
            return false;
        } finally {
            if (is_string($probe) && file_exists($probe)) {
                @unlink($probe);
            }
        }
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $result = EcratsRuntimePreflight::inspect(dirname(__DIR__));
    $stream = $result['exit_code'] === 0 ? STDOUT : STDERR;
    fwrite($stream, EcratsRuntimePreflight::render($result));

    exit($result['exit_code']);
}
