import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtemp, mkdir, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const checker = fileURLToPath(new URL('./check-design-system.mjs', import.meta.url));
const requiredFiles = [
    'resources/css/design-system.css',
    'resources/css/ui-forms.css',
    'resources/css/ui-data.css',
    'resources/css/ui-feedback.css',
    'resources/views/components/ui/button.blade.php',
    'resources/views/components/ui/input.blade.php',
    'resources/views/components/ui/table.blade.php',
    'resources/views/components/ui/modal.blade.php',
    'resources/views/components/erp/entity-header.blade.php',
    'resources/views/components/ai/assistant-panel.blade.php',
];

async function createFixture(root, { extraFiles = {}, missingFiles = [] } = {}) {
    const files = new Map(requiredFiles.map((file) => [file, '']));
    files.set('resources/css/design-system.css', ':root { --color-primary: #17614b; }\n');

    for (const file of missingFiles) files.delete(file);
    for (const [file, content] of Object.entries(extraFiles)) files.set(file, content);

    for (const [file, content] of files) {
        const destination = path.join(root, file);
        await mkdir(path.dirname(destination), { recursive: true });
        await writeFile(destination, content, 'utf8');
    }
}

function runChecker(root) {
    const result = spawnSync(process.execPath, [checker, '--root', root], {
        encoding: 'utf8',
        timeout: 10_000,
    });

    assert.equal(result.error, undefined, `checker process should start and finish: ${result.error?.message ?? ''}`);

    return result;
}

const tempRoot = await mkdtemp(path.join(os.tmpdir(), 'design-system-check-'));

try {
    const cleanRoot = path.join(tempRoot, 'clean');
    await mkdir(cleanRoot);
    await createFixture(cleanRoot, {
        extraFiles: {
            'resources/css/ui-forms.css': '.example { color: var(--color-text); background: rgb(var(--surface-rgb) / 20%); }\n',
            'resources/views/design-system/clean.blade.php': '<span style="color: var(--color-text)">Ready</span>\n',
            'resources/js/comment.js': "// document.body.style.color = 'red';\n",
        },
    });
    const clean = runChecker(cleanRoot);
    assert.equal(clean.status, 0, `clean fixture should pass\n${clean.stderr}${clean.stdout}`);

    const violationRoot = path.join(tempRoot, 'violation');
    await mkdir(violationRoot);
    await createFixture(violationRoot, {
        extraFiles: {
            'resources/css/ui-forms.css': '.example { color: #123456; }\n',
            'resources/css/ui-named-colors.css': '.example { color: red; background: white; }\n',
            'resources/css/ui-modern-color.css': '.example { color: color(srgb 1 0 0); }\n',
            'resources/views/design-system/bad.blade.php': '<span style="color: oklch(54% 0.1 120)">Bad</span>\n',
            'resources/js/design-system.js': "document.body.style.color = 'rgb(255 0 0)';\n",
        },
    });
    const violation = runChecker(violationRoot);
    assert.equal(violation.status, 1, 'a hardcoded theme color should fail');
    assert.match(violation.stderr + violation.stdout, /ui-forms\.css/);
    assert.match(violation.stderr + violation.stdout, /ui-named-colors\.css/);
    assert.match(violation.stderr + violation.stdout, /ui-modern-color\.css/);
    assert.match(violation.stderr + violation.stdout, /bad\.blade\.php/);
    assert.match(violation.stderr + violation.stdout, /design-system\.js/);

    const javascriptThemeRoot = path.join(tempRoot, 'javascript-theme');
    await mkdir(javascriptThemeRoot);
    await createFixture(javascriptThemeRoot, {
        extraFiles: { 'resources/js/theme.js': "const theme = { primary: '#abcdef' };\n" },
    });
    const javascriptTheme = runChecker(javascriptThemeRoot);
    assert.equal(javascriptTheme.status, 1, 'a hardcoded JavaScript theme value should fail');
    assert.match(javascriptTheme.stderr + javascriptTheme.stdout, /theme\.js/);

    const layoutRoot = path.join(tempRoot, 'layout-token');
    await mkdir(layoutRoot);
    await createFixture(layoutRoot, {
        extraFiles: { 'resources/css/ui-forms.css': ':root { --container-max-width: 72rem; }\n' },
    });
    const layout = runChecker(layoutRoot);
    assert.equal(layout.status, 1, 'a competing global layout token should fail');
    assert.match(layout.stderr + layout.stdout, /layout sizing token/);

    const tokenRoot = path.join(tempRoot, 'token');
    await mkdir(tokenRoot);
    await createFixture(tokenRoot, {
        extraFiles: { 'resources/css/design-system.css': ':root { --color-primary: #123456; }\n' },
    });
    const token = runChecker(tokenRoot);
    assert.equal(token.status, 0, `theme tokens should be allowed in design-system.css\n${token.stderr}${token.stdout}`);

    const missingRoot = path.join(tempRoot, 'missing');
    await mkdir(missingRoot);
    await createFixture(missingRoot, {
        missingFiles: ['resources/views/components/ui/button.blade.php'],
    });
    const missing = runChecker(missingRoot);
    assert.equal(missing.status, 1, 'missing core library files should fail');
    assert.match(missing.stderr + missing.stdout, /button\.blade\.php/);
} finally {
    await rm(tempRoot, { recursive: true, force: true });
}

console.log('Design-system checker self-test passed.');
