# =============================================================================
#  Compozit Suite - deployment configuration
# =============================================================================
#  Every value below is auto-detected when left as $null. On a normal install
#  you do not need to edit this file at all. Change a value only when the
#  auto-detected one is wrong, and `status.bat` will tell you when it is.
#
#  This file is PowerShell. It must end by producing the hashtable below --
#  do not add anything after it.
# =============================================================================

# `deploy/` lives inside the application, so the app root is one level up.
$AppRoot = Split-Path -Parent $PSScriptRoot

@{
    # --- Paths ---------------------------------------------------------------

    # The application root: the folder holding `artisan`.
    AppPath = $AppRoot

    # Full path to php.exe. $null = find `php` on PATH.
    # Set this explicitly on a server, where PATH may differ per account:
    #   PhpExe = 'C:/xampp/php/php.exe'
    PhpExe = $null

    # The URL the site is served on. $null = read APP_URL from the app's .env.
    # Used for the health check only; this script never starts a web server.
    AppUrl = $null

    # --- Queue workers -------------------------------------------------------

    # How many `queue:work` processes to run. 1 is right for this application
    # today -- it has no queued jobs yet, so the worker sits idle. Raise it when
    # jobs are added and one worker stops keeping up.
    QueueWorkers = 1

    # Arguments passed to `php artisan queue:work`.
    #   --max-time=3600 recycles the worker hourly so a slow leak cannot grow.
    #                   The supervisor restarts it immediately; nothing is lost.
    #   --tries=3       a failing job is retried twice, then recorded in
    #                   `failed_jobs` rather than retried forever.
    QueueArgs = @('--tries=3', '--timeout=90', '--sleep=3', '--max-time=3600')

    # --- Scheduler -----------------------------------------------------------

    # Runs `php artisan schedule:work`, which fires due scheduled tasks every
    # minute. Currently the application schedules nothing, so this is a no-op
    # that costs one idle process -- and means the first scheduled task somebody
    # adds simply works, instead of silently never running.
    RunScheduler = $true

    # --- Behaviour -----------------------------------------------------------

    # Seconds to let a queue worker finish its current job before it is killed.
    # Must exceed the longest job you expect. Kept above --timeout=90 above.
    StopGraceSeconds = 120

    # Seconds to wait for the site to answer /up after `start`.
    HealthTimeoutSeconds = 45

    # --- Web server ----------------------------------------------------------

    <#
        Start and supervise Apache alongside the queue worker and scheduler, so
        one command brings the whole application up.

        ** ONE APACHE SERVES EVERY VHOST ON THE MACHINE. **

        That is the thing to understand before leaving this on. Apache is a
        single process serving every site configured on it -- on a Laragon box
        that is this application on :8787 *and* every other project on :8080.
        Stopping it here stops all of them. On a dedicated server that is
        exactly what you want; on a machine you also develop on, it may not be.

        Set to $false to go back to only managing the queue worker and
        scheduler, leaving Apache to Laragon or XAMPP. Nothing else changes.
    #>
    ManageWebServer = $true

    # Full path to httpd.exe, and Apache's ServerRoot. $null = auto-detect:
    # from a running httpd first, then the usual Laragon and XAMPP locations.
    # `status.bat` prints what it resolved.
    ApacheExe  = $null
    ApacheRoot = $null

    # --- Preflight checks ----------------------------------------------------

    # Refuse to start if the database is unreachable. Leave $true: the queue
    # worker uses the `database` queue driver and cannot run without it.
    #
    # MySQL is never started or stopped here. It belongs to Laragon or XAMPP,
    # and killing mysqld outright risks InnoDB crash recovery on live data --
    # a graceful `mysqladmin shutdown` is the only safe way, which is a
    # different job from supervising a process.
    ExpectDatabase = $true

    # Warn if nothing is listening on the site's port. Only consulted when
    # ManageWebServer is $false; otherwise the web server is a managed
    # component and is reported as one.
    ExpectWebServer = $true
}
