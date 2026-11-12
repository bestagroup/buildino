import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const script = fileURLToPath(new URL('../../scripts/deploy-cpanel.sh', import.meta.url));

for (const failure of ['', 'install', 'migrate']) {
    test(`deployment ${failure ? `stops on ${failure} failure` : 'installs locked dependencies before migrations and restarts workers'}`, () => {
        const directory = mkdtempSync(join(tmpdir(), 'buildino-deploy-'));
        try {
            writeFileSync(join(directory, 'artisan'), '');
            const composer = join(directory, 'composer');
            writeFileSync(composer, '', { mode: 0o755 });
            const php = join(directory, 'php');
            writeFileSync(php, `#!/bin/sh
printf '%s\\n' "$*" >> "$DEPLOY_TEST_LOG"
if [ -n "$DEPLOY_TEST_FAIL" ] && [ "$2" = "$DEPLOY_TEST_FAIL" ]; then exit 42; fi
`, { mode: 0o755 });
            const log = join(directory, 'commands');
            const result = spawnSync('sh', [script], {
                cwd: directory, encoding: 'utf8',
                env: { ...process.env, SKIP_GIT: '1', PHP_BIN: php, COMPOSER_BIN: composer, DEPLOY_TEST_LOG: log, DEPLOY_TEST_FAIL: failure },
            });
            const commands = readFileSync(log, 'utf8').trim().split('\n');
            assert.equal(commands[0], 'artisan down --retry=60');
            assert.match(commands[1], /install --no-dev --prefer-dist --optimize-autoloader --no-interaction$/);
            if (failure) {
                assert.equal(result.status, 42);
                assert.ok(!commands.includes('artisan up'));
                assert.ok(!commands.includes('artisan queue:restart'));
                if (failure === 'install') assert.ok(!commands.includes('artisan migrate --force'));
            } else {
                assert.equal(result.status, 0, result.stderr);
                assert.ok(commands[2].endsWith('check-platform-reqs --no-dev'));
                assert.equal(commands[3], 'artisan migrate --force');
                assert.deepEqual(commands.slice(-2), ['artisan queue:restart', 'artisan up']);
            }
        } finally {
            rmSync(directory, { recursive: true, force: true });
        }
    });
}
