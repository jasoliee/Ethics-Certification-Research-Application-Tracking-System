<?php

namespace Tests\Unit;

use Tests\TestCase;

require_once dirname(__DIR__, 2).'/scripts/preflight.php';

class RuntimePreflightScriptTest extends TestCase
{
    public function test_preflight_passes_when_runtime_and_storage_are_ready(): void
    {
        $result = \EcratsRuntimePreflight::evaluate($this->state());

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString(
            'Runtime preflight passed',
            \EcratsRuntimePreflight::render($result),
        );
    }

    public function test_preflight_failure_identifies_ini_zip_and_storage_remediation(): void
    {
        $state = $this->state();
        $state['php_version'] = '8.2.29';
        $state['integer_size'] = 4;
        $state['ini_file'] = 'C:\\laragon\\bin\\php\\php.ini';
        $state['extensions']['zip'] = false;
        $state['zip_archive'] = false;
        $state['storage_ready'] = false;

        $result = \EcratsRuntimePreflight::evaluate($state);
        $output = \EcratsRuntimePreflight::render($result);

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString('PHP 8.3.0 or newer is required', $output);
        $this->assertStringContainsString('64-bit PHP runtime', $output);
        $this->assertStringContainsString('Missing PHP extension: ext-zip', $output);
        $this->assertStringContainsString('ZipArchive class is unavailable', $output);
        $this->assertStringContainsString('Private export storage is not writable', $output);
        $this->assertStringContainsString('Active php.ini: C:\\laragon\\bin\\php\\php.ini', $output);
        $this->assertStringContainsString('uncomment extension=zip', $output);
        $this->assertStringContainsString('restart Laragon and every PHP/artisan process', $output);
        $this->assertStringContainsString('composer run verify', $output);
    }

    /**
     * @return array{
     *     php_version: string,
     *     integer_size: int,
     *     php_binary: string,
     *     php_sapi: string,
     *     ini_file: string,
     *     extensions: array<string, bool>,
     *     zip_archive: bool,
     *     storage_ready: bool,
     *     storage_path: string
     * }
     */
    private function state(): array
    {
        return [
            'php_version' => '8.3.30',
            'integer_size' => 8,
            'php_binary' => 'C:\\php\\php.exe',
            'php_sapi' => 'cli',
            'ini_file' => 'C:\\php\\php.ini',
            'extensions' => array_fill_keys([
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
            ], true),
            'zip_archive' => true,
            'storage_ready' => true,
            'storage_path' => 'C:\\ecrats\\storage\\app\\private\\exports\\account-templates',
        ];
    }
}
