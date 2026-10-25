import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { buildSync } from 'esbuild';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
test('Jalali datetime fields offer hours and minutes and preserve the submitted value', { skip: !existsSync(chrome) }, () => {
    const directory = mkdtempSync(join(tmpdir(), 'buildino-datetime-'));
    try {
        buildSync({ entryPoints: [join(project, 'resources/js/buildino-jalali-datepicker.js')], bundle: true, outfile: join(directory, 'picker.js'), loader: { '.css': 'empty' } });
        const fixture = join(directory, 'fixture.html');
        writeFileSync(fixture, `<!doctype html><meta charset="utf-8">
        <form id="form"><input id="arrival" name="expected_entry_at" type="datetime-local" value="2026-03-21T10:00">
        <input id="date" name="date" type="date" value="2026-03-21"><input id="time" name="time" type="time" value="10:00"></form>
        <pre id="result"></pre><script src="picker.js"></script><script>
        document.addEventListener('DOMContentLoaded', () => {
            const expect = (condition, message) => { if (!condition) throw Error(message); };
            try {
                const arrival = document.getElementById('arrival');
                window.jalaliDatepicker.show(arrival);
                const controls = () => document.querySelectorAll('jdp-container .jdp-time select');
                expect(controls().length === 2, 'Datetime must provide hour and minute selectors');
                controls()[1].value = '14'; controls()[1].dispatchEvent(new Event('change'));
                controls()[0].value = '35'; controls()[0].dispatchEvent(new Event('change'));
                expect(document.getElementById('form').elements.expected_entry_at.value === '2026-03-21T14:35', 'Selected time must reach submitted Gregorian value');
                expect(arrival.checkValidity(), 'Chosen datetime must be valid');
                window.jalaliDatepicker.show(document.getElementById('date'));
                expect(controls().length === 0, 'Date-only fields must stay date-only');
                window.jalaliDatepicker.show(document.getElementById('time'));
                expect(controls().length === 2, 'Time-only fields must retain time selectors');
                document.getElementById('result').dataset.ok = 'true';
            } catch (error) { document.getElementById('result').textContent = error.stack; }
        });</script>`);
        const output = execFileSync(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--allow-file-access-from-files', '--user-data-dir=' + join(directory, 'profile'), '--virtual-time-budget=3000', '--dump-dom', pathToFileURL(fixture).href], { encoding: 'utf8', timeout: 30000, maxBuffer: 1024 * 1024, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
        const result = output.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0] || output.slice(-1000);
        assert.match(result, /data-ok="true"/, result);
    } finally {
        if (dirname(resolve(directory)) === resolve(tmpdir()) && directory.includes('buildino-datetime-')) rmSync(directory, { recursive: true, force: true, maxRetries: 3, retryDelay: 100 });
    }
});
