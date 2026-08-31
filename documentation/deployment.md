# Deployment — Runbook

> **Scope.** This file is the *procedure*: the commands to run, in order, to get Compozit Suite
> running on the office server and to keep it running. *Why* it is built this way — why plain HTTP,
> why only two seeders run in production, why `DatabaseSeeder` must never run there — is
> [`ARCHITECTURE.md §15`](../ARCHITECTURE.md#15-deployment)'s job. This file links to it rather than
> restating it, so a decision never has two copies that can disagree.
>
> Unlike the other files in this folder, this is not a module reference. See
> [`ARCHITECTURE.md §14`](../ARCHITECTURE.md#14-documentation).
>
> Update this file in the same change as `deploy.ps1`, `.env.production.example`, or any deploy step.

---

## 1. Already set up? Deploy in three commands

On your own machine:

```powershell
git push origin main
```

On the server, in `C:\laragon\www\compozit-suite`:

```powershell
.\deploy.ps1
```

Then confirm it came back up:

```powershell
curl http://<server-ip>:8787/up
```

That is the whole routine deploy. `deploy.ps1` pulls, installs, builds, **backs up the database**,
migrates, seeds, caches and restarts the queue worker, and it stops the moment anything fails.

If it failed, jump to [§8 Troubleshooting](#8-troubleshooting). Everything between here and there is
for the first time only, or for when you need to do something by hand.

---

## 2. What you are deploying

One Windows PC on the office LAN, running Laragon. No cloud, no containers. People reach it at
`http://<server-ip>:8787` from their own PCs.

| | Your machine (development) | The server (production) |
| --- | --- | --- |
| Started with | `composer run dev` | Laragon's Apache, always on |
| Address | `http://localhost:8000` | `http://<static-lan-ip>:8787` |
| Who can reach it | only you | everyone on the office LAN |
| `APP_DEBUG` | `true` | `false` |
| Frontend assets | built live by Vite | pre-built into `public/build` |
| Background jobs | in the dev script | a Windows service |
| Deployed by | — | `deploy.ps1` |

**`composer run dev` is not how you run the server.** It is a development tool: it shows full error
pages with database credentials in them, and it stops when you close the terminal.

New to Laravel? Read [§9 Glossary](#9-glossary) first — it explains migrations, seeders, artisan and
the queue worker in a paragraph each.

---

## 3. First-time server setup

Do this once, on the server. Later deploys are just §1.

### 3.1 Install the prerequisites

| Software | Why |
| --- | --- |
| [Laragon](https://laragon.org/) (Apache + MySQL) | The web server and the database |
| PHP 8.4 | Ships with Laragon; the app requires 8.3+ |
| [Node.js 22](https://nodejs.org/) | Builds the frontend. **Not** bundled with Laragon |
| [Composer](https://getcomposer.org/) | Installs PHP libraries |
| [Git](https://git-scm.com/download/win) | Fetches the code |

Add PHP to the system PATH so `php` works in any terminal (Laragon: **Menu → Tools → Path → Add
Laragon to Path**), then check all four in a **new** PowerShell window:

```powershell
php --version        # expect 8.4.x
composer --version
node --version       # expect v22.x
git --version
```

`deploy.ps1` refuses to start if any of these is missing, so fix them now.

### 3.2 Create the database and its user

Open Laragon's MySQL console (**Menu → MySQL → CLI**) or run
`C:\laragon\bin\mysql\<version>\bin\mysql.exe -u root`, then:

```sql
CREATE DATABASE compozitsuite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'compozit'@'localhost' IDENTIFIED BY 'put-a-real-password-here';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES,
      CREATE VIEW, SHOW VIEW, TRIGGER, CREATE ROUTINE, ALTER ROUTINE, EXECUTE,
      LOCK TABLES
   ON compozitsuite.* TO 'compozit'@'localhost';

FLUSH PRIVILEGES;
```

**Use a dedicated user, not `root`.** These grants are exactly what the application and the backup
command need and nothing more — they were tested against this application, including taking a full
`mysqldump` as this user.

While you are here, note the server version — you need it in step 3.5:

```sql
SELECT VERSION();
```

### 3.3 Give the machine a fixed address

Set a static IP, or a DHCP reservation on the router. The address goes into `.env` as `APP_URL`, and
the app builds links from it.

> If the IP ever changes you must edit `.env` and re-run `php artisan optimize`, or the application
> starts producing links to an address nobody answers.

### 3.4 Get the code

```powershell
cd C:\laragon\www
git clone https://github.com/moinulpalmal/compozit-suite.git
cd compozit-suite
```

### 3.5 Create the configuration

```powershell
copy .env.production.example .env
notepad .env
```

`.env.production.example` explains every setting inline. The ones you must change:

| Setting | Set it to |
| --- | --- |
| `APP_URL` | `http://<your-server-ip>:8787` |
| `DB_USERNAME` / `DB_PASSWORD` | the user and password from §3.2 |
| `ADMIN_EMPLOYEE_ID`, `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | the first administrator's login — **you sign in with the employee ID, not the email** |
| `BACKUP_PATH` | a folder on a **different physical drive**, e.g. `D:\backups\compozit` |
| `BACKUP_MYSQLDUMP_PATH` | the `mysqldump.exe` matching the version from §3.2 |

> **`BACKUP_MYSQLDUMP_PATH` matters.** Laragon installs several MySQL versions side by side
> (`C:\laragon\bin\mysql\mysql-9.6.0-winx64\bin\mysqldump.exe`, `…8.4.3…`, `…5.7.39…`). Point it at
> the one matching the running server. An older client dumping a newer server can lose data.

Then generate the application's encryption key:

```powershell
php artisan key:generate
```

### 3.6 Link the file-upload folder

```powershell
php artisan storage:link
```

> **Run this from a terminal opened with "Run as administrator."** Windows needs admin rights (or
> Developer Mode) to create the symbolic link. Without it the command appears to work but uploaded
> files return 404 later, with nothing in the logs to explain it.

### 3.7 Point Apache at the app on port 8787

Two edits. **Stop Laragon first.**

**a. Tell Apache to listen on 8787.** Open
`C:\laragon\bin\apache\<apache-version>\conf\httpd.conf`, find the line `Listen 8080`, and add
below it:

```apache
Listen 8787
```

**b. Create the site.** In `C:\laragon\etc\apache2\sites-enabled\`, create a **new** file named
`compozit-production.conf`:

```apache
<VirtualHost *:8787>
    DocumentRoot "C:/laragon/www/compozit-suite/public"

    <Directory "C:/laragon/www/compozit-suite/public">
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "C:/laragon/etc/apache2/logs/compozit-error.log"
    CustomLog "C:/laragon/etc/apache2/logs/compozit-access.log" common
</VirtualHost>
```

> **Do not edit the `auto.compozit-suite.test.conf` file that is already in that folder.** Laragon
> generates every `auto.*.conf` itself and **overwrites them**, so your changes vanish on the next
> restart. Any filename without the `auto.` prefix is yours to keep — that is why the file above is
> called `compozit-production.conf`.

Three things must be true or the site will not work:

- `DocumentRoot` points at the project's **`public`** folder, not the project root. Pointing it at
  the root exposes `.env` to the whole network.
- `AllowOverride All`, so the shipped `public/.htaccess` can route requests.
- `mod_rewrite` is enabled (Laragon: **Menu → Apache → modules → rewrite_module**).

Start Laragon, then check locally on the server:

```powershell
curl http://localhost:8787/up
```

### 3.8 Open the firewall to the LAN only

In an administrator PowerShell:

```powershell
New-NetFirewallRule -DisplayName "Compozit Suite (8787)" -Direction Inbound `
  -Protocol TCP -LocalPort 8787 -Profile Private -Action Allow
```

`-Profile Private` matters: it opens the port to the office network but not to a public Wi-Fi the
machine might join later.

### 3.9 Run the background worker as a service

Download [NSSM](https://nssm.cc/download), then in an administrator PowerShell:

```powershell
nssm install CompozitQueue "C:\path\to\php.exe" "artisan queue:work --tries=3 --max-time=3600"
nssm set CompozitQueue AppDirectory "C:\laragon\www\compozit-suite"
nssm set CompozitQueue Start SERVICE_AUTO_START
nssm start CompozitQueue
```

> **Nothing uses the queue yet.** `app/Jobs/` and `app/Notifications/` are empty and nothing sends
> mail, so this service currently sits idle. It is installed now so the wiring exists before the
> first feature needs it — do not read its presence as evidence that something depends on it.

### 3.10 Add the scheduler task

This is what runs the nightly backup. **One** Windows task, ever:

```powershell
$action  = New-ScheduledTaskAction -Execute "C:\path\to\php.exe" `
             -Argument "artisan schedule:run" `
             -WorkingDirectory "C:\laragon\www\compozit-suite"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) `
             -RepetitionInterval (New-TimeSpan -Minutes 1)

Register-ScheduledTask -TaskName "CompozitScheduler" -Action $action -Trigger $trigger `
  -RunLevel Highest -Description "Runs Laravel's scheduler every minute"
```

Set it to **"Run whether user is logged on or not"** in Task Scheduler's properties, or it stops
when nobody is signed in.

> **Never add a second Windows task for a recurring job.** Everything scheduled lives in
> `routes/console.php` and is fired by this one entry. A job added in a Windows dialog exists on one
> machine only and is invisible to everyone reading the code. See
> [`ARCHITECTURE.md §15.5`](../ARCHITECTURE.md#155-recurring-work-belongs-in-routesconsolephp).

Check what it will run:

```powershell
php artisan schedule:list
# 0 1 * * *  php artisan backup:database .... Next Due: ...
```

### 3.11 Deploy, and check it from somebody else's PC

```powershell
.\deploy.ps1
```

Then, **from a different computer on the LAN** — not the server itself, which would pass even with
the firewall shut:

1. Open `http://<server-ip>:8787/up` → a green health page.
2. Open `http://<server-ip>:8787` → the login screen.
3. Sign in with the **`ADMIN_EMPLOYEE_ID`** and `ADMIN_PASSWORD` from `.env`.
4. Change that password immediately: **Settings → Security**.
5. Add your buyers under **Admin → Buyers**, then create real users.

> Buyers are deliberately not seeded — the application is buyer-scoped and an empty buyer list is the
> correct starting point. Designations *are* seeded, but the shipped 17 job titles are placeholders;
> edit them under **Admin → Designations** to match your real HR list.

---

## 4. What a deploy actually does

`deploy.ps1` runs these in order. Each one stops the deploy if it fails.

| # | Step | What it means |
| --- | --- | --- |
| 0 | Preflight | Checks `git`/`php`/`composer`/`npm` exist, that `.env` exists, and that `APP_ENV=production`. Refuses to run otherwise. |
| 1 | `git pull --ff-only` | Fetches the new code. Runs *before* the site goes down, so a failed pull costs no downtime. |
| 2 | `php artisan down` | Maintenance mode: everyone gets a "be right back" page instead of a half-updated app. |
| 3 | `composer install --no-dev --optimize-autoloader` | Installs PHP libraries, skipping development-only ones. |
| 4 | `npm ci` + `npm run build` | Compiles the React frontend into `public/build`. `ci` installs the lockfile exactly, so the bundle cannot drift. |
| 5 | `php artisan backup:database` | **A database dump, taken seconds before the migrations run.** |
| 6 | `php artisan migrate --force` | Applies database structure changes. `--force` means "yes, in production". |
| 7 | seed roles + designations | Re-syncs permissions so a release that adds one grants it automatically. Safe to repeat. |
| 8 | `php artisan admin:create-super` | Creates the first administrator. Does nothing on every deploy after the first, and **never** resets a password you have changed. |
| 9 | `php artisan optimize` | Caches config, routes and views for speed. Comes last, so no earlier step reads a stale cache. |
| 10 | `php artisan queue:restart` | Tells the background worker to pick up the new code. |
| 11 | `php artisan up` | Back online. |

**If a step fails, the site stays in maintenance mode on purpose.** A 503 page is a better outcome
than an ERP serving real orders from half-updated code. The script tells you so, and tells you how
to recover. Why this order is the way it is:
[`ARCHITECTURE.md §15.2`](../ARCHITECTURE.md#152-why-the-deploy-order-is-what-it-is).

### Options

```powershell
.\deploy.ps1 -SkipBuild     # no frontend change; refuses if public/build is missing
.\deploy.ps1 -SkipBackup    # only when the database is empty
```

---

## 5. Deploying by hand

Use this when `deploy.ps1` stopped partway and you want to continue from that step. Run them in the
project folder, in this order:

```powershell
php artisan down

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan backup:database
php artisan migrate --force
php artisan db:seed --class="Database\Seeders\Admin\RolePermissionSeeder" --force
php artisan db:seed --class="Database\Seeders\Admin\DesignationSeeder" --force
php artisan admin:create-super

php artisan optimize
php artisan queue:restart
php artisan up
```

> **Never run `php artisan db:seed` with no `--class` on the server.** That runs `DatabaseSeeder`,
> which creates a `test@example.com` account with the password `password`. Only the two seeders
> named above may run in production —
> [`ARCHITECTURE.md §15.3`](../ARCHITECTURE.md#153-what-is-seeded-in-production-and-what-must-never-be).

> **Never run `php artisan migrate:fresh` on the server.** It drops every table and everything in
> them. There is no undo beyond your backups.

---

## 6. Backups

### Where they come from

- **Nightly**, at `BACKUP_TIME` (default 01:00), via the scheduler task from §3.10.
- **Before every deploy**, automatically, as step 5.
- **Whenever you ask**:

```powershell
php artisan backup:database
```

Files land in `BACKUP_PATH` as `compozitsuite-YYYY-MM-DD_HHmmss.sql`. Anything older than
`BACKUP_RETENTION_DAYS` (default 14) is deleted after a *successful* run — a failed backup never
deletes the good history behind it.

### Checking the nightly job is really running

```powershell
Get-ChildItem D:\backups\compozit | Sort-Object LastWriteTime -Descending | Select-Object -First 5
```

If last night's file is missing, check the scheduler task in §3.10 and
`storage\logs\laravel.log`.

### What is not covered

`BACKUP_PATH` on the same machine survives a bad migration, a wrong `DELETE`, and a broken deploy.
It does **not** survive the machine being stolen, burning, or losing its disk. Copying the folder to
a NAS or network share on a schedule is still an open item —
[`ARCHITECTURE.md §15.4`](../ARCHITECTURE.md#154-backups).

---

## 7. Restoring from a backup

**These exact commands were run and verified against this application's database.** A restore was
tested into a scratch schema and every table and row count matched the source.

Restoring **overwrites data**. Take a fresh dump of the current state first, even if you believe it
is broken — you cannot get back to it otherwise.

Adjust paths to your MySQL version, then:

```powershell
# 0. Keep everyone out while you work
php artisan down

# 1. Snapshot the current (broken) state, so this step is reversible
php artisan backup:database

# 2. Restore into a SCRATCH database first and check it there
$mysql = "C:\laragon\bin\mysql\mysql-9.6.0-winx64\bin\mysql.exe"
& $mysql -u root -e "CREATE DATABASE compozit_check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cmd /c "`"$mysql`" -u root compozit_check < D:\backups\compozit\compozitsuite-2026-08-31_010000.sql"

# 3. Does it actually contain what you expect?
& $mysql -u root -t -e "SELECT COUNT(*) AS users FROM compozit_check.users;"
& $mysql -u root -t -e "SELECT COUNT(*) AS tables_restored FROM information_schema.tables WHERE table_schema='compozit_check';"
```

Only once the scratch copy looks right, replace the live database:

```powershell
& $mysql -u root -e "DROP DATABASE compozitsuite; CREATE DATABASE compozitsuite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cmd /c "`"$mysql`" -u root compozitsuite < D:\backups\compozit\compozitsuite-2026-08-31_010000.sql"

# Tidy up and come back online
& $mysql -u root -e "DROP DATABASE compozit_check;"
php artisan migrate --force
php artisan optimize
php artisan up
```

`migrate --force` after the restore is not optional: if the dump predates the code now deployed, it
applies the structure changes the running application expects.

> **Do this drill once, on purpose, before you need it.** Restore last night's backup into a scratch
> database on a quiet afternoon. A restore procedure first attempted during a real emergency is
> where restores fail.

---

## 8. Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| `deploy.ps1`: *"APP_ENV is 'local', not 'production'"* | `.env` was copied from a dev machine | Set `APP_ENV=production` and `APP_DEBUG=false` |
| `deploy.ps1` stopped; users see a 503 | A step failed and it stayed down on purpose | Fix the error shown, re-run `.\deploy.ps1`. To come back up regardless: `php artisan up` |
| Site loads but has **no styling** | `public/build` missing — the frontend was never compiled | `npm ci; npm run build`. Do not use `-SkipBuild` |
| Page shows raw PHP or a directory listing | `DocumentRoot` is the project root, or `mod_rewrite` is off | Re-check §3.7 — it must end in `/public`, with `AllowOverride All` |
| Reachable on the server, not from other PCs | Firewall, or Apache is not on 8787 | §3.8; confirm `Listen 8787` in `httpd.conf` |
| Vhost changes keep disappearing | You edited an `auto.*.conf`, which Laragon regenerates | Use a filename without the `auto.` prefix — §3.7 |
| Login always fails, no error | Signing in with the email | The login is the **employee ID** |
| Login "succeeds" then returns to the login page | `SESSION_SECURE_COOKIE=true` without HTTPS | Set it to `false` — this deployment is plain HTTP |
| Uploaded files 404 | `storage:link` ran without admin rights | Re-run it from an elevated terminal — §3.6 |
| `.env` change has no effect | Config is cached | `php artisan optimize` (or `config:clear` while diagnosing) |
| Backup: *"you need the PROCESS privilege"* | An old build lacking `--no-tablespaces` | Deploy current code; the flag is now always passed |
| Restore: *"ERROR 3546 … GTID_PURGED cannot be changed"* | The dump was taken by an old build lacking `--set-gtid-purged=OFF` | Delete that line near the top of the `.sql` and re-run; newer dumps do not contain it |
| Nightly backup never happens | The scheduler task is not running as configured | §3.10, then `php artisan schedule:list` |
| Everything is slow after a code change | Caches hold the old code | `php artisan optimize` |

Logs: `storage\logs\laravel.log` (application) and
`C:\laragon\etc\apache2\logs\compozit-error.log` (web server).

---

## 9. Glossary

For readers who do not work with Laravel day to day.

**artisan** — the app's command-line tool. Everything starting `php artisan` is a built-in command
run from the project folder.

**Migration** — a versioned change to the database *structure* (a new table, a new column).
`migrate` applies any that have not run yet; it never repeats one.

**Seeder** — a script that inserts *reference data* (roles, permissions, job titles). The two used
in production are safe to re-run: they add what is missing and change nothing else.

**Composer / npm** — library installers. Composer for the PHP back end, npm for the React front end.

**Build (`npm run build`)** — compiles the React source into the plain CSS and JavaScript browsers
load, written to `public/build`. Skip it and the site loads with no styling.

**Queue worker** — a permanently running process that performs slow work in the background. Idle in
this app today.

**Scheduler** — Laravel's own timetable, defined in `routes/console.php`. One Windows task calls it
every minute; it decides what is actually due.

**Maintenance mode** (`artisan down` / `up`) — serves everyone a temporary "be right back" page while
a deploy is in progress.

**`optimize`** — pre-compiles configuration and routes into a cache for speed. Because it is a cache,
`.env` edits do not take effect until you run it again.
