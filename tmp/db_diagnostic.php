<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$variables = collect(DB::select("SHOW VARIABLES WHERE Variable_name IN ('log_bin', 'log_bin_basename', 'binlog_format', 'datadir')"))
    ->map(fn (object $row): array => (array) $row)
    ->all();

try {
    $binaryLogs = collect(DB::select('SHOW BINARY LOGS'))
        ->map(fn (object $row): array => (array) $row)
        ->all();
} catch (Throwable $exception) {
    $binaryLogs = ['error' => $exception->getMessage()];
}

var_export(['variables' => $variables, 'binary_logs' => $binaryLogs]);
