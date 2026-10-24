import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

test('P11 browser: availability, recovery, payment/cancellation and stale responses', { skip: !existsSync(chrome) }, () => {
    const directory = mkdtempSync(join(tmpdir(), 'buildino-p11-'));
    const asset = name => pathToFileURL(join(project, 'public', name)).href;
    const view = readFileSync(join(project, 'resources/views/management/facility-reservations.blade.php'), 'utf8');
    const loops = [
        '<option value="4">ساختمان دمو</option><option value="5">ساختمان دوم</option>',
        ['انتخاب امکان', 'تاریخ و سانس', 'بررسی و ثبت'].map((label, index) => `<span data-reservation-step-label="${index}"><b>${index + 1}</b>${label}</span>`).join(''),
        '<option value="pending">در انتظار تأیید</option><option value="cancelled">لغوشده</option>',
    ];
    let loop = 0;
    const markup = view.slice(view.indexOf('<div class="ownership-page'), view.indexOf('@endsection'))
        .replace(/@foreach[^]*?@endforeach/g, () => loops[loop++])
        .replace(/@if[^]*?@endif/g, '')
        .replace(/@include\([^]*?\)\}/g, '')
        .replace(/@include\([^]*?\)/g, '◇');
    assert.equal(loop, 3, 'Fixture should use the real three view loops');
    assert.doesNotMatch(markup, /@foreach|@if|@include|\{\{/);
    const script = `
    const $ = key => document.querySelector('[data-reservation-' + key + ']');
    let checks = 0, createCalls = 0, payCalls = 0, cancelCalls = 0, createMode = 'validation', payMode = 'validation', cancelMode = 'validation';
    let releaseHistory, releaseChoice, holdHistory = true, holdChoice = false;
    const expect = (value, text) => { if (!value) throw new Error(text); checks++; };
    const until = async (predicate, text) => { for (let i = 0; i < 120; i++) { if (predicate()) return; await new Promise(resolve => setTimeout(resolve, 5)); } throw new Error(text); };
    const click = el => el.click();
    const chooseBuilding = id => { $('building').value = id; $('building').dispatchEvent(new Event('change', { bubbles: true })); };
    const facility = { id: 11, title: 'استخر ساختمان', is_active: true, requires_payment: true, requires_approval: false, capacity: 5, default_price: 50000, reservation_rule: { auto_confirm: true, max_duration_minutes: 60, refund_percentage: 100 } };
    const row = { id: 31, user_id: 1, status: 'payment_pending', final_amount: 50000, reservation_date: '2099-01-01', start_time: '10:00', end_time: '11:00', facility: { title: 'استخر ساختمان' }, unit: { unit_number: '۱۰۱' }, user: { first_name: 'کاربر', last_name: 'آزمایشی' }, rule_snapshot: { refund_percentage: 100 }, cancellations: [] };
    const validation = (key, text) => Object.assign(new Error(text), { status: 422, errors: { [key]: [text] } });
    const page = data => ({ data, meta: { current_page: 1, last_page: 2, total: data.length } });
    window.BuildinoReservationConfig = { actorId: 1, buildings: [{ id: 4, title: 'ساختمان دمو', currency: 'IRR', timezone: 'Asia/Tehran', canCreate: true, canCancel: true, canApprove: true }, { id: 5, title: 'ساختمان دوم', currency: 'IRR', canCreate: true }], units: [{ id: 9, buildingId: 4, label: 'بلوک الف / طبقه اول / واحد ۱۰۱' }] };
    window.BuildinoCoreFlows = { request: async (raw, options = {}) => {
        const url = new URL(raw, 'http://localhost'), path = url.pathname, method = options.method || 'GET';
        if (path.endsWith('/facility-reservations') && method === 'GET') {
            if (holdHistory) return new Promise(resolve => { releaseHistory = () => { holdHistory = false; resolve(page([])); }; });
            return page(createCalls ? [structuredClone(row)] : []);
        }
        if (/buildings\\/\\d+\\/facilities$/.test(path)) return page([path.includes('/5/') ? { ...facility, id: 22, title: 'سالن ساختمان دوم' } : facility]);
        if (path.endsWith('/availability')) return { data: { date: url.searchParams.get('date'), facility_is_active: true, slots: [
            { id: 81, start_time: '10:00', end_time: '11:00', price: 50000, capacity: 5, remaining_capacity: 3, is_available: true },
            { id: 82, start_time: '11:00', end_time: '12:00', price: 50000, capacity: 5, remaining_capacity: 0, is_available: false },
            { id: 83, start_time: '12:00', end_time: '13:00', price: 50000, capacity: 5, remaining_capacity: 5, is_available: false, is_blackout: true },
        ] } };
        if (/facilities\\/\\d+$/.test(path)) {
            if (holdChoice) return new Promise(resolve => { releaseChoice = () => { holdChoice = false; resolve({ data: facility }); }; });
            return { data: facility };
        }
        if (path.endsWith('/reservations') && method === 'POST') {
            createCalls++;
            if (createMode === 'validation') throw validation('unit_id', 'This unit has reached the daily reservation limit.');
            if (createMode === 'network') throw new Error('offline');
            row.reservation_date = JSON.parse(options.body).reservation_date; return { data: structuredClone(row) };
        }
        if (path.endsWith('/pay')) {
            payCalls++;
            if (payMode === 'validation') throw validation('balance', 'Insufficient available wallet balance.');
            if (payMode === 'network') throw new Error('offline');
            row.wallet_payment = { amount: 50000, status: 'paid', paid_at: '2099-01-01T08:00:00Z' }; row.status = 'pending'; return { data: structuredClone(row) };
        }
        if (path.endsWith('/cancel')) {
            cancelCalls++;
            if (cancelMode === 'validation') throw validation('status', 'The cancellation deadline for this reservation has passed.');
            row.status = 'cancelled'; row.cancellations = [{ reason: JSON.parse(options.body).reason, cancellation_fee: 0, refund_amount: 50000, refund_status: 'pending' }]; return { data: structuredClone(row) };
        }
        if (path.endsWith('/facility-reservations/31')) return { data: structuredClone(row) };
        throw new Error('Unexpected request: ' + path);
    } };
    window.runP11 = async () => {
        chooseBuilding('4');
        await until(() => $('facilities').querySelector('button'), 'Facilities did not load');
        expect($('history-next').disabled, 'An unrelated read must not unlock history pagination');
        releaseHistory(); await until(() => !$('history-next').disabled, 'History pagination never unlocked');
        click($('facilities').querySelector('button'));
        await until(() => !document.querySelector('[data-reservation-panel="1"]').hidden, 'Facility selection did not advance');
        click(document.querySelector('[data-reservation-day="1"]'));
        await until(() => $('slots').querySelectorAll('button').length === 3, 'Quick date did not load slots');
        expect($('slots').querySelectorAll('button')[1].disabled && $('slots').querySelectorAll('button')[2].disabled, 'Full and blackout slots must remain disabled');
        click($('slots').querySelector('button'));
        expect(!document.querySelector('[data-reservation-panel="2"]').hidden, 'Available slot did not advance');
        $('create-form').elements.description.value = 'یادداشت رزرو';
        click($('create'));
        await until(() => !$('create').disabled, 'Validation never completed');
        expect(!document.querySelector('[data-reservation-panel="2"]').hidden, 'Unit error should keep the review open');
        expect($('create-form').elements.description.value === 'یادداشت رزرو', 'Validation must preserve user data');
        createMode = 'network'; click($('create'));
        await until(() => !$('uncertain').hidden, 'Unknown create result must require review');
        expect($('create').disabled, 'Do not resubmit an unknown create result');
        const beforeRetry = createCalls; $('create-form').dispatchEvent(new Event('submit', { cancelable: true }));
        expect(createCalls === beforeRetry, 'Programmatic submit must also respect the unknown result');
        click($('resume')); await until(() => $('slots').querySelector('button'), 'Resume did not refresh availability');
        click($('slots').querySelector('button')); createMode = 'success'; click($('create')); click($('create'));
        await until(() => !$('detail').hidden && $('detail-state').textContent.includes('پرداخت'), 'Creation did not display real payment status');
        expect(createCalls === beforeRetry + 1, 'Double-click must create only one request');
        click($('actions').querySelector('button'));
        $('pay-form').elements.payer_source.value = 'unit_wallet'; click($('pay-form').querySelector('[type="submit"]'));
        await until(() => !$('pay-form').querySelector('[type="submit"]').disabled, 'Payment validation did not settle');
        expect(!$('pay-form').hidden && $('pay-form').elements.payer_source.value === 'unit_wallet', 'Insufficient balance must keep the wallet selection');
        expect(!$('detail-error').hidden, 'Payment error should be next to payment form');
        payMode = 'network'; click($('pay-form').querySelector('[type="submit"]'));
        await until(() => $('detail-error').textContent.includes('نتیجه'), 'Unknown payment result did not show recovery');
        expect($('pay-form').querySelector('[type="submit"]').disabled, 'Unknown payment must block repeat charge');
        click($('detail-reload')); await until(() => !$('detail-reload').disabled, 'Reservation refresh did not settle');
        expect($('detail-error').hidden, 'Refresh must clear stale error');
        click($('actions').querySelector('button')); payMode = 'success';
        const beforePayment = payCalls; click($('pay-form').querySelector('[type="submit"]')); click($('pay-form').querySelector('[type="submit"]'));
        await until(() => $('detail-state').textContent.includes('تأیید مدیر'), 'Payment must preserve the server pending-approval state');
        expect(payCalls === beforePayment + 1, 'Double payment click must send only once');
        await until(() => !$('actions').querySelector('button').disabled, 'Payment lock did not clear');
        click($('actions').querySelector('button')); $('cancel-form').elements.reason.value = 'تغییر برنامه';
        click($('cancel-form').querySelector('[type="submit"]'));
        await until(() => !$('cancel-form').querySelector('[type="submit"]').disabled, 'Cancellation validation did not settle');
        expect(!$('cancel-form').hidden && $('cancel-form').elements.reason.value === 'تغییر برنامه', 'Cancellation failure must preserve the reason');
        cancelMode = 'success'; click($('cancel-form').querySelector('[type="submit"]'));
        await until(() => $('detail-state').textContent.includes('لغوشده'), 'Cancellation status not shown');
        expect($('detail-body').textContent.includes('در انتظار بازپرداخت'), 'Do not claim a pending refund was completed');
        await until(() => !$('building').disabled, 'Write lock did not clear');
        click($('back-facility')); holdChoice = true; click($('facilities').querySelector('button'));
        await until(() => releaseChoice, 'Delayed selection was not sent'); chooseBuilding('5'); releaseChoice();
        await until(() => $('facilities').textContent.includes('سالن ساختمان دوم'), 'Second building did not load');
        expect(!document.querySelector('[data-reservation-panel="0"]').hidden, 'Stale facility response must not advance another building');
        expect(!$('no-units').hidden, 'Missing units should be explained before final step');
        expect($('detail').hidden, 'Previous building detail must be cleared');
        const root = document.querySelector('[data-reservation-page]'); expect(root.scrollWidth <= root.clientWidth, 'No horizontal overflow');
        if (${Boolean(process.env.P11_SCREENSHOT)}) {
            chooseBuilding('4'); await until(() => $('facilities').querySelector('button'), 'Preview facility not loaded');
            click($('facilities').querySelector('button')); await until(() => !document.querySelector('[data-reservation-panel="1"]').hidden, 'Preview selection not ready');
            click(document.querySelector('[data-reservation-day="1"]')); await until(() => $('slots').querySelectorAll('button').length === 3, 'Preview slots not loaded');
            $('booking').scrollIntoView({ block: 'start' });
        }
        document.getElementById('result').dataset.ok = 'true'; document.getElementById('result').textContent = JSON.stringify({ checks });
    };
    `;
    const html = `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="${asset('css/buildino-management.css')}"><link rel="stylesheet" href="${asset('css/buildino-unit-ownerships.css')}"><link rel="stylesheet" href="${asset('css/buildino-facilities.css')}"><link rel="stylesheet" href="${asset('css/buildino-reservations.css')}">
    <style>body{padding:24px} @media(max-width:600px){body{padding:12px}}</style>${markup}<pre id="result" hidden></pre><script>${script}</script><script src="${asset('js/buildino-reservations.js')}"></script><script>runP11().catch(error => document.getElementById('result').textContent = error.stack);</script></html>`;
    const fixture = join(directory, 'fixture.html'); writeFileSync(fixture, html);
    try {
        const args = ['--headless', '--disable-gpu', '--no-sandbox', '--allow-file-access-from-files', '--no-first-run', '--force-device-scale-factor=1', '--hide-scrollbars', '--user-data-dir=' + join(directory, 'profile'), '--window-size=' + (process.env.P11_MOBILE ? '500,900' : '1280,1000'), '--virtual-time-budget=5000', '--dump-dom'];
        if (process.env.P11_SCREENSHOT) args.push('--screenshot=' + resolve(process.env.P11_SCREENSHOT));
        const output = execFileSync(chrome, [...args, pathToFileURL(fixture).href], { encoding: 'utf8', timeout: 30000, maxBuffer: 2 * 1024 * 1024, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
        const result = output.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[0] || output.slice(-1500);
        assert.match(result, /data-ok="true"/, result);
    } finally {
        if (dirname(resolve(directory)) === resolve(tmpdir()) && directory.includes('buildino-p11-')) rmSync(directory, { recursive: true, force: true, maxRetries: 3, retryDelay: 100 });
    }
});
