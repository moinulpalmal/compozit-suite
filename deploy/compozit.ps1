<#
.SYNOPSIS
    Compozit Suite process supervisor.

.DESCRIPTION
    Starts, stops and reports on the application's own long-running processes:
    the queue worker(s) and the scheduler.

    It does NOT start Apache or MySQL. Those belong to Laragon or XAMPP, and two
    managers fighting over one service is worse than none. It does check they are
    up, and says so, because the application cannot work without them.

    Each managed component runs as a small PowerShell supervising loop that
    relaunches its php.exe child whenever that child exits -- on a crash, or on
    the hourly --max-time recycle. The loop's PID is recorded under deploy/run/.

.PARAMETER Action
    menu           Interactive console. The entry point for an operator.
    start          Start every managed component.
    stop           Stop them, letting in-flight jobs finish first.
    restart        stop, then start. Use after every deployment.
    status         Report on everything, managed or not. Exit code 0 = healthy.
    install-task   Register a Scheduled Task so `start` runs at boot. Needs admin.
    uninstall-task Remove that task. Needs admin.
    logs           Tail the managed components' logs.

.EXAMPLE
    .\manage.bat            # interactive console
    .\start.bat             # or drive a single verb directly
    .\compozit.ps1 status
#>
[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [ValidateSet('menu', 'start', 'stop', 'restart', 'status', 'install-task', 'uninstall-task', 'logs', 'help')]
    [string]$Action = 'help',

    [int]$Lines = 40
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$Script:Root      = $PSScriptRoot
$Script:RunDir    = Join-Path $Root 'run'
$Script:StopFlag  = Join-Path $RunDir 'stopping.flag'
$Script:TaskName  = 'Compozit Suite'

# --------------------------------------------------------------------------
#  Output helpers
# --------------------------------------------------------------------------

function Write-Head($text) { Write-Host ''; Write-Host $text -ForegroundColor Cyan; Write-Host ('-' * 62) -ForegroundColor DarkGray }
function Write-Ok  ($text) { Write-Host '  [ OK ]   ' -ForegroundColor Green   -NoNewline; Write-Host $text }
function Write-Bad ($text) { Write-Host '  [FAIL]   ' -ForegroundColor Red     -NoNewline; Write-Host $text }
function Write-Warn($text) { Write-Host '  [WARN]   ' -ForegroundColor Yellow  -NoNewline; Write-Host $text }
function Write-Info($text) { Write-Host '  [....]   ' -ForegroundColor DarkGray -NoNewline; Write-Host $text }

# --------------------------------------------------------------------------
#  Configuration
# --------------------------------------------------------------------------

function Get-Config {
    $configPath = Join-Path $Script:Root 'compozit.config.ps1'
    if (-not (Test-Path $configPath)) {
        throw "Configuration not found at $configPath"
    }

    $cfg = & $configPath

    if (-not (Test-Path (Join-Path $cfg.AppPath 'artisan'))) {
        throw "AppPath '$($cfg.AppPath)' does not contain artisan. Fix AppPath in compozit.config.ps1."
    }

    # php.exe -- explicit, else PATH.
    if (-not $cfg.PhpExe) {
        $found = Get-Command php -ErrorAction SilentlyContinue
        if (-not $found) {
            throw 'php is not on PATH and PhpExe is not set in compozit.config.ps1.'
        }
        $cfg.PhpExe = $found.Source
    }
    if (-not (Test-Path $cfg.PhpExe)) {
        throw "PhpExe '$($cfg.PhpExe)' does not exist."
    }

    # APP_URL -- explicit, else read it out of .env.
    if (-not $cfg.AppUrl) {
        $envFile = Join-Path $cfg.AppPath '.env'
        if (Test-Path $envFile) {
            $line = Select-String -Path $envFile -Pattern '^\s*APP_URL\s*=\s*(.+)$' -ErrorAction SilentlyContinue |
                Select-Object -First 1
            if ($line) {
                $cfg.AppUrl = $line.Matches[0].Groups[1].Value.Trim().Trim('"').Trim("'").TrimEnd('/')
            }
        }
    }
    if (-not $cfg.AppUrl) { $cfg.AppUrl = 'http://localhost' }

    return $cfg
}

function Get-EnvValue($cfg, $key, $default) {
    $envFile = Join-Path $cfg.AppPath '.env'
    if (-not (Test-Path $envFile)) { return $default }
    $line = Select-String -Path $envFile -Pattern "^\s*$key\s*=\s*(.*)$" -ErrorAction SilentlyContinue |
        Select-Object -First 1
    if (-not $line) { return $default }
    $v = $line.Matches[0].Groups[1].Value.Trim().Trim('"').Trim("'")
    if ([string]::IsNullOrWhiteSpace($v)) { return $default }
    return $v
}

# --------------------------------------------------------------------------
#  Component registry
# --------------------------------------------------------------------------

<#
    `Graceful` says whether a component gets the drain-then-wait treatment on
    stop.

    Only queue workers do. `queue:restart` is the signal that makes a worker
    finish the job in hand and exit, and killing one mid-job leaves that job
    reserved until it times out. `schedule:work` holds nothing -- it sleeps and
    fires due tasks -- and it ignores `queue:restart` entirely, so waiting on it
    just burns the whole grace period before killing it anyway. Measured: it
    made `stop` take 120 seconds instead of two.
#>
function Get-Components($cfg) {
    $list = @()
    for ($i = 1; $i -le $cfg.QueueWorkers; $i++) {
        $list += [pscustomobject]@{
            Name        = "queue-$i"
            Description = "Queue worker $i"
            Arguments   = @('artisan', 'queue:work') + $cfg.QueueArgs
            Graceful    = $true
        }
    }
    if ($cfg.RunScheduler) {
        $list += [pscustomobject]@{
            Name        = 'scheduler'
            Description = 'Task scheduler'
            Arguments   = @('artisan', 'schedule:work')
            Graceful    = $false
        }
    }
    return $list
}

function Get-PidPath($name) { Join-Path $Script:RunDir "$name.pid" }

function Get-LoopPath($name) { Join-Path $Script:RunDir "$name.loop.ps1" }

<#
    True only if the PID is live AND is running this component's own loop
    script. PIDs are recycled by Windows -- without the command-line check,
    `stop` could eventually kill an unrelated process that happened to inherit
    the number.
#>
function Test-ManagedProcess($name) {
    $pidPath = Get-PidPath $name
    if (-not (Test-Path $pidPath)) { return $null }

    $recorded = (Get-Content $pidPath -Raw).Trim()
    if (-not ($recorded -match '^\d+$')) { return $null }

    $proc = Get-CimInstance Win32_Process -Filter "ProcessId = $recorded" -ErrorAction SilentlyContinue
    if (-not $proc) { return $null }
    if ($proc.CommandLine -notmatch [regex]::Escape("$name.loop.ps1")) { return $null }

    return [int]$recorded
}

# Every child, including the conhost.exe Windows attaches. Used by `stop`,
# which wants the whole tree gone.
function Get-ChildPids($parentPid) {
    Get-CimInstance Win32_Process -Filter "ParentProcessId = $parentPid" -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty ProcessId
}

# Only the php.exe doing the actual work. Used by `status`, where listing
# conhost.exe alongside the worker would just look like a second worker.
function Get-ChildPhpPids($parentPid) {
    Get-CimInstance Win32_Process -Filter "ParentProcessId = $parentPid AND Name = 'php.exe'" -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty ProcessId
}

<#
    Kill a component's supervising loop and everything under it.

    Children first: killing the loop alone would orphan the php.exe it spawned,
    and an orphaned queue worker keeps consuming jobs with nothing tracking it.
#>
function Stop-ComponentTree($component) {
    $procId = Test-ManagedProcess $component.Name
    if (-not $procId) { return }

    foreach ($child in Get-ChildPids $procId) {
        Stop-Process -Id $child -Force -ErrorAction SilentlyContinue
    }
    Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
}

# --------------------------------------------------------------------------
#  Preflight
# --------------------------------------------------------------------------

function Test-DatabaseUp($cfg) {
    $dbHost = Get-EnvValue $cfg 'DB_HOST' '127.0.0.1'
    $dbPort = [int](Get-EnvValue $cfg 'DB_PORT' '3306')
    try {
        $client = [System.Net.Sockets.TcpClient]::new()
        $ok = $client.ConnectAsync($dbHost, $dbPort).Wait(3000)
        $client.Close()
        return @{ Up = $ok; Target = "$dbHost`:$dbPort" }
    } catch {
        return @{ Up = $false; Target = "$dbHost`:$dbPort" }
    }
}

function Test-WebUp($cfg) {
    try {
        $u = [Uri]$cfg.AppUrl
        $port = if ($u.IsDefaultPort) { if ($u.Scheme -eq 'https') { 443 } else { 80 } } else { $u.Port }
        $client = [System.Net.Sockets.TcpClient]::new()
        $ok = $client.ConnectAsync($u.Host, $port).Wait(3000)
        $client.Close()
        return @{ Up = $ok; Target = "$($u.Host):$port" }
    } catch {
        return @{ Up = $false; Target = $cfg.AppUrl }
    }
}

<#
    Returns the HTTP status code, or 0 if the host could not be reached.

    Written for Windows PowerShell 5.1, which is what `powershell.exe` is and
    therefore what the .bat wrappers run. 5.1 throws on any non-2xx response and
    has no -SkipHttpErrorCheck, so the status has to be dug out of the exception.
    Do not "simplify" this to -SkipHttpErrorCheck: that is PowerShell 7 only, and
    Windows Server ships 5.1.
#>
function Get-HttpStatus($url, $timeoutSeconds) {
    try {
        $r = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec $timeoutSeconds
        return [int]$r.StatusCode
    } catch {
        $response = $null
        try { $response = $_.Exception.Response } catch { }
        if ($response) {
            try { return [int]$response.StatusCode } catch { return 0 }
        }
        return 0
    }
}

function Test-Health($cfg, $timeoutSeconds) {
    $deadline = (Get-Date).AddSeconds($timeoutSeconds)
    do {
        if ((Get-HttpStatus "$($cfg.AppUrl)/up" 10) -eq 200) { return $true }
        Start-Sleep -Seconds 2
    } while ((Get-Date) -lt $deadline)
    return $false
}

# --------------------------------------------------------------------------
#  start
# --------------------------------------------------------------------------

function Start-Component($cfg, $component) {
    $existing = Test-ManagedProcess $component.Name
    if ($existing) {
        Write-Info "$($component.Description) already running (PID $existing)"
        return $true
    }

    $logDir = Join-Path $cfg.AppPath 'storage\logs'
    New-Item -ItemType Directory -Force $logDir | Out-Null
    $outLog = Join-Path $logDir "$($component.Name).log"

    <#
        The supervising loop, written to its own file under run/.

        It relaunches the worker whenever that exits -- on a crash, or on the
        hourly --max-time recycle -- and stops only when `stop` drops the flag
        file. It redirects its own output, because of how it gets launched.

        Two things here are deliberate and must not be "tidied":

        1. The loop is a FILE, not a -Command string. Quoting a multi-line
           script through a command line is where this kind of thing goes
           wrong, and the file is readable when you are debugging at 2am.

        2. It is launched by Win32_Process.Create, not Start-Process. A process
           started by Start-Process inherits the caller's console handles, so
           the detached worker keeps the parent's stdout pipe open for its whole
           life. `start.bat > log.txt` or `start.bat | ...` then never returns --
           the command has finished but the pipe never closes. That bites any
           script or agent that captures output, which is most of them.
           Win32_Process.Create inherits nothing, so the loop redirects its own
           streams to $outLog instead.
    #>
    $argLiterals = ($component.Arguments | ForEach-Object { "'" + ($_ -replace "'", "''") + "'" }) -join ', '
    $loopPath = Get-LoopPath $component.Name

    @"
# Generated by compozit.ps1 - do not edit. Rewritten on every start.
# Supervising loop for: $($component.Description)
`$ErrorActionPreference = 'Continue'
Set-Location '$($cfg.AppPath -replace "'", "''")'
`$phpArgs = @($argLiterals)
while (-not (Test-Path '$($Script:StopFlag -replace "'", "''")')) {
    & '$($cfg.PhpExe -replace "'", "''")' @phpArgs *>> '$($outLog -replace "'", "''")'
    if (Test-Path '$($Script:StopFlag -replace "'", "''")') { break }
    Start-Sleep -Seconds 2
}
"@ | Set-Content -Path $loopPath -Encoding UTF8

    $commandLine = 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "{0}"' -f $loopPath
    $result = Invoke-CimMethod -ClassName Win32_Process -MethodName Create -Arguments @{
        CommandLine      = $commandLine
        CurrentDirectory = $cfg.AppPath
    }

    if ($result.ReturnValue -ne 0) {
        Write-Bad "$($component.Description) could not be launched (Win32_Process.Create returned $($result.ReturnValue))"
        return $false
    }

    Start-Sleep -Milliseconds 900
    $live = Get-CimInstance Win32_Process -Filter "ProcessId = $($result.ProcessId)" -ErrorAction SilentlyContinue
    if (-not $live) {
        Write-Bad "$($component.Description) exited immediately - see $outLog"
        return $false
    }

    Set-Content -Path (Get-PidPath $component.Name) -Value $result.ProcessId -Encoding ascii
    Write-Ok "$($component.Description) started (PID $($result.ProcessId))"
    return $true
}

function Invoke-Start($cfg) {
    Write-Head 'Compozit Suite - starting'
    New-Item -ItemType Directory -Force $Script:RunDir | Out-Null
    Remove-Item $Script:StopFlag -ErrorAction SilentlyContinue

    # Preflight. The queue driver is `database`; without MySQL the worker would
    # spin and fail on every poll, so this is fatal rather than a warning.
    $db = Test-DatabaseUp $cfg
    if ($db.Up) {
        Write-Ok "Database reachable ($($db.Target))"
    } elseif ($cfg.ExpectDatabase) {
        Write-Bad "Database unreachable ($($db.Target)). Start MySQL in Laragon/XAMPP first."
        return 1
    } else {
        Write-Warn "Database unreachable ($($db.Target)) - starting anyway"
    }

    if ($cfg.ExpectWebServer) {
        $web = Test-WebUp $cfg
        if ($web.Up) {
            Write-Ok "Web server listening ($($web.Target))"
        } else {
            Write-Warn "Nothing listening on $($web.Target). Start Apache in Laragon/XAMPP - this script does not."
        }
    }

    $all = $true
    foreach ($c in Get-Components $cfg) {
        if (-not (Start-Component $cfg $c)) { $all = $false }
    }

    if ($cfg.ExpectWebServer) {
        Write-Info "Waiting for $($cfg.AppUrl)/up ..."
        if (Test-Health $cfg $cfg.HealthTimeoutSeconds) {
            Write-Ok "Application healthy at $($cfg.AppUrl)"
        } else {
            Write-Warn "No 200 from $($cfg.AppUrl)/up within $($cfg.HealthTimeoutSeconds)s. Workers are running; check the web server."
        }
    }

    Write-Host ''
    if ($all) { Write-Host '  Started.' -ForegroundColor Green; return 0 }
    Write-Host '  Started with errors - see above.' -ForegroundColor Red
    return 1
}

# --------------------------------------------------------------------------
#  stop
# --------------------------------------------------------------------------

function Invoke-Stop($cfg) {
    Write-Head 'Compozit Suite - stopping'
    New-Item -ItemType Directory -Force $Script:RunDir | Out-Null

    $running = @(Get-Components $cfg | Where-Object { Test-ManagedProcess $_.Name })
    if ($running.Count -eq 0) {
        Write-Info 'Nothing running.'
        Get-ChildItem $Script:RunDir -Filter *.pid -ErrorAction SilentlyContinue | Remove-Item -Force
        return 0
    }

    # 1. Flag first, so a loop whose child exits during shutdown does not
    #    helpfully start a replacement behind us.
    Set-Content -Path $Script:StopFlag -Value (Get-Date -Format o) -Encoding ascii

    # 2. Components with nothing to drain go now. Waiting on them would spend
    #    the entire grace period achieving nothing.
    foreach ($c in @($running | Where-Object { -not $_.Graceful })) {
        Stop-ComponentTree $c
        Write-Ok "$($c.Description) stopped"
    }

    $graceful = @($running | Where-Object { $_.Graceful })
    if ($graceful.Count) {
        # 3. Ask workers to finish the job in hand and exit. Killing one mid-job
        #    would leave that job reserved until it timed out.
        Write-Info 'Signalling workers to finish current job (queue:restart)'
        try {
            Push-Location $cfg.AppPath
            & $cfg.PhpExe artisan queue:restart 2>&1 | Out-Null
        } catch {
            Write-Warn "queue:restart failed: $($_.Exception.Message)"
        } finally { Pop-Location }

        # 4. Wait them out.
        $deadline = (Get-Date).AddSeconds($cfg.StopGraceSeconds)
        do {
            $still = @($graceful | Where-Object { Test-ManagedProcess $_.Name })
            if ($still.Count -eq 0) { break }
            Start-Sleep -Seconds 1
        } while ((Get-Date) -lt $deadline)

        # 5. Anything still holding on gets the tree killed.
        foreach ($c in $graceful) {
            if (Test-ManagedProcess $c.Name) {
                Write-Warn "$($c.Description) did not exit in $($cfg.StopGraceSeconds)s - terminating"
                Stop-ComponentTree $c
            } else {
                Write-Ok "$($c.Description) stopped"
            }
        }
    }

    foreach ($c in $running) { Remove-Item (Get-PidPath $c.Name) -ErrorAction SilentlyContinue }

    Remove-Item $Script:StopFlag -ErrorAction SilentlyContinue
    Write-Host ''
    Write-Host '  Stopped.' -ForegroundColor Green
    return 0
}

# --------------------------------------------------------------------------
#  status
# --------------------------------------------------------------------------

function Invoke-Status($cfg) {
    Write-Head 'Compozit Suite - status'
    Write-Host "  App      $($cfg.AppPath)"
    Write-Host "  PHP      $($cfg.PhpExe)"
    Write-Host "  URL      $($cfg.AppUrl)"
    Write-Host ''

    $healthy = $true

    Write-Host '  Not managed by this script (Laragon / XAMPP owns these):' -ForegroundColor DarkGray
    $db = Test-DatabaseUp $cfg
    if ($db.Up) { Write-Ok "MySQL       listening on $($db.Target)" }
    else { Write-Bad "MySQL       nothing on $($db.Target)"; $healthy = $false }

    $web = Test-WebUp $cfg
    if ($web.Up) { Write-Ok "Web server  listening on $($web.Target)" }
    else { Write-Bad "Web server  nothing on $($web.Target)"; $healthy = $false }

    Write-Host ''
    Write-Host '  Managed by this script:' -ForegroundColor DarkGray
    foreach ($c in Get-Components $cfg) {
        $procId = Test-ManagedProcess $c.Name
        if ($procId) {
            $children = @(Get-ChildPhpPids $procId)
            $note = if ($children.Count) { "supervisor PID $procId, php PID $($children -join ',')" } else { "supervisor PID $procId, php restarting" }
            Write-Ok ("{0,-12}{1}" -f $c.Name, $note)
        } else {
            Write-Bad ("{0,-12}not running" -f $c.Name)
            $healthy = $false
        }
    }

    Write-Host ''
    if ($web.Up) {
        $code = Get-HttpStatus "$($cfg.AppUrl)/up" 10
        if ($code -eq 200) {
            Write-Ok 'Health      GET /up -> 200'
        } elseif ($code -eq 0) {
            Write-Bad 'Health      GET /up -> no response'
            $healthy = $false
        } else {
            Write-Bad "Health      GET /up -> $code"
            $healthy = $false
        }

        # /up is deliberately exempt from maintenance mode so monitors keep
        # working, so it cannot tell you the site is down. Ask a real route.
        $root = Get-HttpStatus $cfg.AppUrl 10
        if ($root -eq 503) {
            Write-Warn 'Maintenance  site is in maintenance mode. Run: php artisan up'
            $healthy = $false
        }
    }

    $task = Get-ScheduledTask -TaskName $Script:TaskName -ErrorAction SilentlyContinue
    Write-Host ''
    if ($task) { Write-Ok  "Boot task   registered ($($task.State))" }
    else { Write-Info 'Boot task   not registered - run: compozit.ps1 install-task (as admin)' }

    Write-Host ''
    if ($healthy) { Write-Host '  Everything is up.' -ForegroundColor Green; return 0 }
    Write-Host '  Something is down - see above.' -ForegroundColor Red
    return 1
}

# --------------------------------------------------------------------------
#  Boot task
# --------------------------------------------------------------------------

function Test-Elevated {
    $p = [Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent())
    return $p.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Install-BootTask($cfg) {
    Write-Head 'Registering boot task'
    if (-not (Test-Elevated)) {
        Write-Bad 'Administrator rights required. Re-run from an elevated PowerShell.'
        return 1
    }

    $action  = New-ScheduledTaskAction -Execute 'powershell.exe' `
        -Argument "-NoProfile -NonInteractive -ExecutionPolicy Bypass -File `"$(Join-Path $Script:Root 'compozit.ps1')`" start" `
        -WorkingDirectory $Script:Root
    $trigger = New-ScheduledTaskTrigger -AtStartup
    # Delay so Laragon/XAMPP's own services are listening before the workers
    # start polling a database that is not up yet.
    $trigger.Delay = 'PT90S'
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
        -StartWhenAvailable -ExecutionTimeLimit ([TimeSpan]::Zero) -RestartCount 3 -RestartInterval ([TimeSpan]::FromMinutes(1))

    Register-ScheduledTask -TaskName $Script:TaskName -Action $action -Trigger $trigger `
        -Settings $settings -User 'SYSTEM' -RunLevel Highest -Force | Out-Null

    Write-Ok "Registered '$Script:TaskName' - starts 90s after boot, as SYSTEM."
    Write-Warn 'Running as SYSTEM means a different account from yours. Reboot once and check status.bat.'
    return 0
}

function Uninstall-BootTask {
    Write-Head 'Removing boot task'
    if (-not (Test-Elevated)) { Write-Bad 'Administrator rights required.'; return 1 }
    if (Get-ScheduledTask -TaskName $Script:TaskName -ErrorAction SilentlyContinue) {
        Unregister-ScheduledTask -TaskName $Script:TaskName -Confirm:$false
        Write-Ok "Removed '$Script:TaskName'"
    } else {
        Write-Info 'Not registered.'
    }
    return 0
}

# --------------------------------------------------------------------------
#  logs / help
# --------------------------------------------------------------------------

function Invoke-Logs($cfg) {
    Write-Head "Last $Lines lines of each managed component"
    $logDir = Join-Path $cfg.AppPath 'storage\logs'
    foreach ($c in Get-Components $cfg) {
        $f = Join-Path $logDir "$($c.Name).log"
        if ((Test-Path $f) -and (Get-Item $f).Length -gt 0) {
            Write-Host ''
            Write-Host "--- $f" -ForegroundColor Yellow
            Get-Content $f -Tail $Lines
        }
    }
    $app = Join-Path $logDir 'laravel.log'
    if (Test-Path $app) {
        Write-Host ''
        Write-Host "--- $app" -ForegroundColor Yellow
        Get-Content $app -Tail $Lines
    }
    return 0
}

function Show-Help {
    @'
Compozit Suite supervisor

  manage.bat           Interactive console - start here if unsure
  start.bat            Start queue workers and the scheduler
  stop.bat             Stop them, letting in-flight jobs finish
  restart.bat          Both. Run this after every deployment
  status.bat           Report on everything; exit 0 when healthy

  compozit.ps1 logs [-Lines 100]     Tail the component logs
  compozit.ps1 install-task          Start at boot (needs admin)
  compozit.ps1 uninstall-task        Remove that (needs admin)

Apache and MySQL are NOT started here - Laragon or XAMPP owns them.
Settings live in compozit.config.ps1; everything auto-detects by default.
'@ | Write-Host
    return 0
}

# --------------------------------------------------------------------------
#  Interactive console
# --------------------------------------------------------------------------

function Write-Row($label, $up, $detail) {
    Write-Host '   ' -NoNewline
    if ($up) { Write-Host '[ UP ]' -ForegroundColor Green -NoNewline }
    else { Write-Host '[DOWN]' -ForegroundColor Red -NoNewline }
    Write-Host ("  {0,-13}{1}" -f $label, $detail)
}

function Show-Console($cfg) {
    Write-Host ''
    Write-Host '  ===========================================================' -ForegroundColor Cyan
    Write-Host '     COMPOZIT SUITE  -  Operations Console' -ForegroundColor Cyan
    Write-Host '  ===========================================================' -ForegroundColor Cyan
    Write-Host "     $($cfg.AppPath)" -ForegroundColor DarkGray
    Write-Host "     $($cfg.AppUrl)" -ForegroundColor DarkGray
    Write-Host ''

    $db = Test-DatabaseUp $cfg
    Write-Row 'MySQL' $db.Up "$($db.Target)   (Laragon/XAMPP)"
    $web = Test-WebUp $cfg
    Write-Row 'Web server' $web.Up "$($web.Target)   (Laragon/XAMPP)"

    foreach ($c in Get-Components $cfg) {
        $procId = Test-ManagedProcess $c.Name
        if ($procId) {
            $php = @(Get-ChildPhpPids $procId)
            $detail = if ($php.Count) { "php PID $($php -join ',')" } else { 'restarting' }
            Write-Row $c.Name $true $detail
        } else {
            Write-Row $c.Name $false 'not running'
        }
    }

    $maintenance = $false
    if ($web.Up) {
        $code = Get-HttpStatus "$($cfg.AppUrl)/up" 8
        Write-Row 'Health' ($code -eq 200) "GET /up -> $(if ($code) { $code } else { 'no response' })"
        if ((Get-HttpStatus $cfg.AppUrl 8) -eq 503) {
            $maintenance = $true
            Write-Host '    [!]   MAINTENANCE MODE - visitors see a 503 page' -ForegroundColor Yellow
        }
    }

    Write-Host ''
    Write-Host '  -----------------------------------------------------------' -ForegroundColor DarkGray
    Write-Host '   1  Start                 5  View logs'
    Write-Host '   2  Stop                  6  Failed jobs'
    if ($maintenance) {
        Write-Host '   3  Restart               7  Maintenance mode  ' -NoNewline
        Write-Host '[ON]' -ForegroundColor Yellow
    } else {
        Write-Host '   3  Restart               7  Maintenance mode  [off]'
    }
    Write-Host '   4  Refresh               8  Start-at-boot task'
    Write-Host ''
    Write-Host '   Q  Quit'
    Write-Host '  -----------------------------------------------------------' -ForegroundColor DarkGray

    return $maintenance
}

<#
    Read-Host returns $null -- not an empty string -- when stdin is at EOF,
    which happens whenever this is launched with its input redirected or
    closed. Calling .Trim() on that throws, and with $ErrorActionPreference =
    'Stop' the whole console dies with "You cannot call a method on a
    null-valued expression". Every prompt goes through here instead.
#>
function Read-Choice($prompt) {
    $value = Read-Host $prompt
    if ($null -eq $value) { return '' }
    return $value.Trim()
}

function Wait-Key {
    Write-Host ''
    Read-Host '  Press Enter to return to the console' | Out-Null
}

<#
    Clear-Host throws "The handle is invalid" when the console's output is
    redirected -- piped to a file, or captured by a calling script. With
    $ErrorActionPreference = 'Stop' at the top of this file that would abort the
    whole console instead of just failing to clear, so it is swallowed. A
    redirected console does not need clearing anyway.
#>
function Clear-Screen {
    try { Clear-Host } catch { Write-Host '' }
}

function Invoke-MenuLogs($cfg) {
    Write-Head 'Logs'
    Write-Host '   1  Queue worker      2  Scheduler      3  Application (laravel.log)      4  All'
    $pick = Read-Choice '  Choose (Enter = all)'
    $logDir = Join-Path $cfg.AppPath 'storage\logs'
    $files = switch ($pick) {
        '1' { @(Join-Path $logDir 'queue-1.log') }
        '2' { @(Join-Path $logDir 'scheduler.log') }
        '3' { @(Join-Path $logDir 'laravel.log') }
        default {
            @(Get-Components $cfg | ForEach-Object { Join-Path $logDir "$($_.Name).log" }) +
            @(Join-Path $logDir 'laravel.log')
        }
    }
    foreach ($f in $files) {
        Write-Host ''
        Write-Host "--- $f" -ForegroundColor Yellow
        if ((Test-Path $f) -and (Get-Item $f).Length -gt 0) {
            Get-Content $f -Tail 30
        } else {
            Write-Host '    (empty or not created yet)' -ForegroundColor DarkGray
        }
    }
}

function Invoke-MenuFailedJobs($cfg) {
    Write-Head 'Failed background jobs'
    Push-Location $cfg.AppPath
    try {
        & $cfg.PhpExe artisan queue:failed 2>&1 | Write-Host
        Write-Host ''
        Write-Host '   R  Retry all      F  Delete all      Enter  Back'
        switch ((Read-Choice '  Choose').ToUpper()) {
            'R' { & $cfg.PhpExe artisan queue:retry all 2>&1 | Write-Host }
            'F' {
                if ((Read-Choice '  Type DELETE to confirm') -ceq 'DELETE') {
                    & $cfg.PhpExe artisan queue:flush 2>&1 | Write-Host
                } else {
                    Write-Info 'Cancelled.'
                }
            }
        }
    } finally { Pop-Location }
}

function Invoke-MenuMaintenance($cfg, $isOn) {
    Write-Head 'Maintenance mode'
    Push-Location $cfg.AppPath
    try {
        if ($isOn) {
            Write-Info 'Bringing the site back up.'
            & $cfg.PhpExe artisan up 2>&1 | Write-Host
        } else {
            Write-Warn 'This shows every user a 503 page until you turn it off.'
            if ((Read-Choice '  Type DOWN to confirm') -ceq 'DOWN') {
                & $cfg.PhpExe artisan down 2>&1 | Write-Host
            } else {
                Write-Info 'Cancelled.'
            }
        }
    } finally { Pop-Location }
}

function Invoke-MenuBootTask($cfg) {
    Write-Head 'Start-at-boot task'
    $task = Get-ScheduledTask -TaskName $Script:TaskName -ErrorAction SilentlyContinue
    if ($task) {
        Write-Ok "Registered, state: $($task.State)"
        Write-Host '   R  Remove it      Enter  Back'
        if ((Read-Choice '  Choose').ToUpper() -eq 'R') { Uninstall-BootTask | Out-Null }
    } else {
        Write-Info 'Not registered. Without it, the workers stay down after a reboot.'
        Write-Host '   I  Install it (needs administrator)      Enter  Back'
        if ((Read-Choice '  Choose').ToUpper() -eq 'I') { Install-BootTask $cfg | Out-Null }
    }
}

<#
    The interactive console.

    `blank` guards against a runaway loop. If this is launched with its input
    redirected or closed, Read-Host returns an empty string forever rather than
    blocking, and an unguarded `while ($true)` would spin at full tilt. Three
    empty reads is taken as "there is nobody there" and exits.
#>
function Invoke-Menu($cfg) {
    $blank = 0
    while ($true) {
        Clear-Screen
        $maintenance = Show-Console $cfg

        $choice = (Read-Choice '  Choose').ToUpper()

        if ([string]::IsNullOrEmpty($choice)) {
            $blank++
            if ($blank -ge 3) {
                Write-Host ''
                Write-Info 'No input - leaving the console.'
                return 0
            }
            continue
        }
        $blank = 0

        switch ($choice) {
            '1' { Invoke-Start $cfg | Out-Null; Wait-Key }
            '2' { Invoke-Stop  $cfg | Out-Null; Wait-Key }
            '3' { Invoke-Stop  $cfg | Out-Null; Invoke-Start $cfg | Out-Null; Wait-Key }
            '4' { }   # redraw
            '5' { Invoke-MenuLogs $cfg; Wait-Key }
            '6' { Invoke-MenuFailedJobs $cfg; Wait-Key }
            '7' { Invoke-MenuMaintenance $cfg $maintenance; Wait-Key }
            '8' { Invoke-MenuBootTask $cfg; Wait-Key }
            'Q' { Write-Host ''; Write-Host '  Bye.' -ForegroundColor Cyan; return 0 }
            default {
                Write-Host ''
                Write-Warn "'$choice' is not an option."
                Start-Sleep -Milliseconds 900
            }
        }
    }
}

# --------------------------------------------------------------------------
#  Entry point
# --------------------------------------------------------------------------

try {
    if ($Action -eq 'help') { exit (Show-Help) }

    $cfg = Get-Config

    switch ($Action) {
        'menu'           { exit (Invoke-Menu $cfg) }
        'start'          { exit (Invoke-Start $cfg) }
        'stop'           { exit (Invoke-Stop $cfg) }
        'restart'        { Invoke-Stop $cfg | Out-Null; exit (Invoke-Start $cfg) }
        'status'         { exit (Invoke-Status $cfg) }
        'logs'           { exit (Invoke-Logs $cfg) }
        'install-task'   { exit (Install-BootTask $cfg) }
        'uninstall-task' { exit (Uninstall-BootTask) }
    }
} catch {
    Write-Host ''
    Write-Bad $_.Exception.Message
    Write-Host "  $($_.ScriptStackTrace)" -ForegroundColor DarkGray
    exit 1
}
