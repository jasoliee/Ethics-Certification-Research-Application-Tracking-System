<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const MYSQL_BIN = 'D:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe';
const MYSQLDUMP_BIN = 'D:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe';
const MYSQLBINLOG_BIN = 'D:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqlbinlog.exe';
const LIVE_DATABASE = 'ecrats_db';
const RECOVERY_DATABASE = 'ecrats_recovery_20260823';

/** @return array{host:string,port:string,database:string,username:string,password:string} */
function mysqlConfig(): array
{
    $config = config('database.connections.mysql');

    return [
        'host' => (string) ($config['host'] ?? '127.0.0.1'),
        'port' => (string) ($config['port'] ?? '3306'),
        'database' => (string) ($config['database'] ?? ''),
        'username' => (string) ($config['username'] ?? ''),
        'password' => (string) ($config['password'] ?? ''),
    ];
}

function assertRecoveryDatabase(string $database): void
{
    if (! in_array($database, [LIVE_DATABASE, RECOVERY_DATABASE], true)) {
        throw new RuntimeException('Refusing an unexpected database target.');
    }
}

function adminPdo(): PDO
{
    $config = mysqlConfig();

    return new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['host'], $config['port']),
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
}

function databasePdo(string $database): PDO
{
    assertRecoveryDatabase($database);
    $config = mysqlConfig();

    return new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $database),
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
}

/** @return array<string, string> */
function mysqlEnvironment(): array
{
    return ['MYSQL_PWD' => mysqlConfig()['password']];
}

/** @return list<string> */
function mysqlArguments(string $database): array
{
    assertRecoveryDatabase($database);
    $config = mysqlConfig();

    return [
        MYSQL_BIN,
        '--protocol=TCP',
        '--host='.$config['host'],
        '--port='.$config['port'],
        '--user='.$config['username'],
        '--default-character-set=utf8mb4',
        '--binary-mode',
        $database,
    ];
}

function createDatabase(string $database): void
{
    assertRecoveryDatabase($database);
    $pdo = adminPdo();
    $exists = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ".$pdo->quote($database))->fetchColumn();
    if ($exists !== false) {
        throw new RuntimeException("Database {$database} already exists; refusing to overwrite it.");
    }

    $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

function replayLog(string $database, string $file, ?int $startPosition = null, ?int $stopPosition = null): void
{
    assertRecoveryDatabase($database);
    $arguments = [MYSQLBINLOG_BIN, '--rewrite-db='.LIVE_DATABASE.'->'.$database];
    if ($startPosition !== null) {
        $arguments[] = '--start-position='.$startPosition;
    }
    if ($stopPosition !== null) {
        $arguments[] = '--stop-position='.$stopPosition;
    }
    $arguments[] = $file;

    $input = new InputStream;
    $mysqlError = '';
    $mysql = new Process(mysqlArguments($database), null, mysqlEnvironment(), null, 900);
    $mysql->setInput($input);
    $mysql->start(function (string $type, string $buffer) use (&$mysqlError): void {
        if ($type === Process::ERR) {
            $mysqlError .= $buffer;
        }
    });

    $binlogError = '';
    $binlog = new Process($arguments, null, null, null, 900);
    $binlog->run(function (string $type, string $buffer) use ($input, &$binlogError): void {
        if ($type === Process::OUT) {
            $input->write($buffer);
        } else {
            $binlogError .= $buffer;
        }
    });
    $input->close();
    $mysql->wait();

    if (! $binlog->isSuccessful() || ! $mysql->isSuccessful()) {
        throw new RuntimeException(trim("mysqlbinlog: {$binlogError}\nmysql: {$mysqlError}"));
    }
}

function dumpDatabase(string $database, string $target): void
{
    assertRecoveryDatabase($database);
    $config = mysqlConfig();
    $process = new Process([
        MYSQLDUMP_BIN,
        '--protocol=TCP',
        '--host='.$config['host'],
        '--port='.$config['port'],
        '--user='.$config['username'],
        '--default-character-set=utf8mb4',
        '--single-transaction',
        '--routines',
        '--triggers',
        '--events',
        '--hex-blob',
        '--no-tablespaces',
        '--set-gtid-purged=OFF',
        $database,
    ], null, mysqlEnvironment(), null, 900);

    $handle = fopen($target, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Unable to create dump target.');
    }
    $error = '';
    $process->run(function (string $type, string $buffer) use ($handle, &$error): void {
        if ($type === Process::OUT) {
            fwrite($handle, $buffer);
        } else {
            $error .= $buffer;
        }
    });
    fclose($handle);

    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($error));
    }
}

function importDump(string $database, string $source): void
{
    assertRecoveryDatabase($database);
    $handle = fopen($source, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Unable to open dump source.');
    }

    $error = '';
    $process = new Process(mysqlArguments($database), null, mysqlEnvironment(), $handle, 900);
    $process->run(function (string $type, string $buffer) use (&$error): void {
        if ($type === Process::ERR) {
            $error .= $buffer;
        }
    });
    fclose($handle);

    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($error));
    }
}

