<#
.SYNOPSIS
    Deploys Compozit Suite on the LAN production server.

.DESCRIPTION
    Run this ON THE SERVER, from the repository root, after pushing to GitHub.

        .\deploy.ps1

    Every step aborts the run on failure. The application is put into
    maintenance mode before anything changes and taken out only once the whole
    sequence has succeeded, so a failed deploy leaves a 503 page rather than a
    half-updated application serving real order data.

    A database dump is taken immediately before migrations run, so a migration
    that goes wrong has a backup from seconds earlier, not from last night.

    Only two seeders run here. `DatabaseSeeder` is never invoked in production:
    it creates test@example.com with the password "password". Both seeders that
    do run are idempotent by design, which is why re-running a deploy is safe.

    First deploy on a new machine: see the runbook in ARCHITECTURE.md §15.

.PARAMETER SkipBuild
    Skip `npm ci` and `npm run build`. Only valid when the deploy changes no
    frontend asset — public/build is gitignored, so if it is missing this will
    leave the site with no CSS or JS at all.

.PARAMETER SkipBackup
    Skip the pre-migration dump. Use only when the database is empty.
#>

[CmdletBinding()]
param(
    [switch]$SkipBuild,
    [switch]$SkipBackup
)

$ErrorActionPreference = 'Stop'
Set-Location -Path $PSScriptRoot

$script:MaintenanceModeOn = $false

function Invoke-Step {
    <#
        Runs a native command and stops the deploy if it fails.

        PowerShell does not treat a non-zero exit code from a native executable
        as a terminating error, so $ErrorActionPreference alone would let the
        script sail past a failed migration straight into `artisan up`.
    #>
    param(
        [Parameter(Mandatory)][string]$Name,
        [Parameter(Mandatory)][scriptblock]$Action
    )

    Write-Host ""
    Write-Host "==> $Name" -ForegroundColor Cyan

    & $Action

    if ($LASTEXITCODE -ne 0) {
        throw "$Name failed with exit code $LASTEXITCODE."
    }
}

function Assert-Command {
    param([Parameter(Mandatory)][string]$Name, [Parameter(Mandatory)][string]$Hint)

    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "'$Name' is not on the PATH. $Hint"
    }
}

try {
    # -------------------------------------------------------------------------
    # 0. Fail before touching anything if the machine is not set up
    # -------------------------------------------------------------------------
    Assert-Command -Name 'git'      -Hint 'Install Git for Windows.'
    Assert-Command -Name 'php'      -Hint 'Add the Laragon PHP directory to the system PATH.'
    Assert-Command -Name 'composer' -Hint 'Install Composer.'

    if (-not $SkipBuild) {
        Assert-Command -Name 'npm' -Hint 'Install Node 22, or pass -SkipBuild if no asset changed.'
    }

    if (-not (Test-Path '.env')) {
        throw "No .env found. Copy .env.production.example to .env, fill it in, then run 'php artisan key:generate'."
    }

    $appEnv = (Select-String -Path '.env' -Pattern '^APP_ENV=(.*)$').Matches.Groups[1].Value.Trim()

    if ($appEnv -ne 'production') {
        throw "APP_ENV is '$appEnv', not 'production'. Refusing to deploy: the seeders and optimisations below assume production."
    }

    # -------------------------------------------------------------------------
    # 1. Get the new code before going down, so a bad pull costs no downtime
    # -------------------------------------------------------------------------
    Invoke-Step 'Fetching latest code' { git pull --ff-only }

    # -------------------------------------------------------------------------
    # 2. Down
    # -------------------------------------------------------------------------
    Invoke-Step 'Entering maintenance mode' { php artisan down --retry=60 }
    $script:MaintenanceModeOn = $true

    # -------------------------------------------------------------------------
    # 3. Dependencies
    # -------------------------------------------------------------------------
    Invoke-Step 'Installing PHP dependencies' {
        composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
    }

    if (-not $SkipBuild) {
        # `npm ci` not `npm install`: it installs exactly the lockfile, and it
        # is the only one that cannot silently drift the production bundle.
        Invoke-Step 'Installing JS dependencies' { npm ci }
        Invoke-Step 'Building frontend assets'   { npm run build }
    }
    else {
        Write-Host "`n==> Skipping frontend build (-SkipBuild)" -ForegroundColor Yellow

        if (-not (Test-Path 'public/build/manifest.json')) {
            throw "public/build/manifest.json is missing, so -SkipBuild would deploy a site with no CSS or JS. Re-run without it."
        }
    }

    # -------------------------------------------------------------------------
    # 4. Database — dump first, then migrate
    # -------------------------------------------------------------------------
    if (-not $SkipBackup) {
        Invoke-Step 'Backing up the database' { php artisan backup:database }
    }
    else {
        Write-Host "`n==> Skipping pre-migration backup (-SkipBackup)" -ForegroundColor Yellow
    }

    Invoke-Step 'Running migrations' { php artisan migrate --force }

    # -------------------------------------------------------------------------
    # 5. Reference data
    #
    # The RBAC catalogue is re-synced every deploy so a release that adds a
    # permission grants it to the right roles without a manual step. The
    # designation list is topped up the same way. Neither overwrites anything an
    # admin has changed through the UI.
    # -------------------------------------------------------------------------
    Invoke-Step 'Seeding roles and permissions' {
        php artisan db:seed --class="Database\Seeders\Admin\RolePermissionSeeder" --force
    }
    Invoke-Step 'Seeding designations' {
        php artisan db:seed --class="Database\Seeders\Admin\DesignationSeeder" --force
    }

    # No-op after the first deploy. Never resets an existing password.
    Invoke-Step 'Ensuring the super administrator exists' { php artisan admin:create-super }

    # -------------------------------------------------------------------------
    # 6. Caches
    #
    # `optimize` writes the config, route, view and event caches. It must come
    # AFTER the steps above: with a cached config in place, any .env change made
    # mid-deploy would be invisible to them.
    # -------------------------------------------------------------------------
    Invoke-Step 'Caching config, routes, views and events' { php artisan optimize }

    # Signals the running queue worker to exit after its current job; the
    # Windows service restarts it, and the new process picks up the new code.
    # A worker is a long-lived process and would otherwise run the old code
    # until the machine was rebooted.
    Invoke-Step 'Restarting the queue worker' { php artisan queue:restart }

    # -------------------------------------------------------------------------
    # 7. Up
    # -------------------------------------------------------------------------
    Invoke-Step 'Leaving maintenance mode' { php artisan up }
    $script:MaintenanceModeOn = $false

    Write-Host "`nDeploy complete." -ForegroundColor Green
    Write-Host "Check the health endpoint: $((Select-String -Path '.env' -Pattern '^APP_URL=(.*)$').Matches.Groups[1].Value.Trim())/up"
}
catch {
    Write-Host "`nDEPLOY FAILED: $_" -ForegroundColor Red

    if ($script:MaintenanceModeOn) {
        Write-Host "The application is STILL IN MAINTENANCE MODE and is serving a 503 to everyone." -ForegroundColor Red
        Write-Host "Fix the failure above, re-run .\deploy.ps1, or bring it back up as-is with: php artisan up" -ForegroundColor Yellow
    }

    exit 1
}
