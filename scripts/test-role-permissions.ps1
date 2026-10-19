[CmdletBinding()]
param([string]$MariaDbBin = (Join-Path $PSScriptRoot '../.local/windows-qa/mariadb-11.4.13-winx64/bin'))

$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$bin = [IO.Path]::GetFullPath($MariaDbBin)
$initialize = Join-Path $bin 'mysql_install_db.exe'
$server = Join-Path $bin 'mysqld.exe'
$client = Join-Path $bin 'mysql.exe'
$admin = Join-Path $bin 'mysqladmin.exe'
foreach ($path in @($initialize, $server, $client, $admin, (Join-Path $root 'vendor\bin\phpunit'))) {
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Required role-permission test binary missing: $path" }
}

$tempRoot = [IO.Path]::GetFullPath((Join-Path $env:TEMP 'arasya-ui-closure'))
$runId = [Guid]::NewGuid().ToString('N')
$runPath = [IO.Path]::GetFullPath((Join-Path $tempRoot $runId))
if (-not $runPath.StartsWith($tempRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Refusing unexpected ui-closure test path.' }
if (Test-Path -LiteralPath $runPath) { throw 'Random ui-closure test path already exists; refusing to reuse it.' }

function New-RandomHex([int]$Bytes = 24) {
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
    if (-not $child.Start()) { throw 'Could not start an owned ui-closure test process.' }
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

function Get-SafeOutput([string]$Text, [string[]]$Secrets) {
    foreach ($secret in $Secrets) {
        if (-not [string]::IsNullOrEmpty($secret)) { $Text = $Text.Replace($secret, '[REDACTED]') }
    }
    return $Text
}

$marker = Join-Path $runPath '.owner'
$started = $false
$serverProcess = $null
$oldEnvironment = @{}
$rootPassword = New-RandomHex
$appPassword = New-RandomHex
$appUser = 'help_' + (New-RandomHex 8).ToLowerInvariant()
$database = 'help_' + $runId.Substring(0, 20)
$port = 0
try {
    New-Item -ItemType Directory -Path $tempRoot -Force | Out-Null
    New-Item -ItemType Directory -Path $runPath -Force:$false | Out-Null
    $started = $true
    Set-Content -LiteralPath $marker -Value "A1-CORE-018:$runId" -Encoding UTF8 -NoNewline
    $runAcl = Get-Acl -LiteralPath $runPath
    $runAcl.SetAccessRuleProtection($true, $false)
    $runAcl.SetOwner([Security.Principal.WindowsIdentity]::GetCurrent().User)
    $runRule = [Security.AccessControl.FileSystemAccessRule]::new([Security.Principal.WindowsIdentity]::GetCurrent().Name, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
    [void]$runAcl.AddAccessRule($runRule)
    Set-Acl -LiteralPath $runPath -AclObject $runAcl

    $dataPath = [IO.Path]::GetFullPath((Join-Path $runPath 'data'))
    New-Item -ItemType Directory -Path $dataPath -Force:$false | Out-Null
    $rootConfig = Join-Path $runPath 'root-client.cnf'
    $appConfig = Join-Path $runPath 'app-client.cnf'
    Write-PrivateFile $rootConfig "[client]`r`nuser=root`r`npassword=$rootPassword`r`nhost=127.0.0.1`r`nprotocol=tcp`r`n"
    Write-PrivateFile $appConfig "[client]`r`nuser=$appUser`r`npassword=$appPassword`r`nhost=127.0.0.1`r`nprotocol=tcp`r`n"

    $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
    $listener.Start()
    $port = ([Net.IPEndPoint]$listener.LocalEndpoint).Port
    $listener.Stop()

    $initializeResult = Invoke-ChildProcess $initialize @("--datadir=$dataPath", "--password=$rootPassword", '--skip-networking', "--port=$port")
    if ($initializeResult.ExitCode -ne 0) { throw 'Disposable MariaDB initialization failed; diagnostic category: INIT_FAILED.' }

    $serverArgs = @('--no-defaults', "--basedir=$bin\..", "--datadir=$dataPath", "--port=$port", '--bind-address=127.0.0.1', '--console', "--pid-file=$(Join-Path $runPath 'mysqld.pid')", "--log-error=$(Join-Path $runPath 'mysqld.err')")
    $serverProcess = Start-Process -FilePath $server -ArgumentList $serverArgs -WorkingDirectory $bin -PassThru -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runPath 'server.out') -RedirectStandardError (Join-Path $runPath 'server.err')
    $ready = $false
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        if ($serverProcess.HasExited) { throw 'Disposable MariaDB exited during startup; diagnostic category: SERVER_EXITED.' }
        $ping = Invoke-ChildProcess $admin @("--defaults-file=$rootConfig", '--protocol=tcp', '-h', '127.0.0.1', '-P', "$port", 'ping')
        if ($ping.ExitCode -eq 0) { $ready = $true; break }
        Start-Sleep -Milliseconds 500
    }
    if (-not $ready) { throw 'Authenticated ping to the owned loopback MariaDB timed out.' }

    $createDb = Invoke-ChildProcess $admin @("--defaults-file=$rootConfig", '--protocol=tcp', '-h', '127.0.0.1', '-P', "$port", 'create', $database)
    if ($createDb.ExitCode -ne 0) { throw 'Disposable schema creation failed; diagnostic category: DB_CREATE_FAILED.' }
    $accountSql = "CREATE USER '$appUser'@'127.0.0.1' IDENTIFIED BY '$appPassword'; GRANT ALL PRIVILEGES ON ``$database``.* TO '$appUser'@'127.0.0.1'; FLUSH PRIVILEGES;`n"
    $account = Invoke-ChildProcess $client @("--defaults-file=$rootConfig", '--protocol=tcp', '-h', '127.0.0.1', '-P', "$port", '--batch') $accountSql
    if ($account.ExitCode -ne 0) { throw 'Disposable schema account setup failed; diagnostic category: ACCOUNT_SETUP_FAILED.' }

    $envNames = @(Get-ChildItem Env: | Where-Object { $_.Name -match '^(DB_|DATABASE_URL$)' } | ForEach-Object { $_.Name }) + @('APP_ENV', 'APP_KEY', 'APP_DEBUG', 'APP_URL', 'APP_BASE_PATH', 'LARAVEL_STORAGE_PATH', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE', 'ARASYA_SYNTHETIC_SALES', 'ARASYA_CLOSURE_PRIVATE', 'ARASYA_CLOSURE_EVIDENCE', 'ARASYA_CLOSURE_ORIGIN', 'APP_CONFIG_CACHE', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_URL', 'DATABASE_URL', 'CACHE_STORE', 'SESSION_DRIVER', 'QUEUE_CONNECTION', 'MYSQL_ATTR_SSL_CA', 'TUTORIAL_HELP_EXPECTED_DATADIR') | Select-Object -Unique
    foreach ($name in $envNames) {
        $oldEnvironment[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
        if ($name -match '^(DB_|DATABASE_URL$)' -or $name -eq 'MYSQL_ATTR_SSL_CA') { [Environment]::SetEnvironmentVariable($name, '', 'Process') }
    }
    $env:APP_BASE_PATH = $root
    $env:APP_ENV = 'testing'
    $env:APP_CONFIG_CACHE = Join-Path $runPath 'config-cache-must-not-exist.php'
    $env:DB_CONNECTION = 'mariadb'
    $env:DB_HOST = '127.0.0.1'
    $env:DB_PORT = "$port"
    $env:DB_DATABASE = $database
    $env:DB_USERNAME = $appUser
    $env:DB_PASSWORD = $appPassword
    $env:DB_URL = ''
    $env:DATABASE_URL = ''
    $env:CACHE_STORE = 'array'
    $env:SESSION_DRIVER = 'array'
    $env:QUEUE_CONNECTION = 'sync'
    $env:TUTORIAL_HELP_EXPECTED_DATADIR = $dataPath

    $guardPhp = @'
<?php
require $argv[1] . '/vendor/autoload.php';
$app = require $argv[1] . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $row = Illuminate\Support\Facades\DB::connection('mariadb')->selectOne('select version() as version, @@port as port, @@datadir as datadir, database() as schema_name');
    $expectedData = realpath((string) getenv('TUTORIAL_HELP_EXPECTED_DATADIR'));
    $actualData = realpath((string) $row->datadir);
    $ok = app()->environment('testing')
        && (string) config('database.default') === 'mariadb'
        && str_starts_with((string) $row->version, '11.4.') && str_contains(strtolower((string) $row->version), 'mariadb')
        && (int) $row->port === (int) getenv('DB_PORT')
        && (string) $row->schema_name === (string) getenv('DB_DATABASE')
        && $expectedData !== false && $actualData !== false && $expectedData === $actualData
        && ! is_file((string) getenv('APP_CONFIG_CACHE'));
    if (! $ok) {
        fwrite(STDOUT, "FAIL: owned MariaDB/configuration identity guard\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: APP_ENV testing, uncached config, and owned loopback MariaDB/schema/datadir verified before migrations\n");
} catch (Throwable) {
    fwrite(STDOUT, "FAIL: owned MariaDB/configuration identity guard\n");
    exit(1);
} finally {
    Illuminate\Support\Facades\DB::disconnect();
}
'@
    $guardFile = Join-Path $runPath 'guard.php'
    [IO.File]::WriteAllText($guardFile, $guardPhp, [Text.UTF8Encoding]::new($false))
    $guardProcess = Start-Process -FilePath 'php' -ArgumentList @($guardFile, $root) -WorkingDirectory $root -Wait -PassThru -NoNewWindow -RedirectStandardOutput (Join-Path $runPath 'guard.out') -RedirectStandardError (Join-Path $runPath 'guard.err')
    $guardOutput = [string](Get-Content -LiteralPath (Join-Path $runPath 'guard.out') -Raw -ErrorAction SilentlyContinue)
    if ($guardProcess.ExitCode -ne 0 -or $guardOutput -notmatch '(?m)^PASS: APP_ENV testing, uncached config, and owned loopback MariaDB/schema/datadir verified before migrations\r?$') {
        Write-Output 'BLOCKED: pre-migration database identity guard failed; PHPUnit migrations were not run.'
        exit 2
    }
    Write-Output $guardOutput.Trim()

    $env:LARAVEL_STORAGE_PATH = Join-Path $runPath 'storage'
    foreach ($folder in @('app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs')) { New-Item -ItemType Directory -Path (Join-Path $env:LARAVEL_STORAGE_PATH $folder) -Force | Out-Null }
    $env:SESSION_DRIVER = 'file'
    $env:APP_KEY = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='
    $env:APP_DEBUG = 'false'
    $env:ARASYA_SYNTHETIC_SALES = 'true'
    $env:ARASYA_CLOSURE_PRIVATE = Join-Path $runPath 'fixture.json'
    $env:ARASYA_CLOSURE_EVIDENCE = Join-Path $root ('.local/role-permissions-http-' + $runId)
    New-Item -ItemType Directory -Path (Join-Path $root ('.local/ui-closure-cache-' + $runId)) -Force | Out-Null
    $env:APP_SERVICES_CACHE = ('.local/ui-closure-cache-' + $runId + '/services.php')
    $env:APP_PACKAGES_CACHE = ('.local/ui-closure-cache-' + $runId + '/packages.php')
    $env:APP_ROUTES_CACHE = ('.local/ui-closure-cache-' + $runId + '/routes.php')
    $env:APP_EVENTS_CACHE = ('.local/ui-closure-cache-' + $runId + '/events.php')
    $httpListener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
    $httpListener.Start(); $httpPort = ([Net.IPEndPoint]$httpListener.LocalEndpoint).Port; $httpListener.Stop()
    $env:ARASYA_CLOSURE_ORIGIN = 'http://127.0.0.1:' + $httpPort
    $env:APP_URL = $env:ARASYA_CLOSURE_ORIGIN
    $storageRouter = Join-Path $runPath 'storage-router.php'
    $storageRouterPhp = @'
<?php
$storage = getenv('LARAVEL_STORAGE_PATH');
if (! is_string($storage) || ! is_dir($storage)) {
    throw new RuntimeException('Owned storage directory is required.');
}
require dirname(getcwd()).'/vendor/autoload.php';
$_ENV['LARAVEL_STORAGE_PATH'] = $storage;
$_SERVER['LARAVEL_STORAGE_PATH'] = $storage;
return require dirname(getcwd()).'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
'@
    [IO.File]::WriteAllText($storageRouter, $storageRouterPhp, [Text.UTF8Encoding]::new($false))
    php (Join-Path $root 'tests/Browser/role-permissions-fixtures.php')
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $env:ARASYA_CLOSURE_PRIVATE -PathType Leaf)) { throw 'Isolated synthetic seed failed' }
    $httpProcess = $null
    try {
        $httpProcess = Start-Process -FilePath 'php' -ArgumentList @('-S', "127.0.0.1:$httpPort", '-t', (Join-Path $root 'public'), $storageRouter) -WorkingDirectory (Join-Path $root 'public') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runPath 'http.out') -RedirectStandardError (Join-Path $runPath 'http.err')
        node (Join-Path $root 'node_modules/@playwright/test/cli.js') test --config (Join-Path $root 'playwright.role-permissions.config.ts')
        if ($LASTEXITCODE -ne 0) { throw 'HTTP workflow verification failed' }
        php (Join-Path $root 'tests/Browser/role-permissions-verify.php')
        if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath (Join-Path $env:ARASYA_CLOSURE_EVIDENCE 'database-audit.json'))) { throw 'Database audit verification failed' }
        Write-Output ('Evidence: ' + $env:ARASYA_CLOSURE_EVIDENCE)
    } finally { if ($null -ne $httpProcess -and -not $httpProcess.HasExited) { $httpProcess.Kill(); $httpProcess.WaitForExit() } }
} finally {
    foreach ($name in $oldEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($name, $oldEnvironment[$name], 'Process')
    }

    if ($null -ne $serverProcess -and -not $serverProcess.HasExited) {
        $shutdown = Invoke-ChildProcess $admin @("--defaults-file=$rootConfig", '--protocol=tcp', '-h', '127.0.0.1', '-P', "$port", 'shutdown')
        for ($attempt = 0; $attempt -lt 20 -and -not $serverProcess.HasExited; $attempt++) { Start-Sleep -Milliseconds 250 }
        if (-not $serverProcess.HasExited) {
            try { $serverProcess.Kill(); $serverProcess.WaitForExit() } catch { Write-Warning 'Could not stop the owned temporary MariaDB process cleanly.' }
        }
    }

    if ($started -and (Test-Path -LiteralPath $runPath -PathType Container)) {
        $resolvedRunPath = [IO.Path]::GetFullPath($runPath)
        $resolvedTempRoot = [IO.Path]::GetFullPath($tempRoot)
        $ownerValue = if (Test-Path -LiteralPath $marker -PathType Leaf) { [IO.File]::ReadAllText($marker) } else { '' }
        if ($resolvedRunPath.StartsWith($resolvedTempRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase) -and $ownerValue -eq "A1-CORE-018:$runId") {
            Remove-Item -LiteralPath $resolvedRunPath -Recurse -Force
        } else {
            Write-Warning 'Retained the temporary directory because its ownership marker/path did not match.'
        }
    }
}
