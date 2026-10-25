import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
test('guest panels replace each other while preserving registration steps and permit actions', { skip: !existsSync(chrome) }, () => {
    const directory = mkdtempSync(join(tmpdir(), 'buildino-guest-'));
    const view = readFileSync(join(project, 'resources/views/management/guest-visits.blade.php'), 'utf8');
    const markup = view.slice(view.indexOf('<div class="ownership-page'), view.indexOf('@endsection'))
        .replace(/@if[^]*?@else/, '').replace(/@endif/g, '')
        .replace(/@foreach \(\$guestConfig[^]*?@endforeach/, '<option value="4">ساختمان آزمایشی</option>')
        .replace(/@foreach \(\['block'[^]*?@endforeach/, ['block', 'floor', 'unit'].map(key => `<select data-guest-${key} disabled><option value="">انتخاب کنید</option></select>`).join(''))
        .replace(/@foreach \(\['مشخصات[^]*?@endforeach/, [0, 1, 2].map(index => `<span data-guest-step-label="${index}"></span>`).join(''))
        .replace(/@include\([^]*?\)/g, '').replace(/\{\{[^]*?\}\}/g, '#');
    const source = readFileSync(join(project, 'public/js/buildino-guest-visits.js'), 'utf8');
    const fixture = join(directory, 'fixture.html');
    writeFileSync(fixture, `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8">${markup}<pre id="result"></pre><script>
    const $ = key => document.querySelector('[data-guest-' + key + ']');
    let hold = false, release, status = 'invited';
    window.BuildinoGuestConfig = { buildings: [{ id: 4, canCreate: true, canUpdate: true }] };
    window.BuildinoCoreFlows = { request: async path => {
        if (path.includes('/lookups/')) return { data: [{ id: 7, label: 'واحد ۱۰۱' }] };
        if (path.includes('/access-logs')) return { data: [], meta: {} };
        const row = { id: 31, status, guest: { first_name: 'علی', last_name: 'آزمایشی' }, expected_entry_at: '2099-01-01T10:00:00Z' };
        if (path.includes('/units/')) { if (hold) await new Promise(resolve => release = resolve); return { data: [row], meta: {} }; }
        return { data: row };
    } };
    </script><script>${source}</script><script>
    const until = async predicate => { for (let i = 0; i < 100; i++) { if (predicate()) return; await new Promise(resolve => setTimeout(resolve, 5)); } throw Error('Timed out'); };
    const expect = (condition, label) => { if (!condition) throw Error(label); };
    const only = panel => {
        expect(['location', 'records', 'editor', 'detail'].filter(key => !$(key).hidden).join() === panel, 'Visible panel: ' + panel);
        expect(!document.querySelector('[data-guest-flow]'), 'Management views must not be presented as registration steps');
        if (panel === 'editor') {
            const current = document.querySelectorAll('[data-guest-step-label][aria-current="step"]');
            expect(current.length === 1, 'Registration must have one current step');
            expect(!$('form').querySelector('[data-guest-step="' + current[0].dataset.guestStepLabel + '"]').hidden, 'Registration progress must match visible step');
        }
    };
    const submit = () => $('form').dispatchEvent(new Event('submit', { cancelable: true }));
    (async () => {
        await until(() => $('list').querySelector('button')); only('records');
        $('add').click(); only('editor'); expect($('editor-unit').textContent.includes('۱۰۱'), 'Unit context missing');
        submit(); expect(!$('form').querySelector('[data-guest-step="0"]').hidden, 'Invalid form advanced');
        $('form').elements.first_name.value = 'علی'; $('form').elements.last_name.value = 'آزمایشی'; submit();
        expect(!$('form').querySelector('[data-guest-step="1"]').hidden, 'Time step missing'); only('editor');
        $('form').elements.expected_entry_at.value = '2099-01-01T10:00'; submit();
        expect(!$('form').querySelector('[data-guest-step="2"]').hidden, 'Review missing');
        $('back').click(); expect(!$('form').querySelector('[data-guest-step="1"]').hidden, 'Back failed');
        $('cancel').click(); only('records');
        $('list').querySelector('button').click(); only('detail');
        await until(() => $('detail-actions').querySelector('button'));
        $('detail-actions').querySelector('button').click(); expect(!$('access-form').hidden, 'Entry action missing');
        $('access-cancel').click(); expect($('access-form').hidden, 'Cancel entry failed');
        $('close-detail').click(); only('records');
        $('change-unit').click(); only('location'); expect($('unit').value === '7' && !$('location-continue').hidden, 'Unit not preserved');
        $('location-continue').click(); await until(() => $('list').querySelector('button')); only('records');
        hold = true; $('reload').click(); await until(() => release); $('change-unit').click(); only('location'); release();
        await new Promise(resolve => setTimeout(resolve, 10)); only('location');
        hold = false; status = 'entered'; $('location-continue').click(); await until(() => $('list').querySelector('button'));
        $('list').querySelector('button').click(); await until(() => $('detail-actions').querySelector('button'));
        expect($('detail-actions').textContent.includes('خروج'), 'Exit action missing'); only('detail');
        document.getElementById('result').dataset.ok = 'true';
    })().catch(error => document.getElementById('result').textContent = error.stack);
    </script></html>`);
    try {
        const output = execFileSync(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--allow-file-access-from-files', '--user-data-dir=' + join(directory, 'profile'), '--virtual-time-budget=3000', '--dump-dom', pathToFileURL(fixture).href], { encoding: 'utf8', timeout: 30000, maxBuffer: 1024 * 1024, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
        const result = output.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0] || output.slice(-1000);
        assert.match(result, /data-ok="true"/, result);
    } finally {
        if (dirname(resolve(directory)) === resolve(tmpdir()) && directory.includes('buildino-guest-')) rmSync(directory, { recursive: true, force: true, maxRetries: 3, retryDelay: 100 });
    }
});
