#!/usr/bin/env node
/**
 * PostToolUse hook — enforces that ARCHITECTURE.md stays in sync with structural changes.
 *
 * Two independent rules. Either one firing feeds a `block` decision back to Claude
 * (on PostToolUse this is a strong nudge, not a hard stop — the turn continues).
 *
 *   Rule A — a file on the structural allowlist was touched (route files, app.tsx,
 *            dependency manifests, bootstrap), but ARCHITECTURE.md has no pending edits.
 *
 *   Rule B — a file was written into a module directory whose name does not appear
 *            anywhere in ARCHITECTURE.md, i.e. an unregistered module.
 *
 * Both rules are self-clearing: document the change and the hook goes quiet.
 * Any unexpected error exits 0 so a broken hook can never block real work.
 */

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';

/** Files whose contents define the application's shape rather than its features. */
const STRUCTURAL_FILES = [
    [/^routes\/[^/]+\.php$/, 'a route file (module registry / route prefixes)'],
    [/^resources\/js\/app\.tsx$/, 'Inertia layout resolution'],
    [/^(composer|package)\.json$/, 'the dependency manifest (stack table)'],
    [/^bootstrap\/(app|providers)\.php$/, 'the application bootstrap (middleware / providers)'],
];

/** Layer directories whose immediate child directory is a module name. */
const MODULE_LAYERS = [
    'app/Http/Controllers/',
    'app/Http/Requests/',
    'app/Models/',
    'app/Policies/',
    'app/Services/',
    'app/Actions/',
    'app/Enums/',
    'resources/js/pages/',
    'resources/js/components/',
    'database/factories/',
    'database/seeders/',
    'tests/Feature/',
];

const MAP_FILE = 'ARCHITECTURE.md';

function readStdin() {
    try {
        return readFileSync(0, 'utf8');
    } catch {
        return '';
    }
}

function git(root, args) {
    return execFileSync('git', args, { cwd: root, encoding: 'utf8' });
}

function block(reason) {
    process.stdout.write(
        JSON.stringify({
            decision: 'block',
            reason,
            hookSpecificOutput: { hookEventName: 'PostToolUse' },
        }),
    );
    process.exit(0);
}

function main() {
    const raw = readStdin();
    if (!raw.trim()) {
        return;
    }

    const payload = JSON.parse(raw);
    const filePath = payload?.tool_response?.filePath ?? payload?.tool_input?.file_path;
    if (!filePath) {
        return;
    }

    // Resolve from the session's directory, not the file's — the file's parent may not
    // exist yet, and a path outside the repo is caught by the `../` guard below.
    const root = git(process.cwd(), ['rev-parse', '--show-toplevel']).trim();

    // Repo-relative, forward slashes, so the patterns below are platform-independent.
    const rel = path.relative(root, path.resolve(filePath)).split(path.sep).join('/');
    if (!rel || rel.startsWith('../') || rel === MAP_FILE) {
        return;
    }

    // Rule A — structural file touched without a corresponding map edit.
    const structural = STRUCTURAL_FILES.find(([pattern]) => pattern.test(rel));
    if (structural) {
        const pending = git(root, ['status', '--porcelain', '--', MAP_FILE]).trim();
        if (!pending) {
            block(
                `Structural change: ${rel} defines ${structural[1]}, but ${MAP_FILE} has no pending edits. ` +
                    `CLAUDE.md requires the map to be updated in the same change. Update the relevant ` +
                    `section of ${MAP_FILE} now (module registry, directory tree, conventions, or stack), ` +
                    `or state plainly why this edit is not structural.`,
            );
        }
        return;
    }

    // Rule B — file written into a module directory the map has never heard of.
    const layer = MODULE_LAYERS.find((prefix) => rel.startsWith(prefix));
    if (!layer) {
        return;
    }

    const remainder = rel.slice(layer.length);
    const segments = remainder.split('/');
    if (segments.length < 2) {
        return; // File sits directly in the layer directory — no module involved.
    }

    const moduleName = segments[0];
    const map = readFileSync(path.join(root, MAP_FILE), 'utf8');

    // Require a path/namespace context — the name must sit next to a "/", follow a "\" or a
    // backtick — so a name that merely occurs in prose ("Quality gate") is not counted as
    // documented. Matches `Merchandising`, pages/merchandising/, Fortify/, Admin\Buyer.
    const escaped = moduleName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const documented = new RegExp('(?:[/`\\\\]' + escaped + '\\b|\\b' + escaped + '/)', 'i');
    if (documented.test(map)) {
        return;
    }

    block(
        `Unregistered module directory: ${rel} sits under "${moduleName}", which is not mentioned ` +
            `anywhere in ${MAP_FILE}. Add it to the module registry (§5) and the directory tree (§3), ` +
            `and work through the "Adding a new module" checklist (§11) — route file, layer directories, ` +
            `sidebar entry, permissions.`,
    );
}

try {
    main();
} catch {
    // Never let a hook failure interfere with the session.
    process.exit(0);
}
