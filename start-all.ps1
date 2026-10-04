param([switch]$CheckOnly, [string]$LaragonRoot = 'C:\laragon')

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$logDir = Join-Path $root 'storage\logs\dev-stack'

function Find-Executable([string]$Name, [string]$Pattern) {
    $command = Get-Command $Name -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($command) { return $command.Source }
    $candidate = Get-ChildItem -Path (Join-Path $LaragonRoot $Pattern) -File -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending | Select-Object -First 1
    if ($candidate) { return $candidate.FullName }
    throw "$Name tidak ditemukan. Pasang program atau tambahkan ke PATH."
}

function Read-Setting([string[]]$Paths, [string]$Name, [string]$Default) {
    foreach ($path in $Paths) {
        if (-not (Test-Path -LiteralPath $path)) { continue }
        foreach ($line in Get-Content -LiteralPath $path) {
            if ($line -match ('^\s*' + [regex]::Escape($Name) + '\s*=\s*(.*)$')) {
                return $Matches[1].Trim().Trim('"', "'")
            }
        }
    }
    return $Default
}

function Test-Listening([string]$HostName, [int]$Port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $pending = $client.BeginConnect($HostName, $Port, $null, $null)
        if (-not $pending.AsyncWaitHandle.WaitOne(400, $false)) { return $false }
        $client.EndConnect($pending)
        return $true
    } catch { return $false } finally { $client.Dispose() }
}

function Start-Background([string]$Name, [string]$File, [string[]]$Arguments, [string]$WorkDir) {
    $quoted = $Arguments | ForEach-Object { '"' + $_ + '"' }
    return Start-Process -FilePath $File -ArgumentList $quoted -WorkingDirectory $WorkDir -WindowStyle Hidden `
        -RedirectStandardOutput (Join-Path $logDir "$Name.log") `
        -RedirectStandardError (Join-Path $logDir "$Name.error.log") -PassThru
}

function Ensure-Service([string]$Name, [string]$HostName, [int]$Port, [scriptblock]$Launch) {
    if (Test-Listening $HostName $Port) {
        Write-Host "AKTIF  $Name ${HostName}:$Port" -ForegroundColor DarkYellow
        return
    }
    if ($CheckOnly) { Write-Host "MATI   $Name ${HostName}:$Port"; return }
    $process = & $Launch
    $deadline = (Get-Date).AddSeconds(30)
    do {
        if ($process.HasExited) { throw "$Name berhenti. Periksa $logDir\$Name.error.log dan $Name.log" }
        if (Test-Listening $HostName $Port) {
            Write-Host "SIAP   $Name ${HostName}:$Port (PID $($process.Id))" -ForegroundColor Green
            return
        }
        Start-Sleep -Milliseconds 300
    } while ((Get-Date) -lt $deadline)
    throw "$Name belum siap setelah 30 detik. Periksa log di $logDir."
}

try {
    $php = Find-Executable 'php.exe' 'bin\php\*\php.exe'
    $node = Find-Executable 'node.exe' 'bin\nodejs\*\node.exe'
    $envPath = Join-Path $root '.env'
    $chatEnv = Join-Path $root 'chat-server\.env'
    foreach ($required in @('.env', 'vendor\autoload.php', 'node_modules\vite\bin\vite.js', 'chat-server\node_modules\express\package.json')) {
        if (-not (Test-Path -LiteralPath (Join-Path $root $required))) {
            throw "$required belum tersedia. Siapkan .env dan jalankan composer install, npm install, serta npm install di chat-server."
        }
    }
    $dbHost = Read-Setting @($envPath) 'DB_HOST' '127.0.0.1'
    $dbPort = [int](Read-Setting @($envPath) 'DB_PORT' '3306')
    $redisHost = Read-Setting @($envPath) 'REDIS_HOST' '127.0.0.1'
    $redisPort = [int](Read-Setting @($envPath) 'REDIS_PORT' '6379')
    $chatPort = [int](Read-Setting @($chatEnv, $envPath) 'PORT' '3001')
    if (-not $CheckOnly) { New-Item -ItemType Directory -Path $logDir -Force | Out-Null }
    Ensure-Service 'MySQL' $dbHost $dbPort {
        if ($dbHost -notin @('localhost', '127.0.0.1')) { throw 'Database remote tidak tersedia.' }
        $mysql = Find-Executable 'mysqld.exe' 'bin\mysql\*\bin\mysqld.exe'
        $ini = Join-Path (Split-Path (Split-Path $mysql -Parent) -Parent) 'my.ini'
        if (-not (Test-Path -LiteralPath $ini)) { throw "Konfigurasi MySQL tidak ditemukan: $ini" }
        Start-Background 'MySQL' $mysql @("--defaults-file=$ini", "--port=$dbPort") $root
    }
    Ensure-Service 'Redis' $redisHost $redisPort {
        if ($redisHost -notin @('localhost', '127.0.0.1')) { throw 'Redis remote tidak tersedia.' }
        $redis = Find-Executable 'redis-server.exe' 'bin\redis\*\redis-server.exe'
        $conf = Join-Path (Split-Path $redis -Parent) 'redis.windows.conf'
        if (-not (Test-Path -LiteralPath $conf)) { throw "Konfigurasi Redis tidak ditemukan: $conf" }
        Start-Background 'Redis' $redis @($conf, '--port', "$redisPort") (Split-Path $redis -Parent)
    }
    $artisan = Join-Path $root 'artisan'
    Ensure-Service 'Laravel' '127.0.0.1' 8000 {
        Start-Background 'Laravel' $php @($artisan, 'serve', '--host=127.0.0.1', '--port=8000', '--tries=1') $root
    }
    $queue = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object {
        $_.CommandLine -and $_.CommandLine.Contains($artisan) -and $_.CommandLine -match 'queue:(listen|work)'
    }
    if ($queue) { Write-Host 'AKTIF  Queue' -ForegroundColor DarkYellow }
    elseif ($CheckOnly) { Write-Host 'MATI   Queue' }
    else {
        $worker = Start-Background 'Queue' $php @($artisan, 'queue:listen', '--tries=1') $root
        Start-Sleep -Seconds 2
        if ($worker.HasExited) { throw "Queue berhenti. Periksa $logDir\Queue.error.log" }
        Write-Host "SIAP   Queue (PID $($worker.Id))" -ForegroundColor Green
    }
    Ensure-Service 'Chat' '127.0.0.1' $chatPort {
        Start-Background 'Chat' $node @((Join-Path $root 'chat-server\server.js')) (Join-Path $root 'chat-server')
    }
    Ensure-Service 'Vite' '127.0.0.1' 5173 {
        Start-Background 'Vite' $node @((Join-Path $root 'node_modules\vite\bin\vite.js'), '--host', '127.0.0.1', '--port', '5173', '--strictPort') $root
    }
    Write-Host 'App: http://127.0.0.1:8000' -ForegroundColor Cyan
    Write-Host "Log: $logDir"
} catch {
    Write-Error $_ -ErrorAction Continue
    exit 1
}
