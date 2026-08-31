# Agent Operating Contract

## 1. Read the map first

**Before planning or writing any code, read @ARCHITECTURE.md.**

It is the authoritative map of this repository: which module owns each concern, where every kind of
class belongs, how routes and pages are named, and which files must be edited to register a new
surface. Do not infer the layout by grepping — the file is authoritative, and guessing puts files
in the wrong place. Read it again at the start of every session; it changes.

If what you find on disk contradicts `ARCHITECTURE.md`, **the disk is the fact and the file is the
bug** — fix the file as part of your change and say so.

## 2. Keep the map in sync

`ARCHITECTURE.md` is **volatile**: it is expected to change as the project's structure and the
owner's preferences evolve. Keeping it accurate is part of the job, not a follow-up task.

**Update `ARCHITECTURE.md` in the same change** whenever you make a *definitive structural change*:

- a module is added, renamed, removed, or re-scoped
- a directory is created or removed at the top level or inside a module
- a route file is added, or a route prefix or naming convention changes
- a naming or placement convention is established or altered
- a cross-cutting mechanism is chosen — RBAC wiring, buyer scoping, audit logging
- an architecture-shaping dependency is added, removed, or upgraded
- layout resolution in `resources/js/app.tsx` changes
- a module's status marker changes (🟡 scaffolded → ✅ built)

[§12 of `ARCHITECTURE.md`](ARCHITECTURE.md#12-keeping-this-file-in-sync) maps each trigger to the
exact section to edit.

**Do not** touch it for ordinary feature work that follows conventions already recorded. A new
controller inside an existing module is not a structural change.

When you update it: edit the decision **in place** and state the reason. Never append a note that
contradicts an existing line and leaves both readings live. Never let a ⬜ placeholder survive a
change that resolves it. Say so in your summary when you have updated it — and say so when a change
was structural but you judged the file already covered it.

### This rule is machine-enforced

A `PostToolUse` hook (`.claude/hooks/architecture-sync.mjs`, wired in `.claude/settings.json`) runs
after every Write/Edit and pushes back when:

- **Rule A** — you touched a structural file (`routes/*.php`, `resources/js/app.tsx`,
  `composer.json`, `package.json`, `bootstrap/app.php`, `bootstrap/providers.php`) while
  `ARCHITECTURE.md` has no pending edits.
- **Rule B** — you wrote into a module directory whose name appears nowhere in `ARCHITECTURE.md`,
  i.e. an unregistered module.

Both are self-clearing: document the change and the hook goes quiet. It is a backstop for the rule
above, not a substitute for it — the hook cannot see a renamed convention or a resolved ⬜. If it
fires on something genuinely not structural, say why rather than working around it.

## 3. The owner's preferences are the source of truth

The structure in `ARCHITECTURE.md` reflects decisions the owner has made, not immutable law. When
they state a preference that conflicts with what is recorded there — about layout, naming, module
boundaries, or anything else — **the preference wins**. Update the file to match it in the same
turn, including the rationale, so the next agent inherits the decision rather than re-litigating it.

## 4. Project rules

- **Use `php artisan make:*`** for every new file, with `--no-interaction`. Do not hand-write class
  files that a generator produces.
- **Never edit `resources/js/actions/` or `resources/js/routes/`** — Wayfinder generates them.
- **Never nest `database/migrations/`** — the migrator does not recurse.
- **Every change needs a test.** Write or update one, then run it:
  `php artisan test --compact --filter=...`.
- **Run `vendor/bin/pint --dirty --format agent`** before finalizing any PHP change.
- **Do not add dependencies or create new top-level directories** without asking first.
- **Do not create documentation files** unless asked. `ARCHITECTURE.md` is the exception — keeping
  it current is required.
- The Laravel Boost guidelines below apply in full and are not superseded by this contract.

## 5. Where to start, by task

| Task | Start here |
| --- | --- |
| Anything at all | [`ARCHITECTURE.md §5`](ARCHITECTURE.md#5-module-registry) — find the owning module |
| Adding a feature | [`§10`](ARCHITECTURE.md#10-adding-a-feature-to-an-existing-module) — the end-to-end path |
| Adding a module | [`§11`](ARCHITECTURE.md#11-adding-a-new-module) — the registration checklist |
| Naming something | [`§6`](ARCHITECTURE.md#6-naming-conventions) |
| Deciding where logic goes | [`§7`](ARCHITECTURE.md#7-request-lifecycle) |
| Permissions, buyer scope, auditing | [`§9`](ARCHITECTURE.md#9-cross-cutting-concerns) |
| Running or testing the app | [`§13`](ARCHITECTURE.md#13-commands) |

---

playwright configuration:
APP BASE URL: http://localhost:8000
  (served by `composer run dev`, which runs `php artisan serve` + queue + vite concurrently.
   The URL is only live while that is running. Port 8080 is the Laragon landing page, not this app.)
Login Auth: 
User Name: test@example.com
Password: password
user this authentication for using playwright mcp server.
playwright trigger clause: if user write plarywright trigger command at prompt explicitly only than use it. 
PHP Path: D:\Projects\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64
  (php is not on PATH in the bash shell; invoke it via this full path or use PowerShell.)

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
