[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$bin = 'C:\xampp\mysql\bin'
$initialize = Join-Path $bin 'mysql_install_db.exe'
$server = Join-Path $bin 'mysqld.exe'
$client = Join-Path $bin 'mysql.exe'
$admin = Join-Path $bin 'mysqladmin.exe'
$dumpTool = Join-Path $bin 'mysqldump.exe'
foreach ($path in @($initialize, $server, $client, $admin, $dumpTool)) {
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Required MariaDB binary missing: $path" }
}

$validationRoot = [IO.Path]::GetFullPath((Join-Path $root '.tools\adr-validation'))
$runId = [Guid]::NewGuid().ToString('N')
$runPath = [IO.Path]::GetFullPath((Join-Path $validationRoot $runId))
if (-not $runPath.StartsWith($validationRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Refusing unexpected validation path.' }
if (Test-Path -LiteralPath $runPath) { throw 'Random validation path unexpectedly exists; refusing to reuse it.' }

function New-RandomHex([int]$Bytes = 32) {
    $buffer = [byte[]]::new($Bytes)
    $generator = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($buffer); return [BitConverter]::ToString($buffer).Replace('-', '') }
    finally { $generator.Dispose() }
}

function Quote-WindowsArgument([string]$Value) {
    if ($Value -notmatch '[\s"]') { return $Value }
    $escaped = [regex]::Replace($Value, '(\\*)"', '$1$1\"')
    $escaped = [regex]::Replace($escaped, '(\\+)$', '$1$1')
    return '"' + $escaped + '"'
}

function Invoke-ChildProcess([string]$FilePath, [string[]]$Arguments, [string]$InputText = '') {
    $info = [Diagnostics.ProcessStartInfo]::new($FilePath)
    $info.WorkingDirectory = $bin
    $info.UseShellExecute = $false
    $info.CreateNoWindow = $true
    $info.RedirectStandardInput = $true
    $info.RedirectStandardOutput = $true
    $info.RedirectStandardError = $true
    $info.Arguments = (($Arguments | ForEach-Object { Quote-WindowsArgument $_ }) -join ' ')
    $child = [Diagnostics.Process]::new()
    $child.StartInfo = $info
    if (-not $child.Start()) { throw 'Could not start an owned local validation process.' }
    $stdout = $child.StandardOutput.ReadToEndAsync()
    $stderr = $child.StandardError.ReadToEndAsync()
    if (-not [string]::IsNullOrEmpty($InputText)) { $child.StandardInput.Write($InputText) }
    $child.StandardInput.Close()
    $child.WaitForExit()
    [pscustomobject]@{ ExitCode = $child.ExitCode; StdOut = $stdout.GetAwaiter().GetResult(); StdErr = $stderr.GetAwaiter().GetResult() }
}

function Write-PrivateFile([string]$Path, [string]$Text) {
    [IO.File]::WriteAllText($Path, $Text, [Text.UTF8Encoding]::new($false))
    $fileAcl = Get-Acl -LiteralPath $Path
    $fileAcl.SetAccessRuleProtection($true, $false)
    $fileAcl.SetOwner([Security.Principal.WindowsIdentity]::GetCurrent().User)
    $rule = [Security.AccessControl.FileSystemAccessRule]::new([Security.Principal.WindowsIdentity]::GetCurrent().Name, 'FullControl', 'Allow')
    [void]$fileAcl.AddAccessRule($rule)
    Set-Acl -LiteralPath $Path -AclObject $fileAcl
}

function Get-SafeQueueOutput([string]$Text) {
    # Only fixture markers and numeric SQL diagnostics may leave the disposable run.
    foreach ($line in ($Text -split '\r?\n')) {
        if ($line -match '^(PASS: [A-Za-z0-9 ;_-]+|FAIL: QUEUE_[A-Z_]+|RESULT: (PASS|FAIL) assertions=\d+|SQL_ERROR: SQLSTATE=[A-Z0-9]{5} code=\d+)$') { $line }
        elseif (-not [string]::IsNullOrWhiteSpace($line)) { '[REDACTED unstructured child output]' }
    }
}

$owner = "My Arasya ADR validation only:$runId"
$marker = Join-Path $runPath '.owner'
$started = Get-Date
$process = $null
$raceProcess = $null
$queueProcess = $null
$oldEnvironment = @{}
$created = $false
$completedResults = [Collections.Generic.List[string]]::new()
$rootPassword = New-RandomHex
$appPassword = New-RandomHex
$appUser = 'adrv_' + (New-RandomHex 8).ToLowerInvariant()
$database = 'adrv_' + $runId.Substring(0, 16)
try {
    New-Item -ItemType Directory -Path $runPath -Force:$false | Out-Null
    $created = $true
    $runAcl = Get-Acl -LiteralPath $runPath
    $runAcl.SetAccessRuleProtection($true, $false)
    $runAcl.SetOwner([Security.Principal.WindowsIdentity]::GetCurrent().User)
    $runRule = [Security.AccessControl.FileSystemAccessRule]::new([Security.Principal.WindowsIdentity]::GetCurrent().Name, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
    [void]$runAcl.AddAccessRule($runRule)
    Set-Acl -LiteralPath $runPath -AclObject $runAcl
    Set-Content -LiteralPath $marker -Value $owner -Encoding UTF8 -NoNewline

    $dataPath = [IO.Path]::GetFullPath((Join-Path $runPath 'data'))
    New-Item -ItemType Directory -Path $dataPath -Force:$false | Out-Null
    $rootConfig = Join-Path $runPath 'root-client.cnf'
    $appConfig = Join-Path $runPath 'app-client.cnf'
    Write-PrivateFile $rootConfig "[client]`r`nuser=root`r`npassword=$rootPassword`r`nhost=127.0.0.1`r`nprotocol=tcp`r`n"
    Write-PrivateFile $appConfig "[client]`r`nuser=$appUser`r`npassword=$appPassword`r`nhost=127.0.0.1`r`nprotocol=tcp`r`n"

    $portListener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
    $portListener.Start()
    $port = ([Net.IPEndPoint]$portListener.LocalEndpoint).Port
    $portListener.Stop()
    $initArgs = @("--datadir=$dataPath", "--password=$rootPassword", '--skip-networking', "--port=$port")
    $init = Invoke-ChildProcess $initialize $initArgs
    if ($init.ExitCode -ne 0) { throw 'MariaDB authenticated datadir initialization failed; diagnostic category: INIT_EXIT_NONZERO.' }

    $serverArgs = @('--no-defaults', "--basedir=$bin\..", "--datadir=$dataPath", "--port=$port", '--bind-address=127.0.0.1', '--console', "--pid-file=$(Join-Path $runPath 'mysqld.pid')", "--log-error=$(Join-Path $runPath 'mysqld.err')")
    $process = Start-Process -FilePath $server -ArgumentList $serverArgs -WorkingDirectory $bin -PassThru -NoNewWindow -RedirectStandardOutput (Join-Path $runPath 'server.out') -RedirectStandardError (Join-Path $runPath 'server.err')

    $ready = $false
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        if ($process.HasExited) { throw 'Isolated MariaDB exited during startup; diagnostic category: SERVER_EXITED.' }
        $ping = Invoke-ChildProcess $admin @("--defaults-file=$rootConfig", '--protocol=tcp', '-h', '127.0.0.1', '-P', "$port", 'ping')
        if ($ping.ExitCode -eq 0) { $ready = $true; break }
        Start-Sleep -Milliseconds 500
    }
    if (-not $ready) { throw 'Authenticated ping to the owned loopback MariaDB instance timed out.' }

    $createDb = Invoke-ChildProcess $admin @("--defaults-file=$rootConfig", '--protocol=tcp', '-h', '127.0.0.1', '-P', "$port", 'create', $database)
    if ($createDb.ExitCode -ne 0) { throw 'Synthetic database creation failed; diagnostic category: DB_CREATE_FAILED.' }
    $accountSql = "CREATE USER '$appUser'@'127.0.0.1' IDENTIFIED BY '$appPassword'; GRANT ALL PRIVILEGES ON ``$database``.* TO '$appUser'@'127.0.0.1'; FLUSH PRIVILEGES;`n"
    $account = Invoke-ChildProcess $client @("--defaults-file=$rootConfig", '--protocol=tcp', '-h', '127.0.0.1', '-P', "$port", '--batch') $accountSql
    if ($account.ExitCode -ne 0) { throw 'Least-privilege test account setup failed; diagnostic category: ACCOUNT_SETUP_FAILED.' }

    $php = @'
<?php
require $argv[1] . '/vendor/autoload.php';
$app = require $argv[1] . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
final class AdrValidationRetryJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function handle(): void {
        if ($this->attempts() < 2) { throw new RuntimeException('synthetic transient retry'); }
        DB::transaction(fn () => DB::table('adrv_consumer_effects')->insert(['event_id' => 'event-once', 'effect' => 'synthetic']));
    }
}
$count = 0;
$check = static function (bool $ok, string $name) use (&$count): void { if (! $ok) { throw new RuntimeException('ASSERTION:' . $name); } $count++; echo "PASS: {$name}\n"; };
$phase = 'owned_server_identity';
try {
    $identity = DB::selectOne('select version() as version, @@port as port, @@datadir as datadir');
    $check(str_contains(strtolower($identity->version), 'mariadb') && (int) $identity->port === (int) getenv('DB_PORT') && realpath($identity->datadir) === realpath(getenv('ADR_EXPECTED_DATADIR')), 'authenticated owned server identity/version/port/datadir');

    $phase = 'forward_schema_backfill'; $migrationStarted = microtime(true);
    Schema::create('adrv_forward', function (Blueprint $table): void { $table->ulid('id')->primary(); $table->string('legacy_value'); });
    $ids = [(string) Illuminate\Support\Str::ulid(), (string) Illuminate\Support\Str::ulid()];
    DB::table('adrv_forward')->insert([['id' => $ids[0], 'legacy_value' => 'alpha'], ['id' => $ids[1], 'legacy_value' => 'beta']]);
    Schema::table('adrv_forward', function (Blueprint $table): void { $table->string('normalized_value')->nullable(); });
    $changed = DB::table('adrv_forward')->whereNull('normalized_value')->update(['normalized_value' => DB::raw('legacy_value')]);
    $check($changed === 2 && DB::table('adrv_forward')->whereNull('normalized_value')->count() === 0, 'forward-compatible schema alter and initial backfill');
    $replay = DB::table('adrv_forward')->whereNull('normalized_value')->update(['normalized_value' => DB::raw('legacy_value')]);
    Schema::table('adrv_forward', function (Blueprint $table): void { $table->unique('normalized_value', 'adrv_forward_norm_uq'); });
    $check($replay === 0 && DB::table('adrv_forward')->whereNotNull('normalized_value')->count() === 2, 'backfill replay is idempotent');
    $check(Schema::hasColumn('adrv_forward', 'normalized_value') && count(Schema::getIndexes('adrv_forward')) >= 2, 'forward schema and unique index visible through Laravel Schema');
    Schema::create('adrv_forward_empty', function (Blueprint $table): void { $table->ulid('id')->primary(); $table->string('legacy_value'); $table->string('normalized_value')->nullable(); $table->unique('normalized_value', 'adrv_forward_norm_uq'); });
    $columnsUpgraded = Schema::getColumnListing('adrv_forward'); $columnsEmpty = Schema::getColumnListing('adrv_forward_empty');
    $indexesUpgraded = array_column(Schema::getIndexes('adrv_forward'), 'name'); $indexesEmpty = array_column(Schema::getIndexes('adrv_forward_empty'), 'name');
    $ddlUpgradedRow = DB::selectOne('SHOW CREATE TABLE adrv_forward'); $ddlEmptyRow = DB::selectOne('SHOW CREATE TABLE adrv_forward_empty');
    $ddlUpgraded = (string) ($ddlUpgradedRow->{'Create Table'} ?? ''); $ddlEmpty = (string) ($ddlEmptyRow->{'Create Table'} ?? '');
    $check($columnsUpgraded === $columnsEmpty && $indexesUpgraded === $indexesEmpty && DB::table('adrv_forward')->count() === 2 && DB::table('adrv_forward_empty')->count() === 0 && $ddlUpgraded !== '' && $ddlEmpty !== '', 'synthetic prior-upgraded and empty-built schemas match with rows preserved');
    $startedBackfill = microtime(true);
    DB::table('adrv_forward')->insert([['id' => (string) Illuminate\Support\Str::ulid(), 'legacy_value' => 'gamma', 'normalized_value' => null], ['id' => (string) Illuminate\Support\Str::ulid(), 'legacy_value' => 'delta', 'normalized_value' => null]]);
    DB::table('adrv_forward')->where('legacy_value', 'gamma')->update(['normalized_value' => 'gamma']);
    $recovered = DB::table('adrv_forward')->whereNull('normalized_value')->update(['normalized_value' => DB::raw('legacy_value')]);
    $recoveredReplay = DB::table('adrv_forward')->whereNull('normalized_value')->update(['normalized_value' => DB::raw('legacy_value')]);
    $check($recovered === 1 && $recoveredReplay === 0 && DB::table('adrv_forward')->whereNull('normalized_value')->count() === 0 && DB::table('adrv_forward')->count() === 4, 'interrupted forward backfill resumes and replay remains idempotent');
    $migrationEvidence = 'schema_match ddl=' . preg_replace('/\\s+/', ' ', $ddlUpgraded) . ' build_and_compare_seconds=' . round(microtime(true) - $migrationStarted, 6) . ' recovery_seconds=' . round(microtime(true) - $startedBackfill, 6);
    file_put_contents(getenv('ADR_EXPECTED_DATADIR') . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'migration-summary.txt', $migrationEvidence . "\n");
    fwrite(STDOUT, 'INFO: ' . $migrationEvidence . "\n");

    $phase = 'scoped_schema_decimal';
    Schema::create('adrv_parents', function (Blueprint $table): void { $table->ulid('id')->primary(); $table->char('entity_id', 26); $table->string('code', 30); $table->unique(['entity_id', 'id'], 'adrv_parent_entity_id_uq'); $table->unique(['entity_id', 'code'], 'adrv_parent_entity_code_uq'); });
    Schema::create('adrv_children', function (Blueprint $table): void { $table->ulid('id')->primary(); $table->char('entity_id', 26); $table->ulid('parent_id'); $table->string('idempotency_key', 40); $table->decimal('amount', 20, 6); $table->index(['entity_id', 'parent_id'], 'adrv_child_scope_search_ix'); $table->foreign(['entity_id', 'parent_id'], 'adrv_child_scope_parent_fk')->references(['entity_id', 'id'])->on('adrv_parents'); $table->unique(['entity_id', 'idempotency_key'], 'adrv_child_idempotency_uq'); });
    $entity = (string) Illuminate\Support\Str::ulid(); $other = (string) Illuminate\Support\Str::ulid();
    $parent = (string) Illuminate\Support\Str::ulid(); $otherParent = (string) Illuminate\Support\Str::ulid();
    DB::table('adrv_parents')->insert([['id' => $parent, 'entity_id' => $entity, 'code' => 'ONLY-A'], ['id' => $otherParent, 'entity_id' => $other, 'code' => 'ONLY-B']]);
    $child = (string) Illuminate\Support\Str::ulid();
    DB::table('adrv_children')->insert(['id' => $child, 'entity_id' => $entity, 'parent_id' => $parent, 'idempotency_key' => 'event-1', 'amount' => '12345678901234.123456']);
    $row = DB::table('adrv_children')->where('entity_id', $entity)->where('id', $child)->first();
    $check($row !== null && (string) $row->id === $child && (string) $row->parent_id === $parent && (string) $row->amount === '12345678901234.123456', 'ULID PK/FK round-trip and synthetic exact decimal string');
    Schema::create('adrv_uuid_parent', function (Blueprint $table): void { $table->uuid('id')->primary(); });
    Schema::create('adrv_uuid_child', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->uuid('parent_id'); $table->foreign('parent_id', 'adrv_uuid_parent_fk')->references('id')->on('adrv_uuid_parent'); });
    $uuidParent = (string) Illuminate\Support\Str::uuid7(); $uuidChild = (string) Illuminate\Support\Str::uuid7();
    DB::table('adrv_uuid_parent')->insert(['id' => $uuidParent]); DB::table('adrv_uuid_child')->insert(['id' => $uuidChild, 'parent_id' => $uuidParent]);
    $check((string) DB::table('adrv_uuid_child')->where('id', $uuidChild)->value('parent_id') === $uuidParent && preg_match('/^[0-9a-f-]{36}$/i', $uuidParent) === 1, 'Laravel UUIDv7 PK/FK round-trip');
    $check(DB::table('adrv_children')->where('entity_id', $other)->where('id', $child)->count() === 0, 'indexed entity-scoped SQL query returns no cross-scope row');
    $check(DB::table('adrv_children')->where('amount', '12345678901234.123456')->sum('amount') === '12345678901234.123456', 'synthetic decimal sum remains exact string (precision 20, scale 6)');
    $duplicateRejected = false;
    try { DB::table('adrv_children')->insert(['id' => (string) Illuminate\Support\Str::ulid(), 'entity_id' => $entity, 'parent_id' => $parent, 'idempotency_key' => 'event-1', 'amount' => '1.000000']); } catch (Illuminate\Database\QueryException $expected) { if ((int) ($expected->errorInfo[1] ?? 0) !== 1062) { throw $expected; } $duplicateRejected = true; }
    $check($duplicateRejected, 'scoped idempotency unique rejects duplicate with MariaDB 1062');
    $fkRejected = false;
    try { DB::table('adrv_children')->insert(['id' => (string) Illuminate\Support\Str::ulid(), 'entity_id' => $other, 'parent_id' => $parent, 'idempotency_key' => 'event-cross', 'amount' => '1.000000']); } catch (Illuminate\Database\QueryException $expected) { if ((int) ($expected->errorInfo[1] ?? 0) !== 1452) { throw $expected; } $fkRejected = true; }
    $check($fkRejected, 'composite foreign key rejects parent from other entity with MariaDB 1452');
    $sqlMode = (string) DB::scalar('select @@sql_mode');
    if (! preg_match('/(?:STRICT_TRANS_TABLES|STRICT_ALL_TABLES)/', $sqlMode)) { throw new RuntimeException('ASSERTION:strict_mode_required'); }
    $overflowRejected = false;
    try { DB::table('adrv_children')->insert(['id' => (string) Illuminate\Support\Str::ulid(), 'entity_id' => $entity, 'parent_id' => $parent, 'idempotency_key' => 'overflow', 'amount' => '123456789012345.123456']); } catch (Illuminate\Database\QueryException $expected) { if ((int) ($expected->errorInfo[1] ?? 0) !== 1264) { throw $expected; } $overflowRejected = true; }
    $check($overflowRejected, 'synthetic decimal precision overflow rejects with MariaDB 1264 under strict mode');
    DB::beginTransaction(); DB::table('adrv_children')->insert(['id' => (string) Illuminate\Support\Str::ulid(), 'entity_id' => $entity, 'parent_id' => $parent, 'idempotency_key' => 'rollback', 'amount' => '2.000000']); DB::rollBack();
    $check(DB::table('adrv_children')->where('idempotency_key', 'rollback')->count() === 0, 'Laravel transaction rollback');

    $phase = 'independent_connection_locks';
    Schema::create('adrv_outbox', function (Blueprint $table): void { $table->string('event_id', 40)->primary(); $table->string('event_type', 40); $table->string('aggregate_id', 40); });
    Schema::create('adrv_journals', function (Blueprint $table): void { $table->string('journal_id', 40)->primary(); $table->string('period_id', 40); });
    Schema::create('adrv_lock_cases', function (Blueprint $table): void { $table->unsignedTinyInteger('id')->primary(); $table->unsignedInteger('value')->default(0); });
    DB::table('adrv_lock_cases')->insert([['id' => 1, 'value' => 0], ['id' => 2, 'value' => 0], ['id' => 3, 'value' => 0]]);
    config(['database.connections.adr_validation_secondary' => config('database.connections.mariadb')]);
    DB::purge('adr_validation_secondary');
    $first = DB::connection('mariadb'); $second = DB::connection('adr_validation_secondary');
    foreach (['reserve' => 1, 'issue' => 2, 'period-close' => 3] as $scenario => $lockId) {
        $first->beginTransaction(); $second->beginTransaction();
        $first->table('adrv_lock_cases')->where('id', $lockId)->lockForUpdate()->first();
        $second->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $blocked = false;
        try { $second->table('adrv_lock_cases')->where('id', $lockId)->increment('value'); } catch (Illuminate\Database\QueryException $expected) { if ((int) ($expected->errorInfo[1] ?? 0) !== 1205) { throw $expected; } $blocked = true; }
        $second->rollBack();
        if ($blocked) { $first->table('adrv_lock_cases')->where('id', $lockId)->increment('value'); }
        $first->commit();
        $check($blocked && (int) DB::table('adrv_lock_cases')->where('id', $lockId)->value('value') === 1, "two independent connections serialize synthetic {$scenario} lock");
    }
    $first->beginTransaction();
    $first->table('adrv_journals')->insert(['journal_id' => 'journal-uncommitted', 'period_id' => 'period-visibility']);
    $first->table('adrv_outbox')->insert(['event_id' => 'outbox-uncommitted', 'event_type' => 'visibility-test', 'aggregate_id' => 'journal-uncommitted']);
    $hiddenBeforeCommit = $second->table('adrv_journals')->where('journal_id', 'journal-uncommitted')->count() === 0 && $second->table('adrv_outbox')->where('event_id', 'outbox-uncommitted')->count() === 0;
    $first->commit();
    $visibleAfterCommit = $second->table('adrv_journals')->where('journal_id', 'journal-uncommitted')->count() === 1 && $second->table('adrv_outbox')->where('event_id', 'outbox-uncommitted')->count() === 1;
    $check($hiddenBeforeCommit && $visibleAfterCommit, 'independent connection cannot see uncommitted domain/outbox rows and sees both after commit');
    DB::disconnect('adr_validation_secondary');

    $phase = 'synthetic_acceptance_state_transitions';
    Schema::create('adrv_stock_state', function (Blueprint $table): void { $table->string('item', 20)->primary(); $table->integer('available')->default(0); $table->integer('reserved')->default(0); $table->integer('on_hand')->default(0); });
    Schema::create('adrv_reservations', function (Blueprint $table): void { $table->string('request_key', 40)->primary(); $table->string('item', 20); $table->integer('quantity'); });
    Schema::create('adrv_movements', function (Blueprint $table): void { $table->string('request_key', 40)->primary(); $table->string('item', 20); $table->integer('quantity'); });
    Schema::create('adrv_inbox', function (Blueprint $table): void { $table->string('event_id', 40)->primary(); $table->integer('version'); });
    Schema::create('adrv_consumer_effects', function (Blueprint $table): void { $table->string('event_id', 40)->primary(); $table->string('effect', 30); });
    Schema::create('adrv_periods', function (Blueprint $table): void { $table->string('period_id', 40)->primary(); $table->boolean('closed')->default(false); });
    DB::table('adrv_stock_state')->insert(['item' => 'reserve-item', 'available' => 4, 'reserved' => 0, 'on_hand' => 4]);
    $reserve = static function (string $key) use ($check): bool {
        return DB::transaction(function () use ($key, $check): bool {
            if (DB::table('adrv_reservations')->where('request_key', $key)->exists()) { return true; }
            $stock = DB::table('adrv_stock_state')->where('item', 'reserve-item')->lockForUpdate()->first();
            if ($stock->available < 4) { return false; }
            DB::table('adrv_stock_state')->where('item', 'reserve-item')->update(['available' => $stock->available - 4, 'reserved' => $stock->reserved + 4]);
            DB::table('adrv_reservations')->insert(['request_key' => $key, 'item' => 'reserve-item', 'quantity' => 4]);
            DB::table('adrv_outbox')->insert(['event_id' => 'reserve-event-' . $key, 'event_type' => 'reserved', 'aggregate_id' => $key]);
            return true;
        });
    };
    $reserveFirst = $reserve('reserve-a'); $reserveReplay = $reserve('reserve-a'); $reserveRejected = $reserve('reserve-b');
    $check($reserveFirst && $reserveReplay && ! $reserveRejected && DB::table('adrv_stock_state')->where('item', 'reserve-item')->value('available') === 0 && DB::table('adrv_reservations')->where('item', 'reserve-item')->count() === 1 && DB::table('adrv_outbox')->where('event_type', 'reserved')->count() === 1, 'synthetic reserve transition, same-key replay, and rejected request have one persisted effect');
    DB::table('adrv_stock_state')->insert(['item' => 'issue-item', 'available' => 0, 'reserved' => 5, 'on_hand' => 5]);
    $issue = static function (string $key) use ($check): bool {
        return DB::transaction(function () use ($key): bool {
            $stock = DB::table('adrv_stock_state')->where('item', 'issue-item')->lockForUpdate()->first();
            if ($stock->reserved < 4 || $stock->on_hand < 4) { return false; }
            DB::table('adrv_stock_state')->where('item', 'issue-item')->update(['reserved' => $stock->reserved - 4, 'on_hand' => $stock->on_hand - 4]);
            DB::table('adrv_movements')->insert(['request_key' => $key, 'item' => 'issue-item', 'quantity' => 4]);
            DB::table('adrv_outbox')->insert(['event_id' => 'issue-event-' . $key, 'event_type' => 'issued', 'aggregate_id' => $key]);
            return true;
        });
    };
    $issueFirst = $issue('issue-a'); $issueRejected = $issue('issue-b');
    $issueState = DB::table('adrv_stock_state')->where('item', 'issue-item')->first();
    $check($issueFirst && ! $issueRejected && $issueState->reserved === 1 && $issueState->on_hand === 1 && DB::table('adrv_movements')->where('item', 'issue-item')->count() === 1 && DB::table('adrv_outbox')->where('event_type', 'issued')->count() === 1, 'synthetic issue transition emits one movement/outbox and decrements stock once');
    DB::table('adrv_periods')->insert(['period_id' => 'period-open', 'closed' => false]);
    DB::transaction(function (): void { DB::table('adrv_journals')->insert(['journal_id' => 'journal-before-close', 'period_id' => 'period-open']); DB::table('adrv_outbox')->insert(['event_id' => 'period-post-event', 'event_type' => 'journal-posted', 'aggregate_id' => 'journal-before-close']); });
    DB::table('adrv_periods')->where('period_id', 'period-open')->update(['closed' => true]);
    DB::table('adrv_periods')->insert(['period_id' => 'period-closed-first', 'closed' => true]);
    $closedPostCode = null;
    DB::transaction(function () use (&$closedPostCode): void {
        $period = DB::table('adrv_periods')->where('period_id', 'period-closed-first')->lockForUpdate()->first();
        if ($period->closed) { $closedPostCode = 'FIN_PERIOD_CLOSED'; return; }
        DB::table('adrv_journals')->insert(['journal_id' => 'journal-after-close', 'period_id' => 'period-closed-first']);
        DB::table('adrv_outbox')->insert(['event_id' => 'period-post-after-close', 'event_type' => 'journal-posted', 'aggregate_id' => 'journal-after-close']);
    });
    $check($closedPostCode === 'FIN_PERIOD_CLOSED' && DB::table('adrv_journals')->where('period_id', 'period-open')->count() === 1 && DB::table('adrv_journals')->where('period_id', 'period-closed-first')->count() === 0 && DB::table('adrv_outbox')->where('event_id', 'period-post-after-close')->count() === 0, 'synthetic period close ordering posts before close and rejects afterward without journal/outbox');
    $beforeOutbox = DB::table('adrv_outbox')->count(); $beforeJournals = DB::table('adrv_journals')->count();
    try { DB::transaction(function (): void { DB::table('adrv_journals')->insert(['journal_id' => 'rollback-domain', 'period_id' => 'period-open']); DB::table('adrv_outbox')->insert(['event_id' => 'rollback-outbox-event', 'event_type' => 'journal-posted', 'aggregate_id' => 'rollback-domain']); throw new RuntimeException('synthetic rollback'); }); } catch (RuntimeException $expected) { if ($expected->getMessage() !== 'synthetic rollback') { throw $expected; } }
    $check(DB::table('adrv_outbox')->count() === $beforeOutbox && DB::table('adrv_journals')->count() === $beforeJournals && ! DB::table('adrv_outbox')->where('event_id', 'rollback-outbox-event')->exists() && ! DB::table('adrv_journals')->where('journal_id', 'rollback-domain')->exists(), 'domain journal and outbox writes both roll back atomically');
    $consumer = static function (string $event, int $version): bool {
        return DB::transaction(function () use ($event, $version): bool {
            if (DB::table('adrv_inbox')->where('event_id', $event)->exists()) { return false; }
            DB::table('adrv_inbox')->insert(['event_id' => $event, 'version' => $version]);
            DB::table('adrv_consumer_effects')->insert(['event_id' => $event, 'effect' => 'synthetic']);
            return true;
        });
    };
    $consumerFirst = $consumer('fixed-event-id', 1); $consumerReplay = $consumer('fixed-event-id', 1);
    $check($consumerFirst && ! $consumerReplay && DB::table('adrv_inbox')->where('event_id', 'fixed-event-id')->count() === 1 && DB::table('adrv_consumer_effects')->where('event_id', 'fixed-event-id')->count() === 1, 'inbox/effect commit atomically and fixed event id replay deduplicates');
    $inboxCount = DB::table('adrv_inbox')->count();
    $inboxAtomicRejected = false;
    try { DB::transaction(function (): void { DB::table('adrv_inbox')->insert(['event_id' => 'atomic-failure-event', 'version' => 1]); DB::table('adrv_consumer_effects')->insert(['event_id' => 'fixed-event-id', 'effect' => 'duplicate']); }); } catch (Illuminate\Database\QueryException $expected) { if ((int) ($expected->errorInfo[1] ?? 0) !== 1062) { throw $expected; } $inboxAtomicRejected = true; }
    $check($inboxAtomicRejected && DB::table('adrv_inbox')->count() === $inboxCount && ! DB::table('adrv_inbox')->where('event_id', 'atomic-failure-event')->exists(), 'failed consumer effect rolls back its newly inserted inbox row');
    Schema::create('adrv_projection', function (Blueprint $table): void { $table->string('aggregate_id', 40)->primary(); $table->integer('version'); });
    $deliver = static function (string $event, int $version): bool {
        return DB::transaction(function () use ($event, $version): bool {
            if (DB::table('adrv_inbox')->where('event_id', $event)->exists()) { return false; }
            DB::table('adrv_inbox')->insert(['event_id' => $event, 'version' => $version]);
            $projection = DB::table('adrv_projection')->where('aggregate_id', 'aggregate-order')->lockForUpdate()->first();
            if ($projection !== null && $version <= $projection->version) { return false; }
            DB::table('adrv_projection')->updateOrInsert(['aggregate_id' => 'aggregate-order'], ['version' => $version]);
            DB::table('adrv_consumer_effects')->insert(['event_id' => $event, 'effect' => 'projection']);
            return true;
        });
    };
    $deliveredV3 = $deliver('aggregate-event-v3', 3); $deliveredV2 = $deliver('aggregate-event-v2', 2);
    $check($deliveredV3 && ! $deliveredV2 && (int) DB::table('adrv_projection')->where('aggregate_id', 'aggregate-order')->value('version') === 3 && DB::table('adrv_consumer_effects')->where('effect', 'projection')->count() === 1, 'version 3 then version 2 delivery keeps projection current and does not repeat effect');

    $phase = 'database_queue_cache';
    Schema::create('jobs', function (Blueprint $table): void { $table->bigIncrements('id'); $table->string('queue')->index(); $table->longText('payload'); $table->unsignedTinyInteger('attempts'); $table->unsignedInteger('reserved_at')->nullable(); $table->unsignedInteger('available_at'); $table->unsignedInteger('created_at'); });
    Schema::create('cache', function (Blueprint $table): void { $table->string('key')->primary(); $table->mediumText('value'); $table->integer('expiration'); });
    $queue = Queue::connection('database');
    $queueId = $queue->push(new AdrValidationRetryJob());
    $job1 = $queue->pop('default');
    $firstAttempt = $job1 !== null ? $job1->attempts() : 0;
    $firstFailed = false;
    try { $job1->fire(); } catch (RuntimeException $expected) { $firstFailed = true; $job1->release(0); }
    $job2 = $queue->pop('default');
    $secondAttempt = $job2 !== null ? $job2->attempts() : 0;
    $job2->fire(); $job2->delete();
    $check($queueId !== null && $firstFailed && $firstAttempt === 1 && $secondAttempt === 2 && DB::table('adrv_consumer_effects')->where('event_id', 'event-once')->count() === 1, 'Laravel database queue job handler retries and commits unique consumer effect');
    $duplicateEffectRejected = false;
    try { DB::transaction(function (): void { DB::table('adrv_consumer_effects')->insert(['event_id' => 'event-once', 'effect' => 'synthetic']); }); } catch (Illuminate\Database\QueryException $expected) { if ((int) ($expected->errorInfo[1] ?? 0) !== 1062) { throw $expected; } $duplicateEffectRejected = true; }
    $check($duplicateEffectRejected && DB::table('adrv_consumer_effects')->where('event_id', 'event-once')->count() === 1, 'transactional unique consumer effect deduplicates replay with MariaDB 1062');
    $keyA = "scope:$entity:user:synthetic:search"; $keyB = "scope:$other:user:synthetic:search";
    Cache::store('database')->put($keyA, 'A', 60); Cache::store('database')->put($keyB, 'B', 60);
    $check(Cache::store('database')->get($keyA) === 'A' && Cache::store('database')->get($keyB) === 'B', 'database cache stores isolated scoped keys');
    Cache::store('database')->forget($keyA);
    $check(Cache::store('database')->get($keyA) === null && Cache::store('database')->get($keyB) === 'B', 'database cache forget preserves unrelated scope');
    Schema::create('adrv_search_records', function (Blueprint $table): void { $table->ulid('id')->primary(); $table->char('entity_id', 26); $table->string('display_name', 80); $table->string('contact_email', 120); $table->boolean('hidden')->default(false); $table->boolean('deleted')->default(false); $table->index(['entity_id', 'hidden', 'deleted'], 'adrv_search_scope_state_ix'); });
    Schema::create('adrv_permissions', function (Blueprint $table): void { $table->string('user_id', 30); $table->char('entity_id', 26); $table->boolean('can_read'); $table->primary(['user_id', 'entity_id']); });
    DB::table('adrv_search_records')->insert([['id' => (string) Illuminate\Support\Str::ulid(), 'entity_id' => $entity, 'display_name' => 'Synthetic One', 'contact_email' => 'private@example.invalid', 'hidden' => false, 'deleted' => false], ['id' => (string) Illuminate\Support\Str::ulid(), 'entity_id' => $other, 'display_name' => 'Synthetic Two', 'contact_email' => 'other@example.invalid', 'hidden' => false, 'deleted' => false], ['id' => (string) Illuminate\Support\Str::ulid(), 'entity_id' => $entity, 'display_name' => 'Hidden Record', 'contact_email' => 'hidden@example.invalid', 'hidden' => true, 'deleted' => false]]);
    DB::table('adrv_permissions')->insert(['user_id' => 'reader', 'entity_id' => $entity, 'can_read' => true]);
    $scopedExport = static function (string $user, string $scope): array {
        if (! DB::table('adrv_permissions')->where('user_id', $user)->where('entity_id', $scope)->where('can_read', true)->exists()) { return []; }
        return DB::table('adrv_search_records')->where('entity_id', $scope)->where('hidden', false)->where('deleted', false)->get()->map(static function ($row): array { $parts = explode('@', $row->contact_email, 2); $row->contact_email = substr($parts[0], 0, 1) . '***@' . ($parts[1] ?? 'invalid'); return (array) $row; })->all();
    };
    $searchRows = $scopedExport('reader', $entity); $searchCount = count($searchRows); $maskedExport = $searchRows[0]['contact_email'] === 'p***@example.invalid' && DB::table('adrv_search_records')->where('entity_id', $other)->count() === 1;
    $check($searchCount === 1 && $maskedExport && $scopedExport('reader', $other) === [], 'synthetic scoped search/count/export excludes other entity and hidden rows and masks contact fields');
    Cache::store('database')->put("permission:$entity:reader", true, 60);
    DB::table('adrv_permissions')->where('user_id', 'reader')->where('entity_id', $entity)->update(['can_read' => false]);
    $check(Cache::store('database')->get("permission:$entity:reader") === true && $scopedExport('reader', $entity) === [], 'revoked queued access is denied at execution despite stale allow cache');
    $indexNames = array_column(DB::select("SELECT INDEX_NAME AS name FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND LEFT(TABLE_NAME, 5) = 'adrv_'"), 'name');
    $constraintNames = array_column(DB::select("SELECT CONSTRAINT_NAME AS name FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND LEFT(TABLE_NAME, 5) = 'adrv_'"), 'name');
    $allNames = array_merge($indexNames, $constraintNames);
    $invalidNames = array_filter($allNames, static fn ($name) => strlen($name) > 60 || preg_match('/^[\x00-\x7F]+$/D', $name) !== 1);
    $check(count($allNames) > 0 && count($invalidNames) === 0, 'all generated synthetic index/FK/constraint names are ASCII and at most 60 bytes');
    echo "RESULT: PASS assertions={$count}\n";
} catch (Throwable $failure) {
    $safe = preg_match('/ASSERTION:([A-Za-z0-9 _-]+)/', $failure->getMessage(), $match) ? $match[1] : $phase;
    if ($failure instanceof PDOException && isset($failure->errorInfo[0], $failure->errorInfo[1])) {
        $native = preg_replace("/'[^']*'|\"[^\"]*\"|`[^`]*`/", '[REDACTED]', (string) ($failure->errorInfo[2] ?? ''));
        $native = str_replace([getenv('DB_PASSWORD'), getenv('DB_USERNAME'), getenv('DB_DATABASE'), "\r", "\n"], '[REDACTED]', $native);
        fwrite(STDOUT, 'SQL_ERROR: SQLSTATE=' . $failure->errorInfo[0] . ' code=' . (int) $failure->errorInfo[1] . ' message=' . $native . "\n");
    }
    fwrite(STDOUT, "FAIL: {$safe}\nRESULT: FAIL assertions={$count}\n");
    exit(1);
} finally {
    DB::disconnect();
}
'@
    $phpFile = Join-Path $runPath 'validation.php'
    [IO.File]::WriteAllText($phpFile, $php, [Text.UTF8Encoding]::new($false))
    $dbEnvironmentNames = @(Get-ChildItem Env: | Where-Object { $_.Name -match '^(DB_|DATABASE_URL$)' } | ForEach-Object { $_.Name })
    $envNames = @('APP_ENV','APP_CONFIG_CACHE','DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','DB_URL','CACHE_STORE','QUEUE_CONNECTION','ADR_EXPECTED_DATADIR') + $dbEnvironmentNames | Select-Object -Unique
    foreach ($name in $envNames) { $oldEnvironment[$name] = [Environment]::GetEnvironmentVariable($name, 'Process'); if ($name -match '^(DB_|DATABASE_URL$)') { [Environment]::SetEnvironmentVariable($name, '', 'Process') } }
    $env:APP_ENV = 'testing'; $env:APP_CONFIG_CACHE = Join-Path $runPath 'no-config-cache.php'; $env:DB_CONNECTION = 'mariadb'; $env:DB_HOST = '127.0.0.1'; $env:DB_PORT = "$port"
    $env:DB_DATABASE = $database; $env:DB_USERNAME = $appUser; $env:DB_PASSWORD = $appPassword; $env:DB_URL = ''
    $env:CACHE_STORE = 'database'; $env:QUEUE_CONNECTION = 'database'; $env:ADR_EXPECTED_DATADIR = $dataPath
    $phpResult = Start-Process -FilePath 'php' -ArgumentList @($phpFile, $root) -WorkingDirectory $root -Wait -PassThru -NoNewWindow -RedirectStandardOutput (Join-Path $runPath 'php.out') -RedirectStandardError (Join-Path $runPath 'php.err')
    $phpOut = Get-Content -LiteralPath (Join-Path $runPath 'php.out') -Raw -ErrorAction SilentlyContinue
    $phpOut = [string]$phpOut
    [regex]::Matches($phpOut, '(?m)^SQL_ERROR: .+$') | ForEach-Object { Write-Output $_.Value; $completedResults.Add($_.Value) }
    $phpMarkers = @([regex]::Matches($phpOut, '(?m)^PASS: .+$') | ForEach-Object { $_.Value })
    $phpResultMarker = [regex]::Match($phpOut, '(?m)^RESULT: PASS assertions=(\d+)$')
    $phpMarkers | ForEach-Object { $completedResults.Add($_) }
    if ($phpMarkers.Count -ne 32 -or -not $phpResultMarker.Success -or [int]$phpResultMarker.Groups[1].Value -ne 32 -or $phpResult.ExitCode -ne 0) {
        $failure = [regex]::Match($phpOut, '(?m)^FAIL: ([A-Za-z0-9 _-]+)$')
        $category = if ($failure.Success) { $failure.Groups[1].Value } else { 'FIXTURE_MARKER_OR_EXIT_MISMATCH' }
        Write-Output "FAIL: Laravel fixture $category; expected 32 explicit PASS assertions and RESULT marker."
        throw 'Laravel synthetic validation fixture did not satisfy its explicit success contract.'
    }
    $phpMarkers | ForEach-Object { Write-Output $_ }
    $raceFile = Join-Path $PSScriptRoot 'adr-validation\concurrent-race.php'
    if (-not (Test-Path -LiteralPath $raceFile -PathType Leaf)) { throw 'Concurrent synthetic fixture missing; diagnostic category: RACE_FIXTURE_MISSING.' }
    $raceProcess = Start-Process -FilePath 'php' -ArgumentList @($raceFile, 'coordinator', $root, $runPath) -WorkingDirectory $root -PassThru -NoNewWindow -RedirectStandardOutput (Join-Path $runPath 'race.out') -RedirectStandardError (Join-Path $runPath 'race.err')
    $raceDeadline = (Get-Date).AddSeconds(90)
    while (-not $raceProcess.HasExited -and (Get-Date) -lt $raceDeadline) { Start-Sleep -Milliseconds 100 }
    if (-not $raceProcess.HasExited) { Stop-Process -Id $raceProcess.Id -Force; $raceProcess.WaitForExit(); throw 'Concurrent fixture timed out; diagnostic category: RACE_FIXTURE_TIMEOUT.' }
    $raceOut = [string](Get-Content -LiteralPath (Join-Path $runPath 'race.out') -Raw -ErrorAction SilentlyContinue)
    $raceMarkers = @([regex]::Matches($raceOut, '(?m)^PASS: .+$') | ForEach-Object { $_.Value })
    $raceMarkers | ForEach-Object { $completedResults.Add($_) }
    if ($raceProcess.ExitCode -ne 0 -or $raceMarkers.Count -ne 2 -or $raceOut -notmatch '(?m)^RESULT: PASS assertions=2$') { throw 'Concurrent fixture failed; diagnostic category: RACE_FIXTURE_ASSERTION_OR_CHILD_FAILURE.' }
    $raceMarkers | ForEach-Object { Write-Output $_ }
    $queueFile = Join-Path $PSScriptRoot 'adr-validation\queue-lifecycle.php'
    if (-not (Test-Path -LiteralPath $queueFile -PathType Leaf)) { throw 'Queue lifecycle fixture missing; diagnostic category: QUEUE_FIXTURE_MISSING.' }
    $queueProcess = Start-Process -FilePath 'php' -ArgumentList @($queueFile, 'coordinator', $root, $runPath) -WorkingDirectory $root -PassThru -NoNewWindow -RedirectStandardOutput (Join-Path $runPath 'queue.out') -RedirectStandardError (Join-Path $runPath 'queue.err')
    $queueDeadline = (Get-Date).AddSeconds(90)
    while (-not $queueProcess.HasExited -and (Get-Date) -lt $queueDeadline) { Start-Sleep -Milliseconds 100 }
    $queueTimedOut = -not $queueProcess.HasExited
    if ($queueTimedOut) { Stop-Process -Id $queueProcess.Id -Force; $queueProcess.WaitForExit() }
    $queueOut = [string](Get-Content -LiteralPath (Join-Path $runPath 'queue.out') -Raw -ErrorAction SilentlyContinue)
    $queueErr = [string](Get-Content -LiteralPath (Join-Path $runPath 'queue.err') -Raw -ErrorAction SilentlyContinue)
    $queueDiagnostic = [ordered]@{ ExitCode = $queueProcess.ExitCode; TimedOut = $queueTimedOut; Stdout = @(Get-SafeQueueOutput $queueOut); Stderr = @(Get-SafeQueueOutput $queueErr); FailedAssertion = 'UNCLASSIFIED' }
    if ($queueOut -match '(?m)^FAIL: (QUEUE_[A-Z_]+)\r?$') { $queueDiagnostic.FailedAssertion = $Matches[1] }
    elseif ($queueProcess.ExitCode -eq 0 -and -not $queueTimedOut) { $queueDiagnostic.FailedAssertion = $null }
    $queueDiagnostic | ConvertTo-Json -Depth 3 | Set-Content -LiteralPath (Join-Path $root '.tools/adr-queue-last.json') -Encoding UTF8
    Write-Output ($queueDiagnostic | ConvertTo-Json -Depth 3 -Compress)
    if ($queueTimedOut) { throw 'Queue lifecycle fixture timed out; diagnostic category: QUEUE_FIXTURE_TIMEOUT.' }
    $queueMarkers = @([regex]::Matches($queueOut, '(?m)^PASS: .+$') | ForEach-Object { $_.Value })
    $queueMarkers | ForEach-Object { $completedResults.Add($_) }
    if ($queueProcess.ExitCode -ne 0 -or $queueMarkers.Count -ne 3 -or $queueOut -notmatch '(?m)^RESULT: PASS assertions=3$') { throw 'Queue lifecycle fixture failed; diagnostic category: QUEUE_FIXTURE_ASSERTION_OR_CHILD_FAILURE.' }
    $queueMarkers | ForEach-Object { Write-Output $_ }

    $dumpPath = Join-Path $runPath 'synthetic.sql'
    $dump = Invoke-ChildProcess $dumpTool @("--defaults-file=$appConfig", '--protocol=tcp', '-h', '127.0.0.1', '-P', "$port", '--single-transaction', '--skip-comments', $database)
    if ($dump.ExitCode -ne 0 -or [string]::IsNullOrWhiteSpace($dump.StdOut)) {
        $dumpCode = if ([regex]::Match([string]$dump.StdErr, '(?i)(?:error|got error)\D{0,8}(\d{3,5})').Success) { [regex]::Match([string]$dump.StdErr, '(?i)(?:error|got error)\D{0,8}(\d{3,5})').Groups[1].Value } else { 'UNCLASSIFIED' }
        $dumpOutputState = if ([string]::IsNullOrWhiteSpace([string]$dump.StdOut)) { 'EMPTY' } else { 'PRESENT' }
        throw "Owned synthetic dump failed; exit=$($dump.ExitCode); client_error_code=$dumpCode; stdout=$dumpOutputState."
    }
    [IO.File]::WriteAllText($dumpPath, $dump.StdOut, [Text.UTF8Encoding]::new($false))
    $restoreDb = $database + '_restore'
    $restoreCreate = Invoke-ChildProcess $admin @("--defaults-file=$rootConfig", '--protocol=tcp', '-h', '127.0.0.1', '-P', "$port", 'create', $restoreDb)
    if ($restoreCreate.ExitCode -ne 0) { throw 'New owned restore database creation failed; diagnostic category: RESTORE_DB_CREATE_FAILED.' }
    $restoreGrant = Invoke-ChildProcess $client @("--defaults-file=$rootConfig", '--protocol=tcp', '-h', '127.0.0.1', '-P', "$port", '--batch') "GRANT ALL PRIVILEGES ON ``$restoreDb``.* TO '$appUser'@'127.0.0.1'; FLUSH PRIVILEGES;`n"
    if ($restoreGrant.ExitCode -ne 0) { throw 'Restore database grant failed; diagnostic category: RESTORE_GRANT_FAILED.' }
    $restore = Invoke-ChildProcess $client @("--defaults-file=$appConfig", '--protocol=tcp', '-h', '127.0.0.1', '-P', "$port", $restoreDb) ([IO.File]::ReadAllText($dumpPath))
    if ($restore.ExitCode -ne 0) { throw 'Synthetic dump restore failed; diagnostic category: RESTORE_FAILED.' }
    $restorePhp = @'
<?php
require $argv[1] . '/vendor/autoload.php';
$app = require $argv[1] . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $db = Illuminate\Support\Facades\DB::connection();
    $expected = ['adrv_forward' => 4, 'adrv_forward_empty' => 0, 'adrv_parents' => 2, 'adrv_children' => 1, 'adrv_uuid_parent' => 1, 'adrv_uuid_child' => 1, 'adrv_lock_cases' => 3, 'adrv_stock_state' => 2, 'adrv_reservations' => 2, 'adrv_movements' => 2, 'adrv_outbox' => 6, 'adrv_inbox' => 3, 'adrv_consumer_effects' => 5, 'adrv_journals' => 2, 'adrv_periods' => 2, 'adrv_projection' => 1, 'adrv_search_records' => 3, 'adrv_permissions' => 1, 'jobs' => 0, 'failed_jobs' => 0, 'cache' => 2];
    foreach ($expected as $table => $count) { if ($db->table($table)->count() !== $count) { throw new RuntimeException('restored_fixture_table_mismatch'); } }
    fwrite(STDOUT, "PASS: restore preserves every named synthetic fixture table row count\n");
    $foreignKeys = Illuminate\Support\Facades\Schema::getForeignKeys('adrv_children'); $uuidForeignKeys = Illuminate\Support\Facades\Schema::getForeignKeys('adrv_uuid_child');
    $indexes = Illuminate\Support\Facades\Schema::getIndexes('adrv_children');
    if (count($foreignKeys) !== 1 || count($uuidForeignKeys) !== 1 || ! in_array('adrv_child_idempotency_uq', array_column($indexes, 'name'), true)) { throw new RuntimeException('restored_constraints_mismatch'); }
    fwrite(STDOUT, "PASS: restore preserves synthetic FK and scoped unique constraint\n");
    $stock = $db->table('adrv_stock_state')->pluck('on_hand', 'item');
    if ((int) $db->table('adrv_stock_state')->where('item', 'reserve-item')->value('available') !== 0 || (int) $db->table('adrv_stock_state')->where('item', 'reserve-item')->value('reserved') !== 4 || (int) $stock['issue-item'] !== 1 || (int) $db->table('adrv_movements')->sum('quantity') !== 8 || $db->table('adrv_outbox')->whereIn('event_type', ['reserved', 'issued', 'journal-posted'])->count() !== 5 || (int) $db->table('adrv_inbox')->where('event_id', 'fixed-event-id')->value('version') !== 1 || (int) $db->table('adrv_projection')->where('aggregate_id', 'aggregate-order')->value('version') !== 3) { throw new RuntimeException('restored_reconciliation_mismatch'); }
    fwrite(STDOUT, "PASS: restore synthetic stock/movement/outbox/inbox reconciliation\nRESULT: PASS assertions=3\n");
} catch (Throwable $failure) { fwrite(STDOUT, "FAIL: dump restore verification\nRESULT: FAIL assertions=0\n"); exit(1); }
'@
    $restoreFile = Join-Path $runPath 'restore-check.php'
    [IO.File]::WriteAllText($restoreFile, $restorePhp, [Text.UTF8Encoding]::new($false))
    $env:DB_DATABASE = $restoreDb
    $restoreCheck = Start-Process -FilePath 'php' -ArgumentList @($restoreFile, $root) -WorkingDirectory $root -Wait -PassThru -NoNewWindow -RedirectStandardOutput (Join-Path $runPath 'restore.out') -RedirectStandardError (Join-Path $runPath 'restore.err')
    $restoreOut = [string](Get-Content -LiteralPath (Join-Path $runPath 'restore.out') -Raw -ErrorAction SilentlyContinue)
    $restoreMarkers = @([regex]::Matches($restoreOut, '(?m)^PASS: .+$'))
    $restoreMarkers | ForEach-Object { $completedResults.Add($_.Value) }
    if ($restoreCheck.ExitCode -ne 0 -or @([regex]::Matches($restoreOut, '(?m)^PASS: .+$')).Count -ne 3 -or $restoreOut -notmatch '(?m)^RESULT: PASS assertions=3$') { throw 'Synthetic dump/restore success markers missing; diagnostic category: RESTORE_MARKER_MISMATCH.' }
    $restoreMarkers | ForEach-Object { Write-Output $_.Value }
    Write-Output 'RESULT: PASS assertions=40'
    $criteria = @(
        'ADR002-RACE-RESERVE: PASS synthetic only — two PHP processes synchronized at bounded barrier race reserve4 against available4; one reservation/outbox, loser no effect, winner key replay has no extra effect. Application/business acceptance NOT TESTED.',
        'ADR002-RACE-ISSUE: PASS synthetic only — two synchronized PHP processes race issue4 against reserved5; one movement/outbox and remaining/on_hand each one. Application/business acceptance NOT TESTED.',
        'ADR002-RACE-PERIOD-CLOSE: NOT TESTED — sequential order examples and independent-connection uncommitted visibility PASS; competing post/close lock interleavings and exact FIN_PERIOD_CLOSED concurrency remain untested.',
        'ADR002-MONEY-DECIMAL: PASS candidate DECIMAL(20,6) storage/sum/overflow only; Finance precision/scale and OPEN-03 rounding policy NOT TESTED.',
        'ADR002-UNIQUE-SCOPE-FK: PASS synthetic scoped unique and cross-entity FK negatives; concurrent source/idempotency tuple and owner business-key policy NOT TESTED.',
        'ADR002-IDENTIFIER-COMPAT: PASS synthetic ULID and UUIDv7 PK/FK database round trips plus ASCII <=60 synthetic index/constraint names; Laravel model/route/serialization/relation and target-driver compatibility NOT TESTED.',
        'ADR002-MIGRATION-FORWARD: PASS synthetic schema/backfill only — empty-built and prior-upgraded column/index metadata compared; interrupted resumable backfill checked; generated DDL and recovery timing captured. Actual project migration acceptance NOT TESTED.',
        'ADR002-BACKUP-RESTORE: PASS synthetic restore only — all listed fixture table counts, FK/unique metadata, and stock/movement/outbox/inbox/projection reconciliation checked. Encryption, operational permissions, RPO/RTO and operational restore NOT TESTED.',
        'ADR003-34 source-of-truth/cache/search: NOT TESTED — no application behavior tested; only local synthetic scopes and cache primitives.',
        'ADR003-35 atomic outbox/inbox/reconciliation: PASS synthetic only — domain journal and outbox rollback together; failed consumer effect rolls back inbox; fixed event replay deduplicates; v3 then v2 keeps projection at v3 without re-effect. Application event integration NOT TESTED.',
        'ADR003-36 execution-time authorization: PASS synthetic only — execution query denies revoked permission despite stale positive cache; real Laravel job authorization NOT TESTED.',
        'ADR003-37 search/count/export scope and masking: PASS synthetic SQL fixture — scope, hidden-row exclusion, count, export masking checked; application endpoints and revoked access at execution NOT TESTED.',
        'ADR003-38 bounded retry/DLQ/authorized replay: PASS synthetic Laravel database worker failed_jobs and authorized same-event-id replay; provider rejection/timeout/ambiguous success and network reconciliation NOT TESTED.',
        'ADR003-39 worker lifecycle/drain: PASS synthetic real queue:work child killed during leased job, expired lease redelivered, and stop-when-empty drain; production shutdown/deployment semantics NOT TESTED.',
        'ADR003-47 race: inventory synthetic concurrent independent-process races PASS; enqueue/publish contention and concurrent projection copies NOT TESTED.',
        'ADR003-47 duplicate/out-of-order events: PASS synthetic fixed-id dedup and v3-before-v2 projection reconciliation; concurrent application consumer contract NOT TESTED.',
        'ADR003-47 authorization changed/revoked before job execution: NOT TESTED — no queued job authorization boundary exercised.',
        'ADR003-47 crash before/after commit and publish-before-inbox: NOT TESTED — no kill/crash injection or durable publish lifecycle.',
        'ADR003-47 worker kill during job and stop-when-empty synthetic drain PASS; DB/Redis/search outage and graceful deployment shutdown NOT TESTED.',
        'ADR003-47 bounded retry, failed_jobs/DLQ, authorized same-event-id replay, and v3-then-v2 projection version handling PASS synthetically; projection rebuild and ambiguous-state reconciliation NOT TESTED.',,
        'ADR003-47 provider rejection, timeout, and success with lost response: NOT TESTED — no network/provider call or local provider simulator.',
        'ADR003-47 synthetic scoped search/count/export mask and hidden-row/revoked-scope checks PASS; deletion leakage and application endpoint authorization NOT TESTED.',
        'ADR003-47 agreed real-data workload/performance: NOT TESTED — no owner-approved workload or performance targets.',
        'OPEN-11 capacity/workload targets: NOT TESTED — no owner-approved workload, throughput, availability, or RPO/RTO targets.',
        'APPROVALS: ADR-002 Finance review and operations restore input missing; ADR-003 IT approval, independent Security signoff, and Legal/Operations signoffs missing; OPEN-10/11 policy decisions remain open.',
        'OPEN-10 retention/replay/restore policy: NOT TESTED — no retention schedule, legal deletion, or replay governance approval.'
    )
    $criteria | ForEach-Object { Write-Output $_ }
    $batchDate = Get-Date -Format 'yyyy-MM-dd'
    $migrationSummaryPath = Join-Path $runPath 'migration-summary.txt'
    if (-not (Test-Path -LiteralPath $migrationSummaryPath -PathType Leaf)) { throw 'Synthetic migration DDL/timing summary missing; diagnostic category: MIGRATION_EVIDENCE_MISSING.' }
    $migrationSummary = (Get-Content -LiteralPath $migrationSummaryPath -Raw).Trim()
    $evidenceAppend = @('## ' + $batchDate + ' - bounded synthetic validation batch', '', 'Runner assertions: 32 Laravel fixture assertions, two independent-process races, three Laravel queue worker assertions, and three isolated restore checks. Synthetic fixture results do not establish application or ADR acceptance.', '', 'Per-criterion status:')
    $evidenceAppend += $criteria
    $evidenceAppend += @('', 'Synthetic DDL/timing record (no credentials): ' + $migrationSummary, '', 'Synthetic state-transition cases include reserve/replay/rejection, issue/movement/outbox, atomic rollback, fixed-event-id inbox/effect dedup, version-3 then version-2 delivery, scope masking/revocation, failed-job replay, and queue lease redelivery. Period-close competing interleavings remain NOT TESTED.', '', 'Policy blockers remain: Finance OPEN-03 precision/scale/rounding; business cross-entity uniqueness owner policy; OPEN-10 retention/replay/restore and Legal/Operations signoff; OPEN-11 workload and RPO/RTO targets; Engineering/IT/Security approvals and independent Security signoff. ADR-002 approval record still requires Finance review and operational restore input; ADR-003 still requires IT, Security, Legal/Operations approvals as recorded in its decision-approval document.', '', 'Synthetic fixture success is not implementation acceptance. ADR statuses and task states are unchanged.', '')
    Add-Content -LiteralPath (Join-Path $root 'docs/governance/evidence/ADR-002-003-environment-check.md') -Value $evidenceAppend -Encoding UTF8
    Write-Output 'AGGREGATE: SYNTHETIC_FIXTURES_PASS; ADR_ACCEPTANCE=NOT_ESTABLISHED; POLICY_GATES=NOT_TESTED'
    Write-Output "ELAPSED_SECONDS: $([math]::Round(((Get-Date) - $started).TotalSeconds, 1))"
    Write-Output 'SCOPE: bounded synthetic fixture batch only; not production business acceptance or ADR sign-off.'
}
catch {
    $failureCategory = 'VALIDATION_FAILED'
    if ([regex]::Match($_.Exception.Message, 'diagnostic category: ([A-Z0-9_]+)').Success) { $failureCategory = [regex]::Match($_.Exception.Message, 'diagnostic category: ([A-Z0-9_]+)').Groups[1].Value }
    $failureDate = Get-Date -Format 'yyyy-MM-dd'
    $failureProgress = [string]::Join("`r`n", $completedResults)
    $failureLine = "`r`n## $failureDate - bounded synthetic validation failure`r`n`r`nFAIL: $failureCategory. Raw output and credentials are not included. The failed run remains NOT ACCEPTED.`r`nCompleted sanitized results:`r`n$failureProgress`r`n"
    try { Add-Content -LiteralPath (Join-Path $root 'docs/governance/evidence/ADR-002-003-environment-check.md') -Value $failureLine -Encoding UTF8 } catch { Write-Warning 'Could not append sanitized failure category to existing evidence artifact.' }
    Write-Output "AGGREGATE: FAIL; category=$failureCategory; ADR_ACCEPTANCE=NOT_ESTABLISHED"
    throw
}
finally {
    foreach ($name in $oldEnvironment.Keys) { [Environment]::SetEnvironmentVariable($name, $oldEnvironment[$name], 'Process') }
    if ($queueProcess -and -not $queueProcess.HasExited) { Stop-Process -Id $queueProcess.Id -Force; $queueProcess.WaitForExit() }
    if ($raceProcess -and -not $raceProcess.HasExited) { Stop-Process -Id $raceProcess.Id -Force; $raceProcess.WaitForExit() }
    if ($process -and -not $process.HasExited) { Stop-Process -Id $process.Id -Force; $process.WaitForExit() }
    if ($created) {
        $absoluteRunPath = [IO.Path]::GetFullPath($runPath)
        $actualMarker = if (Test-Path -LiteralPath $marker -PathType Leaf) { (Get-Content -LiteralPath $marker -Raw).TrimStart([char]0xFEFF).TrimEnd("`r", "`n") } else { '' }
        if ($absoluteRunPath -eq $runPath -and $absoluteRunPath.StartsWith($validationRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase) -and $actualMarker -eq $owner) {
            Remove-Item -LiteralPath $absoluteRunPath -Recurse -Force
            Write-Output 'CLEANUP: removed only this run-owned marked validation directory and stopped its authenticated server process.'
        } else { Write-Warning 'Cleanup refused: ownership marker/path did not match; left run directory untouched.' }
    }
}
