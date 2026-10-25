import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

for (const kind of ['ownership', 'occupancy']) test(kind + ' steps replace one another and preserve unit context on back, failure and save', { skip: !existsSync(chrome) }, () => {
    const directory = mkdtempSync(join(tmpdir(), 'buildino-ownership-'));
    const resource = kind === 'ownership' ? 'ownerships' : 'occupancies';
    const view = readFileSync(join(project, 'resources/views/management/unit-' + resource + '.blade.php'), 'utf8');
    const markup = view.slice(view.indexOf('<div class="ownership-page'), view.indexOf('@endsection'))
        .replace(/@if[^]*?@else/, '')
        .replace(/@foreach[^]*?@endforeach/, '<option value="4">ساختمان آزمایشی</option>')
        .replace(/@endif/g, '').replace(/@include\([^]*?\)/g, '◇').replace(/\{\{[^]*?\}\}/g, '#');
    const source = readFileSync(join(project, 'public/js/buildino-unit-' + resource + '.js'), 'utf8').replaceAll('window.location.origin', "'http://localhost'").replaceAll('location.origin', "'http://localhost'");
    const html = `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8">${markup}<pre id="result"></pre><script>
    const $ = key => document.querySelector('[data-${kind}-' + key + ']');
    let holdRecords = false, releaseRecords, failSave = true, writes = 0;
    window.${kind === 'ownership' ? 'BuildinoOwnershipConfig' : 'BuildinoOccupancyConfig'} = { apiBase: '/api/v1', lookupBase: '/management/lookups', buildings: [{ id: 4, title: 'ساختمان آزمایشی', canCreate: true, canUpdate: true, canViewInvites: true, canInvite: true }] };
    const record = { id: 31, user_id: 1, user: { first_name: 'کاربر', last_name: 'آزمایشی' }, is_active: true, ownership_percentage: 50, occupancy_type: 'resident', starts_at: '2026-01-01' };
    window.fetch = async (raw, options = {}) => {
        const path = new URL(raw).pathname;
        let payload, ok = true;
        if (options.method === 'POST' || options.method === 'PATCH') {
            writes++; ok = !failSave;
            payload = ok ? { data: record } : { message: 'خطای اعتبارسنجی', errors: { notes: ['یادداشت را اصلاح کنید.'] } };
        } else if (path.endsWith('/blocks')) payload = { data: [{ id: 5, label: 'بلوک الف' }] };
        else if (path.endsWith('/floors')) payload = { data: [{ id: 6, label: 'طبقه اول' }] };
        else if (path.endsWith('/units')) payload = { data: [{ id: 7, label: 'واحد ۱۰۱' }] };
        else if (path.endsWith('_candidates')) payload = { data: [{ id: 2, label: 'کاربر جدید', hasMobile: true }] };
        else if (path.endsWith('/invitations')) payload = { data: [{ id: 41, invited_user_id: 3, invited_user: { display_name: 'ساکن دعوت‌شده' }, relation_type: 'resident', status: 'sent', planned_occupancy: { starts_at: '2026-01-01' } }], meta: { last_page: 1 } };
        else if (path.endsWith('/${resource}')) {
            if (holdRecords) await new Promise(resolve => { releaseRecords = resolve; });
            payload = { data: [record], meta: { last_page: 1 } };
        } else payload = { data: [] };
        return { ok, status: ok ? 200 : 422, headers: { get: () => 'application/json' }, json: async () => payload };
    };
    </script><script>${source}</script><script>
    const until = async (predicate, text) => { for (let i = 0; i < 100; i++) { if (predicate()) return; await new Promise(resolve => setTimeout(resolve, 5)); } throw new Error(text); };
    let checks = 0;
    const expect = (condition, text) => { if (!condition) throw new Error(text); checks++; };
    const visible = () => ['location', 'records', 'editor'].filter(key => !$(key).hidden);
    const only = step => expect(JSON.stringify(visible()) === JSON.stringify([step]), 'Only ' + step + ' should be visible');
    (async () => {
        await until(() => $('active').querySelector('button'), 'Owners did not load'); only('records');
        if ('${kind}' === 'occupancy') expect(!$('pending-wrap').hidden, 'Pending invitations must remain available in the residents stage');
        $('active').querySelector('button').click(); only('editor');
        expect($('editor-unit-label').textContent.includes('۱۰۱'), 'Editor should identify the selected unit');
        $('cancel').click(); only('records'); expect($('unit').value === '7', 'Back must preserve unit');
        $('back-unit').click(); only('location'); expect(!$('continue').hidden, 'Selected unit must be resumable');
        $('continue').click(); await until(() => $('active').querySelector('button'), 'Owners did not resume'); only('records');
        $('add').click(); only('editor');
        if ('${kind}' === 'occupancy') {
            $('user-search').value = '09129990011'; $('user-search').dispatchEvent(new Event('input', { bubbles: true }));
        } else expect(!$('user-search'), 'Owner selection must not have a separate search input');
        await until(() => $('user').querySelector('option[value="2"]'), 'Candidate lookup did not finish');
        $('user').value = '2'; $('user').dispatchEvent(new Event('change', { bubbles: true }));
        if ('${kind}' === 'occupancy') $('form').elements.occupancy_type.value = 'resident';
        $('form').elements.notes.value = 'یادداشت محفوظ';
        $('form').dispatchEvent(new Event('submit', { cancelable: true }));
        await until(() => !$('save').disabled, 'Failed save did not settle'); only('editor');
        expect($('form').elements.notes.value === 'یادداشت محفوظ', 'Failure must preserve the form');
        failSave = false; const before = writes;
        $('form').dispatchEvent(new Event('submit', { cancelable: true })); $('form').dispatchEvent(new Event('submit', { cancelable: true }));
        await until(() => !$('save').disabled && !$('records').hidden, 'Successful save did not return to owners'); only('records');
        expect(writes === before + 1, 'Double submit must send only once');
        holdRecords = true; $('reload').click(); await until(() => releaseRecords, 'Reload was not sent'); only('records');
        $('back-unit').click(); only('location'); holdRecords = false; releaseRecords();
        await new Promise(resolve => setTimeout(resolve, 10)); only('location');
        expect(document.querySelector('[data-${kind}-flow="unit"]').getAttribute('aria-current') === 'step', 'Progress must reflect the visible stage');
        document.getElementById('result').dataset.ok = 'true'; document.getElementById('result').textContent = JSON.stringify({ checks });
    })().catch(error => document.getElementById('result').textContent = error.stack);
    </script></html>`;
    const fixture = join(directory, 'fixture.html'); writeFileSync(fixture, html);
    try {
        const output = execFileSync(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--allow-file-access-from-files', '--user-data-dir=' + join(directory, 'profile'), '--virtual-time-budget=3000', '--dump-dom', pathToFileURL(fixture).href], { encoding: 'utf8', timeout: 30000, maxBuffer: 1024 * 1024, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
        const result = output.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[0] || output.slice(-1000);
        assert.match(result, /data-ok="true"/, result);
    } finally {
        if (dirname(resolve(directory)) === resolve(tmpdir()) && directory.includes('buildino-ownership-')) rmSync(directory, { recursive: true, force: true, maxRetries: 3, retryDelay: 100 });
    }
});