/** @return array<string, int> */
function tableCounts(string $database): array
{
    $pdo = databasePdo($database);
    $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);
    $counts = [];
    foreach ($tables as $table) {
        $counts[(string) $table] = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }
    ksort($counts);

    return $counts;
}

/** @return array<string, mixed> */
function validateDatabase(string $database): array
{
    assertRecoveryDatabase($database);
    $pdo = databasePdo($database);
    $counts = tableCounts($database);
    $tableNames = array_keys($counts);
    $checkResults = [];
    foreach ($tableNames as $table) {
        $result = $pdo->query("CHECK TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        $checkResults[$table] = (string) ($result['Msg_text'] ?? 'unknown');
    }

    $migrations = $pdo->query('SELECT migration, batch FROM migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $roleCounts = $pdo->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role ORDER BY role')->fetchAll(PDO::FETCH_ASSOC);
    $applicationStatusCounts = $pdo->query('SELECT application_status, COUNT(*) AS total FROM research_applications GROUP BY application_status ORDER BY application_status')->fetchAll(PDO::FETCH_ASSOC);
    $foreignKeys = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ".$pdo->quote($database))->fetchColumn();

    $pathQueries = [
        'application_documents.file' => ['application_documents', 'stored_file_path', 'file_sha256'],
        'certificate_backgrounds.file' => ['certificate_backgrounds', 'stored_file_path', 'sha256'],
        'review_form_artifacts.file' => ['review_form_artifacts', 'stored_file_path', 'sha256'],
        'certificate_versions.file' => ['certificate_versions', 'stored_file_path', 'sha256'],
        'certificate_versions.qr' => ['certificate_versions', 'qr_code_path', 'qr_code_sha256'],
        'users.certificate_signature' => ['users', 'certificate_signature_path', 'certificate_signature_sha256'],
        'users.worksheet_signature' => ['users', 'worksheet_signature_path', 'worksheet_signature_sha256'],
    ];
    $storage = Storage::disk('local');
    $pathChecks = [];
    foreach ($pathQueries as $label => [$table, $pathColumn, $hashColumn]) {
        $rows = $pdo->query("SELECT `{$pathColumn}` AS path, `{$hashColumn}` AS expected_hash FROM `{$table}` WHERE `{$pathColumn}` IS NOT NULL AND `{$pathColumn}` <> ''")->fetchAll(PDO::FETCH_ASSOC);
        $missing = 0;
        $hashMismatch = 0;
        foreach ($rows as $row) {
            $path = (string) $row['path'];
            if (! $storage->exists($path)) {
                $missing++;
                continue;
            }
            $expectedHash = trim((string) ($row['expected_hash'] ?? ''));
            if ($expectedHash !== '') {
                $actualHash = hash_file('sha256', $storage->path($path));
                if (! is_string($actualHash) || ! hash_equals($expectedHash, $actualHash)) {
                    $hashMismatch++;
                }
            }
        }
        $pathChecks[$label] = ['records' => count($rows), 'missing' => $missing, 'hash_mismatch' => $hashMismatch];
    }

    $integrityQueries = [
        'application_without_applicant' => 'SELECT COUNT(*) FROM research_applications a LEFT JOIN users u ON u.id = a.applicant_user_id WHERE u.id IS NULL',
        'document_without_application' => 'SELECT COUNT(*) FROM application_documents d LEFT JOIN research_applications a ON a.id = d.research_application_id WHERE a.id IS NULL',
        'assignment_without_reviewer' => 'SELECT COUNT(*) FROM reviewer_assignments r LEFT JOIN users u ON u.id = r.reviewer_user_id WHERE u.id IS NULL',
        'submission_without_current_version' => 'SELECT COUNT(*) FROM review_submissions s LEFT JOIN review_submission_versions v ON v.id = s.current_version_id WHERE s.current_version_id IS NOT NULL AND v.id IS NULL',
        'certificate_without_current_version' => 'SELECT COUNT(*) FROM certificates c LEFT JOIN certificate_versions v ON v.id = c.current_certificate_version_id WHERE c.current_certificate_version_id IS NOT NULL AND v.id IS NULL',
        'certificate_without_recipient' => 'SELECT COUNT(*) FROM certificates c LEFT JOIN application_certificate_recipients r ON r.id = c.application_certificate_recipient_id WHERE c.application_certificate_recipient_id IS NOT NULL AND r.id IS NULL',
    ];
    $integrity = [];
    foreach ($integrityQueries as $label => $sql) {
        $integrity[$label] = (int) $pdo->query($sql)->fetchColumn();
    }

    return [
        'database' => $database,
        'table_count' => count($tableNames),
        'row_counts' => $counts,
        'all_tables_check_ok' => count(array_filter($checkResults, fn (string $value): bool => $value !== 'OK')) === 0,
        'table_check_failures' => array_filter($checkResults, fn (string $value): bool => $value !== 'OK'),
        'foreign_key_count' => $foreignKeys,
        'migration_count' => count($migrations),
        'first_migration' => $migrations[0]['migration'] ?? null,
        'last_migration' => $migrations[array_key_last($migrations)]['migration'] ?? null,
        'migration_batches' => array_values(array_unique(array_map(fn (array $row): int => (int) $row['batch'], $migrations))),
        'role_counts' => $roleCounts,
        'application_status_counts' => $applicationStatusCounts,
        'integrity_orphans' => $integrity,
        'private_path_checks' => $pathChecks,
    ];
}

/** @return array<string, mixed> */
function compareDatabases(): array
{
    $livePdo = databasePdo(LIVE_DATABASE);
    $recoveryPdo = databasePdo(RECOVERY_DATABASE);
    $liveCounts = tableCounts(LIVE_DATABASE);
    $recoveryCounts = tableCounts(RECOVERY_DATABASE);
    $tables = array_values(array_unique([...array_keys($liveCounts), ...array_keys($recoveryCounts)]));
    sort($tables);
    $checksumMismatches = [];
    foreach ($tables as $table) {
        $liveChecksum = $livePdo->query('CHECKSUM TABLE `'.LIVE_DATABASE."`.`{$table}`")->fetch(PDO::FETCH_ASSOC);
        $recoveryChecksum = $recoveryPdo->query('CHECKSUM TABLE `'.RECOVERY_DATABASE."`.`{$table}`")->fetch(PDO::FETCH_ASSOC);
        if (($liveChecksum['Checksum'] ?? null) !== ($recoveryChecksum['Checksum'] ?? null)) {
            $checksumMismatches[$table] = [
                'live' => $liveChecksum['Checksum'] ?? null,
                'recovery' => $recoveryChecksum['Checksum'] ?? null,
            ];
        }
    }

    return [
        'same_table_names' => array_keys($liveCounts) === array_keys($recoveryCounts),
        'same_row_counts' => $liveCounts === $recoveryCounts,
        'checksum_mismatches' => $checksumMismatches,
    ];
}

$command = $argv[1] ?? '';

try {
    if ($command === 'rotate') {
        adminPdo()->exec('FLUSH BINARY LOGS');
        echo json_encode(['rotated' => true], JSON_PRETTY_PRINT).PHP_EOL;
        exit(0);
    }

    if ($command === 'create-recovery') {
        createDatabase(RECOVERY_DATABASE);
        echo json_encode(['created' => RECOVERY_DATABASE], JSON_PRETTY_PRINT).PHP_EOL;
        exit(0);
    }

    if ($command === 'replay') {
        $directory = dirname(__DIR__).'/tmp/mysql-recovery-20260823';
        replayLog(RECOVERY_DATABASE, $directory.'/binlog.000002', 401);
        foreach (['binlog.000003', 'binlog.000004', 'binlog.000005'] as $file) {
            replayLog(RECOVERY_DATABASE, $directory.'/'.$file);
        }
        replayLog(RECOVERY_DATABASE, $directory.'/binlog.000006.closed', null, 32686);
        echo json_encode(['replayed_to' => 'binlog.000006:32686'], JSON_PRETTY_PRINT).PHP_EOL;
        exit(0);
    }

    if ($command === 'inventory') {
        $database = $argv[2] ?? '';
        assertRecoveryDatabase($database);
        echo json_encode(['database' => $database, 'counts' => tableCounts($database)], JSON_PRETTY_PRINT).PHP_EOL;
        exit(0);
    }

    if ($command === 'validate') {
        $database = $argv[2] ?? '';
        echo json_encode(validateDatabase($database), JSON_PRETTY_PRINT).PHP_EOL;
        exit(0);
    }

    if ($command === 'compare') {
        echo json_encode(compareDatabases(), JSON_PRETTY_PRINT).PHP_EOL;
        exit(0);
    }

    if ($command === 'dump') {
        $database = $argv[2] ?? '';
        $target = $argv[3] ?? '';
        assertRecoveryDatabase($database);
        dumpDatabase($database, $target);
        echo json_encode(['database' => $database, 'dump' => $target, 'sha256' => hash_file('sha256', $target)], JSON_PRETTY_PRINT).PHP_EOL;
        exit(0);
    }

    if ($command === 'replace-live') {
        $confirmation = $argv[2] ?? '';
        $source = $argv[3] ?? '';
        if ($confirmation !== 'RESTORE-VERIFIED-ECRATS') {
            throw new RuntimeException('Exact live-restore confirmation missing.');
        }
        $pdo = adminPdo();
        $pdo->exec('DROP DATABASE `'.LIVE_DATABASE.'`');
        $pdo->exec('CREATE DATABASE `'.LIVE_DATABASE.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        importDump(LIVE_DATABASE, $source);
        echo json_encode(['restored' => LIVE_DATABASE, 'source_sha256' => hash_file('sha256', $source)], JSON_PRETTY_PRINT).PHP_EOL;
        exit(0);
    }

    throw new RuntimeException('Unknown recovery command.');
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
