import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { buildSync } from 'esbuild';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
test('charge periods: Jalali dates, validation, permission/state actions, confirmation, recovery and stale reads', { skip: !existsSync(chrome) }, () => {
    const directory = mkdtempSync(join(tmpdir(), 'buildino-periods-'));
    try {
        buildSync({ entryPoints: [join(project, 'resources/js/buildino-jalali-datepicker.js')], bundle: true, outfile: join(directory, 'picker.js'), loader: { '.css': 'empty' } });
        const view = readFileSync(join(project, 'resources/views/management/charge-periods.blade.php'), 'utf8');
        const markup = view.slice(view.indexOf('<div class="ownership-page'), view.indexOf('@endsection'))
            .replace(/@foreach[^]*?@endforeach/, '<option value="4">ساختمان آزمایشی</option><option value="5">ساختمان مشاهده</option>')
            .replace(/@if[^]*?@endif/g, '').replace(/@include\([^]*?\)/g, '◇').replace(/\{\{[^]*?\}\}/g, '#');
        assert.doesNotMatch(markup, /@foreach|@if|\{\{/);
        const source = readFileSync(join(project, 'public/js/buildino-charge-periods.js'), 'utf8');
        const asset = name => pathToFileURL(join(project, 'public', name)).href;
        const screenshot = process.env.PERIOD_SCREENSHOT;
        const fixture = join(directory, 'fixture.html');
        writeFileSync(fixture, `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><style>
        @font-face{font-family:IRANYekan;src:url('${asset('fonts/iranyekanrd/IRANYekanRegularRd.ttf')}')}@font-face{font-family:IRANYekan;font-weight:700;src:url('${asset('fonts/iranyekanrd/IRANYekanBoldRd.ttf')}')}
        :root{--surface:#fff;--surface-soft:#f5f8fc;--surface-strong:#eaf0f6;--border:#dde4ed;--border-strong:#c6d2df;--text:#182434;--text-soft:#394e65;--muted:#708090;--primary-50:#edf6ff;--primary-100:#deeeff;--primary-500:#4b91c8;--primary-700:#245c87;--success:#168357;--success-soft:#e5f7ec;--danger:#ba3434;--danger-soft:#fff1f1}*{box-sizing:border-box}body{margin:16px;background:#f3f6fa;font-family:IRANYekan,Tahoma}button,input,select{font:inherit}a{color:var(--primary-700)}</style>
        <link rel="stylesheet" href="${asset('css/buildino-unit-ownerships.css')}"><link rel="stylesheet" href="${asset('css/buildino-charge-periods.css')}">
        <link rel="stylesheet" href="${pathToFileURL(join(project, 'node_modules/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css')).href}">
        <link rel="stylesheet" href="${pathToFileURL(join(project, 'resources/css/buildino-jalali-datepicker.css')).href}">
        ${markup}<pre id="result"></pre><script>
        const $ = key => document.querySelector('[data-period-' + key + ']');
        const browserErrors = []; window.addEventListener('error', event => browserErrors.push(event.error?.stack || event.message));
        const row = { id: 31, title: 'شارژ مهر', period_start: '2026-10-01', period_end: '2026-10-30', due_date: '2026-11-01', status: 'draft', calculations_count: 0, expense_allocations_count: 0, invoices_count: 0 };
        let failSave = true, failAction = true, failRead = false, holdRead = false, releaseRead, discard = false, writes = 0;
        window.confirm = () => discard;
        window.BuildinoPeriodConfig = { buildings: [
            { id: 4, title: 'ساختمان آزمایشی', canCreate: true, canUpdate: true, canCalculate: true, canIssue: true, canViewInvoices: true, canViewFormulas: true },
            { id: 5, title: 'ساختمان مشاهده' }
        ] };
        window.BuildinoCoreFlows = { request: async (raw, options = {}) => {
            const path = new URL(raw, 'http://localhost').pathname, method = options.method || 'GET';
            if (method !== 'GET') {
                writes++;
                if (path.endsWith('/calculate') || path.endsWith('/issue')) {
                    if (failAction) throw Object.assign(new Error('برای این ساختمان فرمول فعالی تعریف نشده است.'), { status: 422 });
                    row.status = path.endsWith('/issue') ? 'issued' : 'calculated'; row.calculations_count = 4; row.expense_allocations_count = 8; row.invoices_count = row.status === 'issued' ? 4 : 0;
                } else {
                    if (failSave) throw Object.assign(new Error('Overlap'), { status: 422, errors: { period_start: ['This charge period overlaps an existing period.'] } });
                    Object.assign(row, JSON.parse(options.body));
                }
                return { data: { ...row } };
            }
            if (path.includes('/buildings/')) return { data: [{ ...row }], meta: { current_page: 1, last_page: 2, total: 2 } };
            if (holdRead) await new Promise(resolve => releaseRead = resolve);
            if (failRead) throw Object.assign(new Error('دریافت نشد'), { status: 500 });
            return { data: { ...row } };
        } };
        </script><script src="picker.js"></script><script>${source}</script><script>
        const expect = (condition, text) => { if (!condition) throw Error(text); };
        const until = async predicate => { for (let i = 0; i < 150; i++) { if (predicate()) return; await new Promise(resolve => setTimeout(resolve, 5)); } throw Error('Timed out'); };
        const only = name => expect(['location', 'directory', 'editor', 'detail'].filter(key => !$(key).hidden).join() === name, 'Only ' + name + ' should be visible. ' + browserErrors.join('; '));
        const choose = id => { $('building').value = id; $('building').dispatchEvent(new Event('change', { bubbles: true })); };
        const setDate = (name, value) => { const field = $('form').elements[name], el = field.previousElementSibling; expect(field.type === 'hidden', 'Real Jalali picker must supply Gregorian form values'); el.value = window.BuildinoJalaliDatepicker.serverToJalali(value, 'date'); el.dispatchEvent(new Event('input', { bubbles: true })); };
        const submit = () => $('form').dispatchEvent(new Event('submit', { cancelable: true }));
        const detailReady = () => !$('detail-body').hidden && !$('close').disabled;
        (async () => {
            await until(() => window.BuildinoJalaliDatepicker && document.readyState !== 'loading');
            only('location'); choose('4'); await until(() => $('list').querySelector('button')); only('directory');
            expect($('building-label').textContent.includes('آزمایشی'), 'Building context missing');
            $('search').value = 'ناموجود'; $('search').dispatchEvent(new Event('input')); expect(!$('list').querySelector('button'), 'Local search must filter visible page');
            $('search').value = ''; $('search').dispatchEvent(new Event('input'));
            $('new').click(); only('editor'); submit(); expect(writes === 0 && !$('form').querySelector('[data-period-field-error="title"]').hidden, 'Required title must be validated');
            $('form').elements.title.value = 'شارژ مهر'; setDate('period_start', '2026-10-01'); setDate('period_end', '2026-09-30'); setDate('due_date', '2026-11-01'); submit();
            expect(writes === 0 && document.activeElement === $('form').elements.period_end.previousElementSibling, 'Invalid range must focus visible date input');
            setDate('period_end', '2026-10-30'); setDate('due_date', '2026-10-29'); submit(); expect(writes === 0, 'Due date before period end must be rejected');
            setDate('due_date', '2026-11-01'); submit(); await until(() => !$('save').disabled); only('editor');
            expect($('form').elements.period_start.value === '2026-10-01' && !$('form').querySelector('[data-period-field-error="period_start"]').hidden, 'Server error must retain dates and reveal inline feedback');
            failSave = false; const beforeSave = writes; submit(); submit(); await until(detailReady); only('detail'); expect(writes === beforeSave + 1, 'Double submit must save once');
            expect(!$('calculate').hidden && $('issue').hidden && !$('edit').hidden, 'Draft actions must match status');
            expect($('form').elements.period_start.value === row.period_start, 'Submitted date must remain Gregorian');
            $('edit').click(); only('editor'); expect($('form').elements.period_end.previousElementSibling.value.includes('/'), 'Editing must restore visible Jalali dates');
            $('form').elements.title.value = 'پیش‌نویس'; $('form').elements.title.dispatchEvent(new Event('input', { bubbles: true })); $('cancel').click(); only('editor');
            discard = true; $('cancel').click(); only('detail');
            const beforeAction = writes; $('calculate').click(); expect(!$('confirm').hidden && writes === beforeAction, 'Calculation requires explicit confirmation');
            $('action-cancel').click(); expect($('confirm').hidden && writes === beforeAction, 'Cancel must not mutate finances');
            $('calculate').click(); $('action-submit').click(); await until(detailReady); only('detail'); expect(!$('error').hidden && row.status === 'draft', 'Failed calculation must not advance lifecycle');
            failAction = false; $('calculate').click(); $('action-submit').click(); $('action-submit').click(); await until(() => row.status === 'calculated' && !$('close').disabled);
            expect(!$('issue').hidden && $('edit').hidden && $('invoices').textContent === '۰', 'Calculated period must allow issuance without invented invoices');
            expect($('allocations').textContent === '۸', 'Expense allocation count must use actual response');
            $('issue').click(); expect($('confirm-copy').textContent.includes('شارژ مهر'), 'Issuance confirmation must identify period'); $('action-submit').click();
            await until(() => row.status === 'issued' && !$('close').disabled); expect($('issue').hidden && $('calculate').hidden && $('edit').hidden && !$('invoice-link').hidden, 'Issued period must be read-only');
            await new Promise(resolve => setTimeout(resolve, 350));
            expect(getComputedStyle(document.querySelector('jdp-container')).display === 'none', 'Delayed calendar animation must not leak outside editor');
            expect($('invoices').textContent === '۴', 'Invoice count must reflect backend');
            $('close').click(); await until(() => $('list').querySelector('button')); only('directory');
            holdRead = true; $('list').querySelector('button').click(); await until(() => releaseRead); $('close').click(); only('directory'); holdRead = false; releaseRead();
            await new Promise(resolve => setTimeout(resolve, 10)); only('directory');
            failRead = true; $('list').querySelector('button').click(); await until(() => !$('detail-retry').hidden); expect($('detail-body').hidden, 'Failed read must not display stale financial data');
            failRead = false; $('detail-retry').click(); await until(detailReady);
            $('close').click(); await until(() => $('list').querySelector('button')); $('change-building').click(); only('location');
            choose('5'); await until(() => $('list').querySelector('button')); expect($('new').hidden, 'Viewer must not create periods');
            row.status = 'draft'; $('list').querySelector('button').click(); await until(detailReady);
            expect($('edit').hidden && $('calculate').hidden && $('issue').hidden, 'Viewer must not receive financial actions');
            expect(document.documentElement.scrollWidth <= window.innerWidth, 'Layout must fit viewport');
            expect(browserErrors.length === 0, 'No browser runtime errors: ' + browserErrors.join('; '));
            if (${Boolean(screenshot)}) { $('close').click(); await until(() => $('list').querySelector('button')); choose('4'); await until(() => $('list').querySelector('button')); row.status = 'calculated'; $('list').querySelector('button').click(); await until(detailReady); await document.fonts.ready; window.scrollTo(0, 0); }
            document.getElementById('result').dataset.ok = 'true';
        })().catch(error => document.getElementById('result').textContent = error.stack);
        </script></html>`);
        const output = execFileSync(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--allow-file-access-from-files', '--window-size=' + (process.env.PERIOD_VIEWPORT || '500,1000'), '--user-data-dir=' + join(directory, 'profile'), '--virtual-time-budget=5000', ...(screenshot ? ['--screenshot=' + resolve(screenshot)] : []), '--dump-dom', pathToFileURL(fixture).href], { encoding: 'utf8', timeout: 30000, maxBuffer: 1024 * 1024, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
        const result = output.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0] || output.slice(-1000);
        assert.match(result, /data-ok="true"/, result);
    } finally {
        if (dirname(resolve(directory)) === resolve(tmpdir()) && directory.includes('buildino-periods-')) rmSync(directory, { recursive: true, force: true, maxRetries: 3, retryDelay: 100 });
    }
});
