<?php

/**
 * Run a git command against this repository.
 *
 * `git -C` rather than a `chdir()`, so the test leaves the process working
 * directory alone for whatever runs after it.
 *
 * @return array{status: int, lines: list<string>}
 */
function deployGit(string $arguments): array
{
    $output = [];

    exec(
        sprintf('git -C %s %s 2>&1', escapeshellarg(dirname(__DIR__, 2)), $arguments),
        $output,
        $status,
    );

    return [
        'status' => $status,
        'lines' => array_values(array_filter(array_map('trim', $output), 'strlen')),
    ];
}

/**
 * Whether these assertions can mean anything here.
 *
 * They ask git what it is tracking, so a source export, a `composer create-project`
 * install or a machine without git on PATH has nothing to answer with — and a
 * skipped test is the honest result, not a failure.
 */
function deployIsGitWorkTree(): bool
{
    $result = deployGit('rev-parse --is-inside-work-tree');

    return $result['status'] === 0 && ($result['lines'][0] ?? '') === 'true';
}

/*
 * `deploy/run/` is generated state, and `deploy/.gitignore` has said so from the
 * start. It was still wrong for eight commits: three `.loop.ps1` files were
 * committed in `8016c1e` before that rule existed, and git does not apply
 * `.gitignore` to a file it is already tracking — so the rule sat there reading
 * as enforcement while enforcing nothing, and the files were recommitted twice
 * more by people simply running `start.bat`.
 *
 * What that costs is not tidiness. `compozit.ps1` rewrites every loop script on
 * every `start` with the local machine's absolute paths and Apache build, so a
 * tracked copy arrives on a server wrong, is overwritten before Apache is ever
 * launched, and leaves a permanently dirty working tree. The next release runs
 * `documentation/deployment.md` §7 — `php artisan down`, then
 * `git pull --ff-only` — and the pull aborts with "local changes would be
 * overwritten", with the site already in maintenance mode.
 *
 * This test is the enforcement the `.gitignore` could not provide.
 */
test('nothing in deploy/run is tracked', function () {
    expect(deployGit('ls-files deploy/run')['lines'])->toBeEmpty(
        'deploy/run/ is generated per machine and rewritten on every start. A tracked file there '
        .'aborts `git pull --ff-only` on a deployed server. Remove it with `git rm --cached`.',
    );
})->skip(fn () => ! deployIsGitWorkTree(), 'Not a git work tree.');

test('the sample loop scripts are tracked as examples', function () {
    $samples = ['apache', 'queue-1', 'scheduler'];

    expect(deployGit('ls-files deploy/examples')['lines'])->toEqualCanonicalizing(
        array_map(fn (string $name): string => "deploy/examples/{$name}.loop.ps1", $samples),
    );

    foreach ($samples as $name) {
        expect(dirname(__DIR__, 2)."/deploy/examples/{$name}.loop.ps1")->toBeReadableFile();
    }
})->skip(fn () => ! deployIsGitWorkTree(), 'Not a git work tree.');
