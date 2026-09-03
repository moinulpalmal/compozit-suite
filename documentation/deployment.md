# Deployment — Complete Guide

> **Scope.** This file is the *procedure*: everything you type, in order, to take Compozit Suite
> from nothing to running and supervised — on your own PC, or on a server the office uses. Where the
> code lives and what it is called is [`ARCHITECTURE.md`](../ARCHITECTURE.md)'s job, and this file
> links to it rather than restating it, so a decision never has two copies that can disagree.
>
> Unlike the other files in `documentation/` it describes an operation, not a module. See
> [`ARCHITECTURE.md §14`](../ARCHITECTURE.md#14-module-reference-documentation).

## How to use this file

**If you are a person doing this for the first time:** read [§1](#1-what-you-are-building) so the
shape is clear, then work through [§2](#2-prerequisites) and [§4](#4-deploying-to-a-server) in
order. Do not skip steps, and do not reorder them — several exist only to stop a later step failing
in a way that is hard to read. Anything you do not recognise is in the [glossary](#10-glossary).

**If you are an autonomous agent:** every step below is a literal command with a stated pass
condition. Run them in order, check the pass condition before continuing, and stop on the first
failure rather than working around it. Steps that need a human decision are marked **[DECISION]**.
Steps that need administrator rights are marked **[ADMIN]**. Do not invent alternative commands when
one fails; the [troubleshooting table](#9-troubleshooting) covers the failures that actually happen.

**Verification status.** Everything here has been executed except where marked ***not verified***.
The commands in [§4](#4-deploying-to-a-server), [§5](#5-the-service-layer) and
[§7](#7-updating-a-running-server) were run end to end against a full production rehearsal — a
separate clone served by Apache on its own port and database, exercised over real HTTP rather than
`artisan serve`. That rehearsal is how the two `php.ini` settings in [§2.3](#23-phpini--two-settings-that-are-not-optional)
were found; both break the application in production while being invisible in development.

---

## 1. What you are building

Compozit Suite is a Laravel monolith with an Inertia + React front end. A working deployment is four
things, and **it is not one program you start**:

| Piece | What runs it | Managed by |
| --- | --- | --- |
| **Web server** — serves every page | Apache, from `public/` | Laragon or XAMPP |
| **Database** — all application data, plus sessions, cache and the job queue | MySQL | Laragon or XAMPP |
| **Queue worker** — runs background jobs | `php artisan queue:work` | `deploy/start.bat` |
| **Scheduler** — fires timed tasks | `php artisan schedule:work` | `deploy/start.bat` |

The last two are what `deploy/` exists for. Apache and MySQL are *not* started by it: they already
belong to Laragon or XAMPP, and two managers fighting over one service is worse than none. So the
running order is always **Laragon/XAMPP first, then `start.bat`** — and `start.bat` checks and tells
you if you got it the wrong way round.

> **The queue is currently idle, and that is fine.** The application dispatches no jobs today and
> schedules no tasks. The worker and scheduler still run, because the alternative is that the first
> job somebody adds silently never executes and nobody notices for a week. Both were verified
> working: a dispatched job was picked up and completed in under three seconds.

### 1.1 Two deployments, two shapes

| | Your own PC | A server |
| --- | --- | --- |
| Started by | `composer run dev`, by hand | Laragon/XAMPP + `deploy/start.bat` |
| Address | `http://localhost:8000` | `http://<static-ip>:8787` |
| Reached by | only you | everyone on the LAN |
| `APP_DEBUG` | `true` | **`false`** |
| Front end | built live by Vite | pre-built into `public/build` |
| `php artisan optimize` | **never** — see [§3.3](#33-never-run-php-artisan-optimize-on-a-development-machine) | **required** |
| Queue worker | included in `composer run dev` | `deploy/start.bat` |

---

## 2. Prerequisites

### 2.1 Software

| Software | Why | Check |
| --- | --- | --- |
| [Laragon](https://laragon.org/) or [XAMPP](https://www.apachefriends.org/) | Apache + MySQL | — |
| **PHP 8.3+** (8.4 recommended) | The application requires `^8.3` | `php --version` |
| [Node.js 22+](https://nodejs.org/) | Builds the front end. **Not** bundled with Laragon | `node --version` |
| [Composer](https://getcomposer.org/) | Installs PHP libraries | `composer --version` |
| [Git](https://git-scm.com/download/win) | Fetches the code | `git --version` |
| Windows PowerShell 5.1 | Runs `deploy/*.bat`. Ships with Windows | `$PSVersionTable.PSVersion` |

**PHP must be on the system PATH.** In Laragon: **Menu → Tools → Path → Add Laragon to Path**. Then
check in a **newly opened** terminal — a window opened before the change still has the old PATH.

> **Not cosmetic, and it fails confusingly.** Composer's `composer.bat` shells out to `php`, and the
> Vite build shells out to `php artisan wayfinder:generate`. Without PHP on the PATH, `npm run build`
> stops with an error naming **Wayfinder** and never mentions PHP.

### 2.2 PHP extensions

`composer.json` names only `ext-dom` and `ext-zip`, but PhpSpreadsheet — a hard requirement of the
BQS importer — pulls in a much longer list. Check all of them at once:

```powershell
php -r "foreach (explode(',','ctype,curl,dom,fileinfo,filter,gd,iconv,json,libxml,mbstring,openssl,pdo_mysql,simplexml,tokenizer,xml,xmlreader,xmlwriter,zip,zlib') as `$e) { if (!extension_loaded(`$e)) echo 'MISSING: '.`$e.PHP_EOL; } echo function_exists('proc_open') ? 'proc_open OK' : 'proc_open DISABLED';"
```

**Pass condition:** no `MISSING:` lines, and `proc_open OK`.

Two need special attention:

- **`zip`** — Laragon ships `php_zip.dll` but leaves the line commented in `php.ini`. Change
  `;extension=zip` to `extension=zip`. Without it **every** purchase-order import fails with
  `Class "ZipArchive" not found`, including `.doc` and `.pdf`, because both are converted to `.docx`
  before they are read.
- **`gd`** — required by PhpSpreadsheet. Easy to miss, because nothing in this application draws an
  image; the failure is a load error on the BQS import path.

`proc_open` must not be in `disable_functions`: the parser uses it to launch LibreOffice and
`pdftotext`.

### 2.3 `php.ini` — two settings that are not optional

**These two break the application in production and cannot be seen in development**, because
`composer run dev` runs PHP as you, while a deployment runs it as the web server.

| Setting | Value | Consequence of getting it wrong |
| --- | --- | --- |
| **`upload_tmp_dir`** | a writable directory | **Every upload returns HTTP 500** |
| **`max_file_uploads`** | **25** — above the app's limit of 20 | The 21st file of a batch is destroyed silently |
| `upload_max_filesize` | ≥ `20M` | PO import caps at 20 MB; the document library has no cap of its own, so this becomes its cap |
| `post_max_size` | ≥ `64M` | Must cover a whole 20-file batch, not one file |
| `memory_limit` | `512M` | PhpSpreadsheet reads a BQS of up to 5000 rows inside the request |
| `max_execution_time` | ≥ `180` | Parsing runs inside the request. A `.doc` import measured **5.9 seconds** |

#### `upload_tmp_dir` — the one that breaks everything

Laragon's `fcgid.conf` sets `FcgidInitialEnv TEMP "C:/Windows/Temp"`, and `C:\Windows\Temp` is not
writable by the account Apache runs as. With `upload_tmp_dir` empty, PHP falls back to it, cannot
keep the uploaded temp file, and hands the application an `UploadedFile` whose `getRealPath()` is an
empty string. What reaches the log is:

```
production.ERROR: Path must not be empty
  #0 .../Illuminate/Filesystem/FilesystemAdapter.php(503): fopen('', 'r')
  #3 .../app/Services/Merchandising/PurchaseOrderImportService.php(123)
```

It names `fopen` and a filesystem adapter, so it reads as a storage-permissions bug in the
application. **It is not — nothing in the application is wrong.** Fix it in `php.ini`:

```ini
upload_tmp_dir = "D:/Projects/laragon/tmp"
```

Any directory the web server can write to will do; create it first. Restart Apache, then verify
**from a page served by Apache** — the CLI reports your own temp directory and looks fine either way.

#### `max_file_uploads` must be *higher* than the batch limit

`config/merchandising-documents.php` sets `max_files_per_batch` to 20 and validates against it. That
validation cannot fire while PHP's `max_file_uploads` is also 20: PHP discards surplus files *before
any PHP code runs*, so a 21-file batch arrives as exactly 20, passes `max:20`, and stores as a
success. **Measured: 21 sent, 20 stored, the 21st gone, success page shown.** With
`max_file_uploads = 25` all 21 arrive, the rule rejects the batch, and the user is told.

### 2.4 Document parsing — one extension, two programs

Purchase orders are imported by parsing the buyer's own document. Each format needs a different
thing, and each failure names the `.env` key to set, so a machine without LibreOffice still imports
`.docx` and `.pdf` normally.

| Need | For | Required? |
| --- | --- | --- |
| `ext-zip` | `.docx` | **Yes** — also the format the other two are converted into |
| [LibreOffice](https://www.libreoffice.org/download/) | `.doc`, `.rtf` | Only those formats |
| [Xpdf tools](https://www.xpdfreader.com/download.html) | `.pdf` | Only that format |

```powershell
winget install --id TheDocumentFoundation.LibreOffice -e
```

Xpdf has no winget package: download the Windows binaries and unzip them somewhere permanent —
`C:\xpdf\` is what `config/po-parser.php`'s comments assume.

> **`pdftotext` must be the XPDF build, not Poppler.** Both install under that name and both accept
> `-layout`, but their column output is not byte-identical — and this parser reads column positions.
>
> ```powershell
> pdftotext -v      # expect: Copyright 1996-2017 Glyph & Cog, LLC
> ```
>
> Glyph & Cog is Xpdf's vendor, so that banner is the right one. Poppler's banner names Poppler.
>
> **Git for Windows bundles a correct Xpdf `pdftotext`** at
> `C:\Program Files\Git\mingw64\bin\pdftotext.exe`. It works — but a server must not depend on a path
> inside Git. Install Xpdf properly there.

Point the application at both in `.env`, with **single quotes and forward slashes** — dotenv reads
`\P` in a double-quoted value as an escape sequence and refuses to parse the whole file:

```dotenv
LIBREOFFICE_BIN='C:/Program Files/LibreOffice/program/soffice.exe'
PDFTOTEXT_BIN='C:/xpdf/bin64/pdftotext.exe'
```

Then confirm the application agrees:

```powershell
php artisan config:clear
php artisan config:show po-parser.libreoffice
php artisan config:show po-parser.pdftotext
```

---

## 3. Running it on your own PC

A development setup: you start it when you want it, it stops when you close the terminal.

### 3.1 Database, code, configuration

```sql
CREATE DATABASE compozitsuite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Laragon's MySQL console is **Menu → MySQL → CLI**; its `root` account has no password by default.

```powershell
cd C:\laragon\www
git clone https://github.com/moinulpalmal/compozit-suite.git
cd compozit-suite
composer install
npm install
Copy-Item .env.example .env
notepad .env
```

`.env.example` ships configured for SQLite. Replace the whole `DB_` block — note the `#` marks go:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=compozitsuite
DB_USERNAME=root
DB_PASSWORD=
```

### 3.2 Build and run

```powershell
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
composer run dev
```

`composer run dev` starts three things at once — the PHP server, a queue listener and Vite — so on a
development PC you do **not** need `deploy/start.bat`. The application is at
**http://localhost:8000**. Port 8080 is Laragon's own landing page, not this app.

Sign in with employee ID **`15868`**, password **`password`**. Stop with `Ctrl+C`.

> `storage:link` needs **no administrator rights**. On Windows, Laravel creates a directory junction
> rather than a symbolic link, and that works as an ordinary user.

### 3.3 Never run `php artisan optimize` on a development machine

> **This command is destructive here, and the damage does not look like a caching problem.**
> `optimize` writes `bootstrap/cache/config.php`. Laravel then short-circuits on that file and
> ignores the `<env>` entries in `phpunit.xml`, so `php artisan test` stops using in-memory SQLite
> and runs `migrate:fresh` against **your MySQL development database**. That has already emptied
> `compozitsuite` twice. The full account, including the two guards now in place, is
> [`ARCHITECTURE.md §13.1`](../ARCHITECTURE.md#131-never-run-the-suite-with-a-cached-config--and-it-can-no-longer-happen).

To make a `.env` change take effect, use `php artisan config:clear`, which removes the cache instead
of writing one. `optimize` belongs on a server, where nobody runs tests.

---

## 4. Deploying to a server

One Windows machine on the LAN. People reach it at `http://<server-ip>:8787` from their own PCs.

> **`composer run dev` is not how you run a server.** It shows full error pages containing database
> credentials, and it stops the moment the terminal closes.

### 4.1 Survey the machine first

A server usually already does something. Find out what before adding to it.

```powershell
php -v
netstat -ano | Select-String ":80 |:3306 |:8787 "
Get-Service | Where-Object { $_.Name -match 'apache|mysql|httpd' }
Get-CimInstance Win32_LogicalDisk | Select-Object DeviceID, @{n='FreeGB';e={[int]($_.FreeSpace/1GB)}}
```

**[DECISION]** If PHP is older than 8.3, stop. Upgrading the PHP of a stack that already serves
another application changes that application's runtime, and that is not a decision to take while
deploying something else.

If antivirus is installed — **Kaspersky, Defender, anything** — add exclusions now, before any
upload test:

- the project directory,
- `soffice.exe`, `pdftotext.exe`.

> **Endpoint protection will block the parser and it will not look like antivirus.** The importer
> launches an office suite *from the web server process*, which is textbook malicious behaviour to a
> HIPS engine. What the user sees is `LibreOffice could not be executed` — indistinguishable from a
> wrong path in `.env`.

### 4.2 Give the machine a fixed address

Set a static IP, or a DHCP reservation on the router. That address goes into `.env` as `APP_URL` and
the application builds links from it. If it changes later, edit `.env` and re-run `php artisan
optimize`, or the app produces links to an address nobody answers.

### 4.3 Create the database and a dedicated user

**Use a dedicated user, not `root`.**

```sql
CREATE DATABASE compozitsuite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'compozit'@'localhost' IDENTIFIED BY 'put-a-real-password-here';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES,
      CREATE VIEW, SHOW VIEW, TRIGGER, CREATE ROUTINE, ALTER ROUTINE, EXECUTE,
      LOCK TABLES
   ON compozitsuite.* TO 'compozit'@'localhost';

FLUSH PRIVILEGES;
```

That grant list is verified sufficient: every migration and every seeder below runs under it, and
nothing more is needed.

### 4.4 Get the code — clone, never copy

```powershell
cd C:\laragon\www
git clone https://github.com/moinulpalmal/compozit-suite.git
cd compozit-suite
```

> **Copying the folder from a development PC breaks the site in a way you cannot see.** Four
> git-ignored files exist in a working tree precisely because they must not travel:
>
> | File | If copied |
> | --- | --- |
> | `public/hot` | Laravel serves every asset from `http://localhost:5173`. The site loads unstyled for every user and nothing in the logs says why |
> | `bootstrap/cache/config.php` | Bakes the development database credentials and `app.env=local` |
> | `.env` | `APP_DEBUG=true` publishes your database password on every error page |
> | `public/build` | Stale assets against fresh code |
>
> `git clone` excludes all four. That is the reason for the rule.

### 4.5 Configure

```powershell
Copy-Item .env.example .env
notepad .env
```

Apply the same `DB_` block replacement as [§3.1](#31-database-code-configuration), using `compozit`
and the password you just set, plus:

| Setting | Value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `http://<your-server-ip>:8787` |
| `LOG_LEVEL` | `warning` |
| `MAIL_MAILER` | `smtp`, plus real `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_FROM_ADDRESS` |
| `LIBREOFFICE_BIN`, `PDFTOTEXT_BIN` | as [§2.4](#24-document-parsing--one-extension-two-programs) |

> **If a password contains `#`, quote it**: `DB_PASSWORD="pa#ss"`. Unquoted, dotenv reads `#` as the
> start of a comment and the password silently truncates.

> **Leaving `MAIL_MAILER=log` is a decision, not a default.** Password reset is an enabled feature,
> so the "forgot password" screen will appear to work while the mail goes to
> `storage/logs/laravel.log` and nobody receives anything. If you have no SMTP server, tell staff
> that an administrator resets passwords.

```powershell
php artisan key:generate
```

### 4.6 Install and build

```powershell
composer install
npm ci
npm run build
php artisan migrate --force
```

> **Yes, plain `composer install` — not `--no-dev`.** The seeder in the next step builds its users
> through model factories, and factories need `fakerphp/faker`, a development dependency.
> [§4.8](#48-return-the-machine-to-a-production-install) strips them back out afterwards.
> `--force` on `migrate` means "yes, really, in production".

`npm run build` needs internet: the font plugin fetches Instrument Sans and self-hosts it into
`public/build`. **After the build there is no runtime internet requirement at all.**

### 4.7 Seed the database

```powershell
php artisan db:seed --force
```

This inserts the roles and permissions, 17 job titles, 10 buyers, and **three accounts**.

> **This is repeatable.** `DatabaseSeeder` looks each account up by `employee_id` before creating
> it, so a second run is a no-op rather than a `Duplicate entry` failure. Verified by running it
> twice against a seeded database: exit 0, user count unchanged.

#### Three accounts, and two must not survive go-live

| Employee ID | Email | Role | Password |
| --- | --- | --- | --- |
| **15868** | `test@example.com` | Super Admin | `password` |
| 20001 | `merchandiser@example.com` | merchandiser | `password` |
| 20002 | `documents@example.com` | document-uploader | `password` |

`20001` and `20002` exist so a developer can log in either side of the document library's permission
boundary. **They have real buyer grants and a known password** — fine on a laptop, not fine on a
server the whole office can reach.

**[DECISION] Delete or disable both before anyone else gets the URL.** Nothing on screen points at
them, so this is easy to forget.

### 4.8 Return the machine to a production install

```powershell
composer install --no-dev --optimize-autoloader
php artisan storage:link
```

This removes the test framework, the static analyser and the other development-only packages, and
rebuilds the autoloader for speed. Do not skip it.

### 4.9 Point Apache at the application

**Stop Laragon/XAMPP first.** Two edits.

**a. Listen on 8787.** In `httpd.conf` (Laragon:
`C:\laragon\bin\apache\<version>\conf\httpd.conf`; XAMPP: `C:\xampp\apache\conf\httpd.conf`), find
the existing `Listen` line and add below it:

```apache
Listen 8787
```

**b. Create the site.** A **new** file named `compozit-production.conf` — Laragon:
`C:\laragon\etc\apache2\sites-enabled\`; XAMPP: `C:\xampp\apache\conf\extra\`, and add an
`Include` for it in `httpd.conf`.

```apache
<VirtualHost *:8787>
    DocumentRoot "C:/laragon/www/compozit-suite/public"

    <Directory "C:/laragon/www/compozit-suite/public">
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/compozit-error.log"
    CustomLog "logs/compozit-access.log" common
</VirtualHost>
```

The log paths are relative to Apache's own directory, so they work on either stack without editing.

> **Do not create this file with Notepad's Save dialog.** *Save as type* defaults to "Text Documents
> (\*.txt)" and silently saves it as **`compozit-production.conf.txt`**. Apache never matches that
> name, so the site does not exist — and the symptom is not an error, it is the stack's own landing
> page on 8787. Create it from PowerShell instead:
>
> ```powershell
> notepad C:\laragon\etc\apache2\sites-enabled\compozit-production.conf
> ```
>
> Notepad offers to create it and the name is then already fixed. Confirm with `Get-ChildItem` —
> Explorer hides known extensions and shows both files identically.

> **Do not edit any `auto.*.conf` already in that folder.** Laragon generates those itself and
> overwrites them on restart. Any filename without the `auto.` prefix is yours to keep.

Three things must hold:

- `DocumentRoot` ends in **`/public`**. Pointing it at the project root publishes `.env`, with your
  database password in it, to the whole network.
- `AllowOverride All`, so the shipped `public/.htaccess` can route requests.
- `mod_rewrite` enabled — Laragon: **Menu → Apache → modules → rewrite_module**.

Start the stack again — **Stop, then Start.** "Reload" does not reliably pick up a new site file.
Then confirm Apache actually read your file:

```powershell
& 'C:\laragon\bin\apache\<version>\bin\httpd.exe' -S
```

**Pass condition: a line beginning `*:8787` appears, naming `compozit-production.conf`.** If 8787 is
absent, Apache never read the file — fix the name and location before going on. Everything after
this point will otherwise appear to work while serving the wrong site.

### 4.10 Cache the configuration

```powershell
php artisan optimize
```

**After `.env` is final, because it caches it.** Any later `.env` change needs `php artisan optimize`
again before it takes effect.

Then, in a browser **on the server itself**, open `http://localhost:8787/up`. A green health page
means Apache, PHP and the database are wired up correctly.

### 4.11 Secure the seeded accounts — before opening the firewall

Do this in exactly this order. Until the firewall rule exists, the server is reachable only from
itself, so the shipped passwords are never exposed on the network.

1. Sign in at `http://localhost:8787` as **`15868`** / **`password`**.
2. Change the password immediately: **Settings → Security**.
3. Set the real name and email: **Settings → Profile**.
4. Delete or disable **20001** and **20002** ([§4.7](#47-seed-the-database)).

> **The employee ID stays `15868` permanently.** It is set at creation, no screen edits it, and
> deleting the account does not release it — the ID and email stay reserved by unique indexes
> ([`ARCHITECTURE.md §9.6`](../ARCHITECTURE.md#96-authentication-identity)). Change everything else
> and use it as your administrator.

### 4.12 [ADMIN] Open the port

```powershell
New-NetFirewallRule -DisplayName "Compozit Suite (8787)" -Direction Inbound `
  -Protocol TCP -LocalPort 8787 -Profile Private -Action Allow
```

`-Profile Private` opens the port to the office network but not to a public Wi-Fi the machine might
join later. ***Not verified*** — this needs administrator rights, and the rehearsal machine had the
Private and Public firewall profiles disabled, so there was nothing to test against.

---

## 5. The service layer

Apache and MySQL are running, but the application's own background processes are not. That is what
`deploy/` is for.

### 5.1 What is in `deploy/`

| File | Purpose |
| --- | --- |
| **`manage.bat`** | **Interactive console. Start here if you are not sure what you need** |
| `start.bat` | Start the queue worker(s) and the scheduler |
| `stop.bat` | Stop them, letting an in-flight job finish first |
| `restart.bat` | Both. **Run this after every deployment** |
| `status.bat` | Report on everything, managed or not. Exit code 0 = healthy |
| `compozit.ps1` | All the logic. Every `.bat` is a wrapper a few lines long |
| `compozit.config.ps1` | Settings. Everything auto-detects; usually needs no edit |

The four verb scripts exist so that an update procedure or a scheduled task can call one thing and
check its exit code. **A person should use `manage.bat`.**

### 5.1.1 The console — `manage.bat`

Double-click it, or run it from a terminal. It shows live status and redraws after every action, so
you can see what your last choice actually did.

```
  ===========================================================
     COMPOZIT SUITE  -  Operations Console
  ===========================================================
     C:\laragon\www\compozit-suite
     http://192.168.5.99:8787

   [ UP ]  MySQL        127.0.0.1:3306   (Laragon/XAMPP)
   [ UP ]  Web server   192.168.5.99:8787   (Laragon/XAMPP)
   [ UP ]  queue-1      php PID 33736
   [ UP ]  scheduler    php PID 27080
   [ UP ]  Health       GET /up -> 200

  -----------------------------------------------------------
   1  Start                 5  View logs
   2  Stop                  6  Failed jobs
   3  Restart               7  Maintenance mode  [off]
   4  Refresh               8  Start-at-boot task

   Q  Quit
  -----------------------------------------------------------
```

Rows marked `(Laragon/XAMPP)` are **reported, not managed** — nothing in this console starts or
stops Apache or MySQL.

| Option | Does |
| --- | --- |
| 1 / 2 / 3 | Exactly what `start.bat` / `stop.bat` / `restart.bat` do |
| 4 | Re-read everything and redraw |
| 5 | Tail the queue worker, scheduler, or application log |
| 6 | List failed background jobs, and retry or delete them |
| 7 | Turn maintenance mode on or off. Turning it **on** requires typing `DOWN` |
| 8 | Register or remove the start-at-boot task ([§5.5](#55-admin-start-at-boot)) |

Deleting failed jobs requires typing `DELETE`. Both confirmations are literal and case-sensitive:
anything else cancels.

> **What the console deliberately does not do is deploy.** There is no menu option that runs `git
> pull`, `composer install` and `migrate`. That sequence is [§7](#7-updating-a-running-server), and
> it is a decision to make deliberately with the release in front of you, not a keypress away from
> "view logs" on a live server.

### 5.2 Configure — usually nothing to do

`compozit.config.ps1` derives the application path from its own location, finds `php` on the PATH,
and reads `APP_URL` out of `.env`. On a normal install **you do not need to edit it**.

Set `PhpExe` explicitly if PATH differs per account on your server — likely with XAMPP:

```powershell
PhpExe = 'C:/xampp/php/php.exe'
```

`status.bat` prints what it resolved, so run that first and only edit what is wrong.

### 5.3 Start it

```powershell
cd C:\laragon\www\compozit-suite\deploy
.\start.bat
```

```
Compozit Suite - starting
--------------------------------------------------------------
  [ OK ]   Database reachable (127.0.0.1:3306)
  [ OK ]   Web server listening (192.168.5.99:8787)
  [ OK ]   Queue worker 1 started (PID 12788)
  [ OK ]   Task scheduler started (PID 31132)
  [ OK ]   Application healthy at http://192.168.5.99:8787

  Started.
```

**Pass condition: exit code 0 and no `[FAIL]` lines.**

It refuses to start if MySQL is unreachable, because the queue driver is `database` and a worker
without a database just fails on every poll. If Apache is down it warns and starts anyway — the
workers do not need it.

Running `start.bat` twice is safe; the second run reports what is already up and starts nothing new.

### 5.4 Stop it

```powershell
.\stop.bat
```

Stopping is graceful where it matters. The queue worker is signalled with `queue:restart` so it
finishes the job in hand and exits — killing it mid-job would leave that job reserved until it timed
out. The scheduler holds nothing and is stopped immediately. A component that has not exited within
`StopGraceSeconds` (default 120) has its process tree terminated.

### 5.5 [ADMIN] Start at boot

Without this, the workers stop at the next reboot and nobody notices until a job does not run.

```powershell
powershell -ExecutionPolicy Bypass -File .\compozit.ps1 install-task
```

This registers a Scheduled Task that runs `start` 90 seconds after boot, as SYSTEM. The delay is so
Laragon/XAMPP's own services are listening before the workers start polling. Remove it with
`uninstall-task`.

> **Laragon and XAMPP do not start at boot by default either.** Set their own "run as service" or
> autostart option too, or the boot task will find nothing to connect to and refuse. `status.bat`
> after a reboot is the check.

***Not verified*** — registering the task needs administrator rights, which the rehearsal did not
have. The `start`, `stop`, `restart` and `status` verbs it invokes are all verified.

### 5.6 How it works, for when you need to debug it

Each component runs as a small PowerShell **supervising loop** that relaunches its `php.exe` child
whenever that child exits — on a crash, or on the hourly `--max-time` recycle. The loop's PID goes
in `deploy/run/<name>.pid`; the loop script itself is written to `deploy/run/<name>.loop.ps1` and is
readable.

Two implementation details are deliberate, and both were found by testing:

- **Processes are launched with `Win32_Process.Create`, not `Start-Process`.** A process started by
  `Start-Process` inherits the caller's console handles, so a detached worker holds the parent's
  stdout pipe open for its entire life. `start.bat > log.txt` then never returns — the command has
  finished, but the pipe never closes. This bites any script or agent that captures output.
- **Before killing anything, the recorded PID is checked against the running process's command
  line.** Windows recycles PIDs; without that check, `stop` could eventually kill an unrelated
  process that inherited the number.

Verified: killing a worker's `php.exe` outright had it relaunched by its loop in about two seconds.

```powershell
powershell -File .\compozit.ps1 logs -Lines 100
```

tails each component's log plus `laravel.log`. Component output is in
`storage/logs/queue-1.log` and `storage/logs/scheduler.log`.

---

## 6. Acceptance tests

Run all of these before telling anyone the URL. Tests 4–8 are the ones that matter — everything else
would also pass under `artisan serve`; those are the first time the parser binaries run under the
web server's own account.

| # | Test | Pass condition |
| --- | --- | --- |
| 1 | `http://localhost:8787/up` on the server | Green health page |
| 2 | Sign in as `15868` | Dashboard renders **with styling** — proves real built assets, no `public/hot` |
| 3 | Visit a nonsense URL | Generic 404. **No stack trace, no credentials.** If you see them, `APP_DEBUG` is still `true` |
| 4 | Import a **`.docx`** purchase order | Parses. Proves `ext-zip` under Apache |
| 5 | Import a **`.doc`** or `.rtf` | Parses. Proves LibreOffice spawns from the web server, can write under `storage/`, and is not blocked by antivirus |
| 6 | Import a **`.pdf`** | Parses. Proves the XPDF build |
| 7 | Import a **`.xlsx`** BQS | Parses. Proves `gd` via PhpSpreadsheet |
| 8 | Upload a **20-file** document batch, then try **21** | 20 succeeds; 21 is **rejected with a message**. If 21 "succeeds" with 20 stored, `max_file_uploads` is wrong — [§2.3](#23-phpini--two-settings-that-are-not-optional) |
| 9 | Download and preview an uploaded document | Proves `storage:link` and the permission-checked file route |
| 10 | Trigger a password reset | A real message arrives. Proves SMTP |
| 11 | `deploy\status.bat` | Exit 0, everything `[ OK ]` |
| 12 | From **another PC**: `http://<server-ip>:8787/up`, then sign in | Proves the firewall rule. The server itself would pass even with the firewall shut |

Fixtures for tests 4–7 are in `tests/Fixtures/Merchandising/`.

---

## 7. Updating a running server

```powershell
cd C:\laragon\www\compozit-suite

php artisan down
deploy\stop.bat

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class="Database\Seeders\Admin\RolePermissionSeeder" --force

php artisan optimize
deploy\start.bat
php artisan up
```

**`deploy\stop.bat` and `deploy\start.bat` are not optional here.** A queue worker loads the
application into memory once and keeps it — a worker left running across a deployment executes the
*old* code against the *new* database schema for as long as it lives.

`RolePermissionSeeder` is idempotent and re-syncs the permission catalogue, so a release that adds a
permission grants it automatically. It is the only seeder that belongs in an update: bare `db:seed`
would also re-assert the ten placeholder buyers and seventeen designations over whatever the office
has curated.

If a step fails, the site stays in maintenance mode. That is the right outcome — a 503 beats an ERP
serving real orders from half-updated code. Fix the error, re-run from the failed step, and finish
with `php artisan up`.

> **`/up` stays 200 during maintenance mode.** Laravel exempts the health route so monitors keep
> working, so it cannot tell you the site is down. Check `/` or `/login` instead.

---

## 8. Daily operation

**For anything on this list, `deploy\manage.bat` will do it from a menu.** The commands are given so
that scripts — and people who prefer typing — have them.

| Task | Command |
| --- | --- |
| Anything, interactively | **`deploy\manage.bat`** |
| Is everything up? | `deploy\status.bat` |
| Start after a reboot | Start Laragon/XAMPP, then `deploy\start.bat` |
| Stop for maintenance | `php artisan down`, then `deploy\stop.bat` |
| After changing `.env` | `php artisan optimize`, then `deploy\restart.bat` |
| Read the logs | `powershell -File deploy\compozit.ps1 logs -Lines 100` |
| Failed background jobs | `php artisan queue:failed`, then `php artisan queue:retry all` |

**Back up two things, together and off the server:** the `compozitsuite` database
(`mysqldump`) and `storage/app/private`. That directory is the *only* copy of every document staff
upload — the document library has no size cap and no retention policy, so also watch free disk.

---

## 9. Troubleshooting

Rows marked ✔ were reproduced while writing this file.

| Symptom | Cause | Fix |
| --- | --- | --- |
| ✔ **Every upload fails**, log says *"Path must not be empty"* at `fopen('', 'r')` | `upload_tmp_dir` unset, system temp not writable by Apache. Reads as an application storage bug; is not | [§2.3](#23-phpini--two-settings-that-are-not-optional) |
| ✔ A **21-file batch reports success but stores 20** | PHP's `max_file_uploads` equals the app's batch limit, so surplus files are discarded before validation can object | Set `max_file_uploads` **above** 20 — [§2.3](#23-phpini--two-settings-that-are-not-optional) |
| **Port 8787 shows the stack's own "Server is running" page** | No virtual host matched 8787, so Apache fell back to its main `DocumentRoot`. Not an error, so nothing is logged | `httpd.exe -S`; if `*:8787` is absent see the next two rows — [§4.9](#49-point-apache-at-the-application) |
| Site file looks right in Explorer but is never loaded | Notepad saved it as `.conf.txt`; Explorer hides the extension | `Get-ChildItem` the folder, then `Rename-Item` |
| `httpd.exe -S` lists 8787 but the browser gives **403** | `DocumentRoot` points somewhere that does not exist — often `C:/laragon/...` copied onto a machine where the stack is elsewhere | Correct both paths, forward slashes, ending in `/public` |
| ✔ `npm run build` fails naming **Wayfinder** | PHP is not on the PATH. The error never mentions PHP | [§2.1](#21-software), then open a **new** terminal |
| ✔ `db:seed` fails with *"Call to undefined function fake()"* | Run after `composer install --no-dev`; factories need faker | `composer install`, seed, then strip again — [§4.6](#46-install-and-build) |
| ✔ A `.env` change has no effect | Configuration is cached | `config:clear` on a PC, `optimize` on a server |
| Site loads but has **no styling** | `public/build` missing, or `public/hot` was copied from a dev machine | `npm ci; npm run build`, and delete `public/hot` |
| `LibreOffice could not be executed` | Wrong `LIBREOFFICE_BIN` — **or antivirus blocking the spawn**, which looks identical | Check the path, then add the antivirus exclusions in [§4.1](#41-survey-the-machine-first) |
| PDF imports produce garbled columns | `pdftotext` is Poppler, not XPDF | `pdftotext -v` — [§2.4](#24-document-parsing--one-extension-two-programs) |
| ✔ `start.bat` says *"Database unreachable"* | Laragon/XAMPP is not running, or MySQL is not started within it | Start the stack first. It is never started by `start.bat` |
| Jobs are queued but never run | The worker is not running, or was left running across a deployment | `deploy\status.bat`, then `deploy\restart.bat` |
| ✔ `php artisan down` is active but `/up` still returns 200 | Correct — Laravel exempts the health route from maintenance mode | Check `/` or `/login` instead |
| Works on the server, not from other PCs | Firewall rule missing, or Apache is not listening on 8787 | [§4.12](#412-admin-open-the-port); confirm `Listen 8787` |
| Login always fails, with no useful error | Signing in with the email address | The login is the **employee ID** |
| Everything breaks after a reboot | Laragon/XAMPP did not autostart, or the boot task is not registered | [§5.5](#55-admin-start-at-boot) |

Logs: `storage\logs\laravel.log` for the application, `storage\logs\queue-1.log` and
`scheduler.log` for the background processes, and Apache's own logs in its `logs\` directory.

---

## 10. Glossary

For readers who do not work with Laravel day to day.

**artisan** — the application's command-line tool. Everything starting `php artisan` is a built-in
command, run from the project folder.

**Migration** — a versioned change to the database *structure*: a new table, a new column. `migrate`
applies the ones that have not run yet and never repeats one.

**Seeder** — a script that inserts *starting data*: roles, permissions, job titles, buyers. All of
this application's seeders are safe to re-run.

**Composer / npm** — library installers. Composer for the PHP back end, npm for the React front end.

**Build (`npm run build`)** — compiles the React source into the plain CSS and JavaScript a browser
loads, written to `public/build`. Skip it on a server and the site loads with no styling.

**`optimize`** — pre-compiles configuration and routes into a cache, for speed. Because it is a
cache, `.env` edits do not take effect until you run it again. Server only —
[§3.3](#33-never-run-php-artisan-optimize-on-a-development-machine).

**Queue / job / worker** — a *job* is work deferred to run in the background. It is written to a
*queue* (here, a database table), and a *worker* is the long-running process that takes jobs off
that queue and runs them. Nothing happens without a worker.

**Scheduler** — Laravel's cron equivalent. `schedule:work` stays running and fires due tasks each
minute.

**Maintenance mode** (`artisan down` / `up`) — serves everyone a temporary "be right back" page
while an update is in progress.

**Virtual host (vhost)** — an Apache configuration block saying "for requests on this port, serve
files from this folder".
