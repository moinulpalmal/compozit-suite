# Deployment — Runbook

> **Scope.** This file is the *procedure*: what to type, in order, to get Compozit Suite running —
> on your own PC, or on the office server. Where code lives and what it is called is
> [`ARCHITECTURE.md`](../ARCHITECTURE.md)'s job, and this file links to it rather than restating it,
> so a decision never has two copies that can disagree.
>
> Unlike the other files in this folder it describes an operation, not a module. See
> [`ARCHITECTURE.md §14`](../ARCHITECTURE.md#14-documentation).
>
> **What was actually tested.** Both halves were run end to end on Windows 11 under Laragon — PHP
> 8.4.12, MySQL 9.6.0, Apache 2.4.66 — into a throwaway clone and a throwaway database: install,
> configure, migrate, seed, build, cache, serve, and a real sign-in reaching the dashboard.
>
> Five commands could not be executed on that machine and are marked ***not verified*** where they
> appear — the firewall rule (needs administrator rights) and the `httpd.conf` edit (needs a restart
> of a machine that was in use), plus `git pull`, `artisan down` and `artisan up` in
> [§5](#5-updating-a-server-that-is-already-running), which need an already-deployed server.

---

## 1. Which half do you need?

| You want to | Go to |
| --- | --- |
| Work on the code on your own PC | [Part A](#3-part-a--run-it-on-your-own-pc) |
| Let the office reach it from their own PCs | [Part B](#4-part-b--serve-it-to-the-office) |
| Update a server that is already running | [§5](#5-updating-a-server-that-is-already-running) |

Part B assumes you have read Part A; it does not repeat the prerequisites. If you do not work with
Laravel day to day, read the [glossary](#7-glossary) first — it explains migrations, seeders and the
rest in a paragraph each.

> **Paths.** Everything below uses Laragon's default location, `C:\laragon`. On the machine this was
> written on Laragon lives at `D:\Projects\laragon` instead. Substitute whichever is yours — most
> commands fail loudly if you get it wrong, but the two paths inside the Apache site file in
> [§4.8](#48-point-apache-at-the-app-on-port-8787) fail *quietly*, so check those twice.

---

## 2. Prerequisites — both halves

| Software | Why |
| --- | --- |
| [Laragon](https://laragon.org/) | The Apache web server and the MySQL database |
| PHP 8.4 | Ships with Laragon. The application requires 8.3 or newer |
| [Node.js 22+](https://nodejs.org/) | Builds the React frontend. **Not** bundled with Laragon |
| [Composer](https://getcomposer.org/) | Installs the PHP libraries |
| [Git](https://git-scm.com/download/win) | Fetches the code |

**PHP must be on the system PATH.** In Laragon: **Menu → Tools → Path → Add Laragon to Path**. Then
check all four in a **newly opened** terminal — a window opened before the change still has the old
PATH:

```powershell
php --version        # expect 8.4.x
composer --version
node --version
git --version
```

> **This step is not cosmetic, and skipping it fails confusingly.** Composer's `composer.bat` shells
> out to `php`, and the Vite build shells out to `php artisan wayfinder:generate`. Without PHP on the
> PATH, `npm run dev` stops with an error naming **Wayfinder** and never mentions PHP. It is the
> first row of [§6](#6-troubleshooting) for that reason.

---

## 3. Part A — run it on your own PC

The result is a development setup: you start it when you want it, and it stops when you close the
terminal.

### 3.1 Create the database

Open Laragon's MySQL console (**Menu → MySQL → CLI**), or run
`C:\laragon\bin\mysql\<version>\bin\mysql.exe -u root`. Laragon's `root` account has **no password**
by default.

```sql
CREATE DATABASE compozitsuite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

On your own machine `root` is fine. The server gets a dedicated user instead — [§4.3](#43-create-the-database-and-a-dedicated-user).

### 3.2 Get the code and its libraries

```powershell
cd C:\laragon\www
git clone https://github.com/moinulpalmal/compozit-suite.git
cd compozit-suite
composer install
npm install
```

### 3.3 Configure

```powershell
copy .env.example .env
notepad .env
```

`.env.example` ships configured for SQLite. Replace this block:

```ini
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel
# DB_USERNAME=root
# DB_PASSWORD=
```

with this one — note the `#` marks are gone:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=compozitsuite
DB_USERNAME=root
DB_PASSWORD=
```

Then give the application its encryption key:

```powershell
php artisan key:generate
```

### 3.4 Build the database

```powershell
php artisan migrate
php artisan db:seed
php artisan storage:link
```

`db:seed` inserts the roles and permissions, the job titles, a list of buyers, and **the one account
you log in with**. It is a first-install command — see the warning in [§4.6](#46-seed-the-database--once-ever).

> `storage:link` needs **no administrator rights**. On Windows, Laravel creates a directory junction
> rather than a symbolic link, and that works as an ordinary user.

### 3.5 Run it

```powershell
composer run dev
```

That starts three things at once: the PHP server, a queue listener, and Vite. The application is at
**http://localhost:8000**. Port 8080 is Laragon's own landing page, not this app.

Sign in with employee ID **`15868`** and password **`password`**.

> **The login is the employee ID, not the email address.** That is deliberate —
> [`ARCHITECTURE.md §9.6`](../ARCHITECTURE.md#96-authentication-identity).

Stop it with `Ctrl+C`. The application is only live while that command is running.

> `composer run setup` ([`composer.json`](../composer.json)) chains install → key → migrate → npm →
> build into one command. It is *not* the first step, because it migrates before you have edited
> `.env` and would build a SQLite file instead of using MySQL. Once `.env` is correct it is a
> reasonable shortcut.

### 3.6 Do not run `php artisan optimize` on this machine

> **On a development machine this command is destructive, and the damage does not look like a
> caching problem.** `optimize` writes `bootstrap/cache/config.php`. Laravel then short-circuits on
> that file and ignores the `<env>` entries in `phpunit.xml`, so `php artisan test` no longer uses
> in-memory SQLite — it runs `migrate:fresh` against **your MySQL development database**. That has
> already emptied `compozitsuite` twice. The full account, including the two guards now in place, is
> [`ARCHITECTURE.md §13.1`](../ARCHITECTURE.md#131-never-run-the-suite-with-a-cached-config--and-it-can-no-longer-happen).

If you need a `.env` change to take effect, use `php artisan config:clear`, which removes the cache
instead of writing one. `optimize` belongs on the server only, where nobody runs tests.

---

## 4. Part B — serve it to the office

One Windows PC on the office LAN, running Laragon's Apache. No cloud, no containers. People reach it
at `http://<server-ip>:8787` from their own machines.

| | Your PC (Part A) | The server (Part B) |
| --- | --- | --- |
| Started by | `composer run dev`, by hand | Laragon's Apache, always on |
| Address | `http://localhost:8000` | `http://<static-lan-ip>:8787` |
| Reachable by | only you | everyone on the office LAN |
| `APP_DEBUG` | `true` | `false` |
| Frontend | built live by Vite | pre-built into `public/build` |
| `php artisan optimize` | **never** | **required** |

> **`composer run dev` is not how you run a server.** It shows full error pages containing database
> credentials, and it stops the moment the terminal closes.

### 4.1 Give the machine a fixed address

Set a static IP, or a DHCP reservation on the router. That address goes into `.env` as `APP_URL` and
the application builds links from it. If it ever changes, edit `.env` and re-run
`php artisan optimize`, or the app will produce links to an address nobody answers.

### 4.2 Get the code

```powershell
cd C:\laragon\www
git clone --branch main https://github.com/moinulpalmal/compozit-suite.git
cd compozit-suite
```

> **The server runs `main`, and only `main`.** `--branch` is written out rather than relying on the
> repository's default, so the command still does the right thing if that default ever moves. Feature
> work happens on `dev` and reaches the server by being merged into `main` — never by deploying `dev`
> directly. Confirm what you have with `git branch --show-current` before going on.

### 4.3 Create the database and a dedicated user

In Laragon's MySQL console. **Use a dedicated user here, not `root`.**

```sql
CREATE DATABASE compozitsuite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'compozit'@'localhost' IDENTIFIED BY 'put-a-real-password-here';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES,
      CREATE VIEW, SHOW VIEW, TRIGGER, CREATE ROUTINE, ALTER ROUTINE, EXECUTE,
      LOCK TABLES
   ON compozitsuite.* TO 'compozit'@'localhost';

FLUSH PRIVILEGES;
```

That grant list was tested against this application: every migration and every seeder in this file
runs under it, and nothing more is needed.

### 4.4 Configure

```powershell
copy .env.example .env
notepad .env
```

Apply the same `DB_` block replacement as [§3.3](#33-configure), using `compozit` and the password
you just set, and change these as well:

| Setting | Set it to |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `http://<your-server-ip>:8787` |
| `LOG_LEVEL` | `warning` |

Then:

```powershell
php artisan key:generate
```

### 4.5 Install and build

```powershell
composer install
npm ci
npm run build
php artisan migrate --force
```

> **Yes, plain `composer install` — not `--no-dev`.** The seeder in the next step builds its user
> through a model factory, and factories need `fakerphp/faker`, which is a development dependency.
> [§4.7](#47-return-the-machine-to-a-production-install) strips the development packages back out
> once the seeding is done. `--force` on `migrate` means "yes, really, in production".

### 4.6 Seed the database — once, ever

```powershell
php artisan db:seed --force
```

This inserts the roles and permissions, 17 job titles, 10 buyers, and the single account you will log
in with: employee ID **`15868`**, password **`password`**.

> **Run this exactly once, on a new installation, and never again.** It is not repeatable: the second
> run fails with `Duplicate entry 'test@example.com' for key 'users.users_email_unique'`. It is
> absent from the update procedure in [§5](#5-updating-a-server-that-is-already-running) for that
> reason.

> **`migrate:fresh` drops every table and everything in them.** There is no undo. It is never part of
> any procedure in this file.

### 4.7 Return the machine to a production install

```powershell
composer install --no-dev --optimize-autoloader
```

This removes the test framework, the static analyser and the other development-only packages that
[§4.5](#45-install-and-build) had to install, and rebuilds the autoloader for speed. Do not skip it.

```powershell
php artisan storage:link
```

### 4.8 Point Apache at the app on port 8787

Two edits. **Stop Laragon first.**

**a. Tell Apache to listen on 8787.** Open
`C:\laragon\bin\apache\<apache-version>\conf\httpd.conf`, find the line `Listen 8080`, and add below
it:

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
</VirtualHost>
```

> **Do not create this file with Notepad's Save dialog.** Its *Save as type* defaults to "Text
> Documents (\*.txt)" and silently saves `compozit-production.conf` as
> **`compozit-production.conf.txt`**. Apache includes `sites-enabled/*.conf` and never matches that
> name, so the site does not exist — and the symptom is not an error, it is Laragon's own "Server is
> running" page on port 8787 (see [§6](#6-troubleshooting)). Either choose *All Files* in the Save
> dialog, or create the file from PowerShell:
>
> ```powershell
> notepad C:\laragon\etc\apache2\sites-enabled\compozit-production.conf
> ```
>
> Notepad offers to create it, and the name is then already fixed. Confirm what you actually got with
> `Get-ChildItem C:\laragon\etc\apache2\sites-enabled` — Windows Explorer hides known extensions and
> will show both files as `compozit-production.conf`.

> **Do not edit the `auto.compozit-suite.test.conf` already in that folder.** Laragon generates every
> `auto.*.conf` itself and overwrites them, so changes there vanish on the next restart. Any filename
> without the `auto.` prefix is yours to keep — which is why the file above is not called one.

Three things must hold or the site will not work:

- `DocumentRoot` points at the project's **`public`** folder, never the project root. Pointing it at
  the root publishes `.env`, with your database password in it, to the whole network.
- `AllowOverride All`, so the shipped `public/.htaccess` can route requests.
- `mod_rewrite` is enabled — **Menu → Apache → modules → rewrite_module**.

Start Laragon again — **Stop, then Start.** "Reload" does not always pick up a new site file.

**Check that Apache actually loaded your site before going on.** This is the one step that catches a
misnamed or misplaced file, and it takes a second:

```powershell
cd C:\laragon\bin\apache\<apache-version>\bin
.\httpd.exe -S
```

The output lists every virtual host. **A line beginning `*:8787` must appear**, pointing at your
`compozit-production.conf`. If port 8787 is absent, Apache never read your file — go back and check
its name and folder, then look at the first two rows of [§6](#6-troubleshooting). Do not move on
until 8787 is listed: everything after this point will appear to work and serve the wrong site.

> The virtual-host block above is **verified**: it was loaded by Apache 2.4.66 using Laragon's own
> FastCGI PHP wiring and served this application correctly, including `.htaccess` routing and the
> pre-built assets. The `Listen 8787` edit and the Laragon restart around it are ***not verified*** —
> the test used a second Apache instance so it would not restart a machine that was in use.

### 4.9 Cache the configuration and check it locally

```powershell
php artisan optimize
```

Then, in a browser **on the server itself**, open `http://localhost:8787/up`. A green health page
means Apache, PHP and the database are all wired up correctly.

`optimize` comes **after** `.env` is final, because it caches it. Any later `.env` change needs
`php artisan optimize` again before it takes effect.

### 4.10 Secure the account before opening the firewall

Do this in exactly this order. Until the firewall rule exists the server is reachable only from
itself, so the shipped password is never exposed on the network.

1. On the server, open `http://localhost:8787` and sign in as **`15868`** / **`password`**.
2. Change the password immediately: **Settings → Security**.
3. Give the account its real identity: **Settings → Profile** for the name and email address.

> **The employee ID stays `15868` permanently.** It is set when the account is created and there is
> no screen that edits it, and deleting the account does not release it — the ID and the email
> address stay reserved by unique indexes, and reuse is refused
> ([`ARCHITECTURE.md §9.6`](../ARCHITECTURE.md#96-authentication-identity)). Change everything else
> and use it as your administrator; a "clean" employee ID is not available without a new database.

Only now open the port, in an **administrator** PowerShell:

```powershell
New-NetFirewallRule -DisplayName "Compozit Suite (8787)" -Direction Inbound `
  -Protocol TCP -LocalPort 8787 -Profile Private -Action Allow
```

`-Profile Private` matters: it opens the port to the office network but not to a public Wi-Fi the
machine might join later. *Not verified* — this command requires administrator rights that were not
available when this file was written.

### 4.11 Check it from somebody else's PC, then fix the placeholder data

From a **different computer on the LAN** — the server itself would pass even with the firewall shut:

1. `http://<server-ip>:8787/up` → a green health page.
2. `http://<server-ip>:8787` → the login screen, then sign in.

The seeded buyers (H&M, Zara, Primark, C&A, Next…) and job titles are **placeholders**, not your
lists. Replace them under **Admin → Buyers** and **Admin → Designations** before anyone enters real
work, or orders will end up attached to a buyer that does not exist.

---

## 5. Updating a server that is already running

```powershell
php artisan down

git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class="Database\Seeders\Admin\RolePermissionSeeder" --force

php artisan optimize
php artisan up
```

`RolePermissionSeeder` is idempotent and re-syncs the permission catalogue, so a release that adds a
permission grants it automatically. It is the only seeder that belongs here. Its idempotency was
tested: it ran three times against an already-seeded database without complaint or duplication. The
`--class` form is the one [`ARCHITECTURE.md §13`](../ARCHITECTURE.md#13-commands) already uses.

> `git pull`, `artisan down` and `artisan up` are ***not verified***: they need a deployed server to
> run against, and there was not one. The install, build and migrate commands in this block are the
> same ones executed in [§4](#4-part-b--serve-it-to-the-office).

> **Never run bare `php artisan db:seed` on an existing installation.** It is the first-install
> command from [§4.6](#46-seed-the-database--once-ever) and it will fail on the duplicate account —
> after having already re-run the other seeders.

If a step fails, the site stays in maintenance mode. That is the right outcome: a 503 page beats an
ERP serving real orders from half-updated code. Fix the error, re-run from the failed step, and
finish with `php artisan up`.

---

## 6. Troubleshooting

Rows marked ✔ were reproduced while writing this file.

| Symptom | Cause | Fix |
| --- | --- | --- |
| **Port 8787 shows Laragon's own "Server is running / PHP is enabled" page** | No virtual host matched 8787, so Apache fell back to its main `DocumentRoot`. Your site file was never loaded — it is not an error, so nothing appears in the logs | Run `httpd.exe -S`; if `*:8787` is absent, see the next two rows — [§4.8](#48-point-apache-at-the-app-on-port-8787) |
| Site file looks right in Explorer but is never loaded | Notepad saved it as `compozit-production.conf.txt`; Explorer hides the extension | `Get-ChildItem` the folder to see the real name, then `Rename-Item` it — [§4.8](#48-point-apache-at-the-app-on-port-8787) |
| `httpd.exe -S` lists 8787, but the browser gives **403 Forbidden** | The vhost loaded, but `DocumentRoot` points somewhere that does not exist — often `C:/laragon/...` copied verbatim onto a machine where Laragon is on another drive | Correct the two paths in the file to where you really cloned, forward slashes, ending in `/public` |
| ✔ `npm run dev` stops with *"Error generating types: Command failed: php artisan wayfinder:generate"* | PHP is not on the PATH. The error names Wayfinder, not PHP | [§2](#2-prerequisites--both-halves), then open a **new** terminal |
| ✔ `db:seed` stops with *"Call to undefined function … fake()"* | Ran after `composer install --no-dev`; factories need faker | `composer install`, seed, then strip again — [§4.5](#45-install-and-build) |
| ✔ `db:seed` stops with *"Duplicate entry 'test@example.com'"* | Already seeded. It is a one-time command | Nothing to fix — [§4.6](#46-seed-the-database--once-ever) |
| ✔ A `.env` change has no effect | The configuration is cached | `php artisan config:clear` on your PC, `php artisan optimize` on the server |
| ✔ Vite prints *"Port 5173 is in use, trying another one…"* | A second Vite is running — usually another copy of this project | Harmless by itself; stop the other one if you did not mean to run two |
| Site loads but has **no styling** | `public/build` is missing — the frontend was never compiled | `npm ci` then `npm run build` |
| Page shows raw PHP or a directory listing | `DocumentRoot` is the project root, or `mod_rewrite` is off | [§4.8](#48-point-apache-at-the-app-on-port-8787) — it must end in `/public` |
| Works on the server, not from other PCs | The firewall rule, or Apache is not listening on 8787 | [§4.10](#410-secure-the-account-before-opening-the-firewall); confirm `Listen 8787` |
| Virtual-host changes keep disappearing | You edited an `auto.*.conf`, which Laragon regenerates | Use a filename without the `auto.` prefix — [§4.8](#48-point-apache-at-the-app-on-port-8787) |
| Login always fails, with no useful error | Signing in with the email address | The login is the **employee ID** |
| The server is missing a feature you know was finished, or `git pull` reports nothing to do | The clone is on the wrong branch — `dev` work reaches the server only once merged into `main` | `git branch --show-current` must print `main` — [§4.2](#42-get-the-code) |

Logs: `storage\logs\laravel.log` for the application, and Apache's own logs under
`C:\laragon\etc\apache2\logs\` for the web server.

---

## 7. Glossary

For readers who do not work with Laravel day to day.

**artisan** — the application's command-line tool. Everything starting `php artisan` is a built-in
command, run from the project folder.

**Migration** — a versioned change to the database *structure*: a new table, a new column. `migrate`
applies the ones that have not run yet and never repeats one.

**Seeder** — a script that inserts *starting data*: roles, permissions, job titles, buyers. Most are
safe to re-run; the one that creates the login account is not.

**Composer / npm** — library installers. Composer for the PHP back end, npm for the React front end.

**Build (`npm run build`)** — compiles the React source into the plain CSS and JavaScript a browser
loads, written to `public/build`. Skip it on a server and the site loads with no styling.

**`optimize`** — pre-compiles configuration and routes into a cache, for speed. Because it is a
cache, `.env` edits do not take effect until you run it again. Server only — see
[§3.6](#36-do-not-run-php-artisan-optimize-on-this-machine).

**Maintenance mode** (`artisan down` / `up`) — serves everyone a temporary "be right back" page while
an update is in progress.
