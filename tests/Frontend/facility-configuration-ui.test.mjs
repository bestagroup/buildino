import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
test('facility configuration: separate views, saved context, dirty forms, time slots and read recovery', { skip: !existsSync(chrome) }, () => {
    const directory = mkdtempSync(join(tmpdir(), 'buildino-facilities-'));
    const view = readFileSync(join(project, 'resources/views/management/facility-configuration.blade.php'), 'utf8');
    const loops = [
        '<option value="4">ساختمان آزمایشی</option>',
        ['اطلاعات و هزینه', 'برنامه و سانس‌ها', 'قوانین رزرو', 'بررسی و فعال‌سازی'].map((label, i) => `<button type="button" data-facility-step-button="${i}"><b aria-hidden="true">${i + 1}</b><span>${label}</span></button>`).join(''),
        ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'].map((day, i) => `<label><input name="days" type="checkbox" value="${(i + 6) % 7}">${day}</label>`).join(''),
        [['min_duration_minutes','حداقل مدت رزرو (دقیقه)',1,''],['max_duration_minutes','حداکثر مدت رزرو (دقیقه)',1,60],['min_advance_minutes','فاصله رزرو تا شروع (دقیقه)',0,0],['max_advance_days','امکان رزرو تا چند روز آینده',1,''],['max_reservation_per_unit','حداکثر رزرو فعال هر واحد',1,1],['cancel_before_minutes','مهلت لغو پیش از شروع (دقیقه)',0,0]].map(([name,label,min,value]) => `<label class="ownership-field"><span>${label}</span><input name="${name}" type="number" min="${min}" value="${value}" step="1" placeholder="بدون محدودیت"></label>`).join(''),
        [['max_reservations_per_day','حداکثر رزرو روزانه'],['max_reservations_per_week','حداکثر رزرو هفتگی'],['max_reservations_per_month','حداکثر رزرو ماهانه']].map(([name,label]) => `<label class="ownership-field"><span>${label}</span><input name="${name}" type="number" min="1" placeholder="بدون محدودیت"></label>`).join(''),
    ];
    let loop = 0;
    const markup = view.slice(view.indexOf('<div class="ownership-page'), view.indexOf('@endsection'))
        .replace(/@foreach[^]*?@endforeach/g, () => loops[loop++])
        .replace(/@if[^]*?@endif/g, '').replace(/@include\([^]*?\)/g, '◇');
    assert.equal(loop, 5);
    assert.doesNotMatch(markup, /@foreach|@if|\{\{/);
    const source = readFileSync(join(project, 'public/js/buildino-facilities.js'), 'utf8');
    const asset = name => pathToFileURL(join(project, 'public', name)).href;
    const fixture = join(directory, 'fixture.html');
    writeFileSync(fixture, `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><style>:root{--surface:#fff;--surface-soft:#f5f8fc;--border:#dde4ed;--text:#182434;--muted:#708090}*{box-sizing:border-box}body{margin:16px}</style>
    <link rel="stylesheet" href="${asset('css/buildino-fonts.css')}"><link rel="stylesheet" href="${asset('css/buildino-management.css')}"><link rel="stylesheet" href="${asset('css/buildino-design-system.css')}"><link rel="stylesheet" href="${asset('css/buildino-unit-ownerships.css')}"><link rel="stylesheet" href="${asset('css/buildino-facilities.css')}">
    ${markup}<pre id="result"></pre><script>
    const $ = key => document.querySelector('[data-facility-' + key + ']');
    let allowDiscard = false, holdDetail = false, releaseDetail, failDetail = false, failReadAfterWrite = false, writes = 0;
    window.confirm = () => allowDiscard;
    window.BuildinoFacilityConfig = { buildings: [{ id: 4, title: 'ساختمان آزمایشی', currency: 'IRR', canCreate: true, canUpdate: true }] };
    const facility = { id: 31, title: 'استخر', code: 'POOL', type: 'pool', capacity: 10, default_price: 0, is_active: false,
        schedules: [{ id: 21, day_of_week: 6, start_time: '10:00', end_time: '12:00', is_active: true, time_slots: [{ id: 22, start_time: '10:00', end_time: '11:00', capacity: 5, price: 0, is_active: true }] }], reservation_rule: { max_duration_minutes: 60 } };
    window.BuildinoCoreFlows = { request: async (path, options = {}) => {
        if (options.method && options.method !== 'GET') { writes++; if (failReadAfterWrite) failDetail = true; return { data: facility }; }
        if (path.includes('/buildings/')) return { data: [facility], meta: { current_page: 1, last_page: 1 } };
        if (holdDetail) await new Promise(resolve => releaseDetail = resolve);
        if (failDetail) throw Object.assign(new Error('خطا'), { status: 500 });
        return { data: facility };
    } };
    </script><script>${source}</script><script>
    const until = async predicate => { for (let i = 0; i < 100; i++) { if (predicate()) return; await new Promise(resolve => setTimeout(resolve, 5)); } throw Error('Timed out'); };
    const expect = (condition, text) => { if (!condition) throw Error(text); };
    const layoutFits = () => {
        expect(document.documentElement.scrollWidth <= window.innerWidth, 'Layout must fit viewport');
        for (const el of document.querySelectorAll('[data-facility-editor] input, [data-facility-editor] select, .facility-steps button, .facility-settings .buildino-switch')) {
            const rect = el.getBoundingClientRect();
            if (rect.width > 1 && rect.height) expect(rect.left >= 0 && rect.right <= innerWidth, 'Control outside viewport: ' + (el.name || el.textContent));
        }
    };
    const only = name => expect(['location', 'directory', 'editor'].filter(key => !$(key).hidden).join() === name, 'Only ' + name + ' should be visible');
    const go = index => document.querySelector('[data-facility-step-button="' + index + '"]').click();
    (async () => {
        await until(() => $('list').querySelector('button')); only('directory');
        expect($('building-label').textContent.includes('آزمایشی'), 'Building context missing');
        $('new').click(); only('editor');
        expect(document.querySelector('[data-facility-step-button="1"]').disabled, 'New facility cannot skip initial save');
        $('close').click(); only('directory'); await until(() => $('list').querySelector('button'));
        $('list').querySelector('button').click(); only('editor'); await until(() => !$('base-fields').disabled);
        expect($('editor-title').textContent.includes('استخر'), 'Facility title missing');
        layoutFits();
        const price = $('base-form').elements.default_price.getBoundingClientRect(), payment = $('base-form').elements.requires_payment.closest('.buildino-switch').getBoundingClientRect();
        expect(Math.abs(price.height - payment.height) <= 1, 'Payment switch must match input height');
        if (innerWidth > 700) expect(Math.abs(price.top - payment.top) <= 1, 'Price and payment must align');
        go(1); expect(!document.querySelector('[data-facility-panel="1"]').hidden, 'Schedule panel missing');
        layoutFits();
        expect(document.querySelectorAll('.facility-schedule-group').length === 1, 'Schedule and slots must be grouped');
        expect(!$('slot-fields').disabled, 'Existing schedules must enable slot form');
        $('schedules').querySelectorAll('button')[1].click();
        expect($('slot-form').elements.start_time.value === '10:00' && $('slot-form').elements.schedule_id.disabled, 'Existing slot edit must retain its schedule');
        go(2); expect(document.querySelector('[data-facility-step-button="2"]').getAttribute('aria-current') === 'step', 'Progress not updated');
        layoutFits();
        const rule = $('rule-form');
        rule.elements.min_duration_minutes.value = '90';
        rule.dispatchEvent(new Event('submit', { cancelable: true }));
        expect(writes === 0 && !$('error').hidden, 'Duration order must be validated before saving');
        rule.elements.min_duration_minutes.value = '';
        rule.elements.refund_percentage.value = '101';
        rule.dispatchEvent(new Event('submit', { cancelable: true }));
        expect(rule.querySelector('details').open && writes === 0, 'Invalid advanced fields must be revealed before save');
        rule.elements.refund_percentage.value = '100';
        rule.elements.requires_approval.checked = true;
        rule.elements.requires_approval.dispatchEvent(new Event('change', { bubbles: true }));
        expect($('approval-hint').textContent.includes('اثری ندارد'), 'Approval precedence must be explained');
        rule.elements.requires_approval.checked = false;
        // Reopen the saved record to discard this deliberately unsaved rule draft.
        allowDiscard = true; $('close').click(); await until(() => $('list').querySelector('button'));
        $('list').querySelector('button').click(); await until(() => !$('base-fields').disabled);
        go(3); expect(!$('activate').disabled, 'Complete stored configuration must remain activatable');
        layoutFits();
        allowDiscard = false;
        go(0); const title = $('base-form').elements.title; title.value = 'عنوان جدید'; title.dispatchEvent(new Event('input', { bubbles: true }));
        $('close').click(); only('editor'); expect(title.value === 'عنوان جدید', 'Declining discard must preserve form');
        allowDiscard = true; $('close').click(); only('directory'); await until(() => $('list').querySelector('button'));
        $('change-building').click(); only('location'); expect(!$('location-continue').hidden && $('building').value === '4', 'Selected building must be resumable');
        $('location-continue').click(); await until(() => $('list').querySelector('button')); only('directory');
        holdDetail = true; $('list').querySelector('button').click(); await until(() => releaseDetail); only('editor');
        $('close').click(); only('directory'); holdDetail = false; releaseDetail();
        await new Promise(resolve => setTimeout(resolve, 10)); only('directory');
        failDetail = true; $('list').querySelector('button').click(); await until(() => !$('error').hidden); only('directory');
        failDetail = false; $('list').querySelector('button').click(); await until(() => !$('base-fields').disabled);
        const draft = $('base-form').elements.title; draft.value = 'پیش‌نویس محفوظ'; draft.dispatchEvent(new Event('input', { bubbles: true }));
        go(1); $('schedules').querySelector('button').click();
        const schedule = $('schedule-form'); schedule.elements.end_time.value = '09:00';
        schedule.dispatchEvent(new Event('submit', { cancelable: true }));
        expect(writes === 0 && !$('error').hidden, 'Reversed schedule hours must be rejected without a write');
        schedule.elements.end_time.value = '12:00'; failReadAfterWrite = true;
        schedule.dispatchEvent(new Event('submit', { cancelable: true }));
        await until(() => !$('refresh').hidden && !$('refresh').disabled);
        expect(writes === 1 && $('schedule-save').disabled, 'Successful write followed by failed refresh must prevent duplicate writes');
        expect(!document.querySelector('[data-facility-panel="1"]').hidden, 'Failed refresh must not advance the wizard');
        failReadAfterWrite = false; failDetail = false; $('refresh').click();
        await until(() => $('refresh').hidden && !$('schedule-save').disabled);
        expect(draft.value === 'پیش‌نویس محفوظ' && $('save-state').textContent.includes('اطلاعات پایه'), 'Refresh must preserve and report unsaved drafts from other sections');
        const access = window.BuildinoFacilityConfig.buildings[0]; access.canCreate = false; access.canUpdate = false;
        $('close').click(); await until(() => $('list').querySelector('button'));
        $('list').querySelector('button').click(); await until(() => !$('close').disabled && $('editor-title').textContent.includes('مشاهده'));
        expect($('base-save').hidden && $('base-fields').disabled, 'Viewers must not receive editable forms');
        const viewNext = document.querySelector('[data-facility-view-next="1"]');
        expect(!viewNext.hidden, 'Viewers need a visible way to continue'); viewNext.click();
        expect(!document.querySelector('[data-facility-panel="1"]').hidden && $('schedule-save').hidden, 'View-only navigation must retain permissions');
        go(3); expect($('activate').hidden, 'Viewers must not be offered activation');
        expect(!document.querySelector('[data-facility-flow]'), 'Directory must not become a configuration step');
        expect(document.documentElement.scrollWidth <= window.innerWidth, 'Layout must fit mobile viewport');
        if (${JSON.stringify(process.env.FACILITY_CAPTURE || '')}) {
            access.canCreate = true; access.canUpdate = true; $('close').click(); await until(() => $('list').querySelector('button'));
            if (${JSON.stringify(process.env.FACILITY_CAPTURE || '')} !== 'directory') {
                $('list').querySelector('button').click(); await until(() => !$('base-fields').disabled);
                go(({base:0,schedule:1,rules:2,review:3})[${JSON.stringify(process.env.FACILITY_CAPTURE || '')}] ?? 0);
            }
            window.scrollTo(0, 0);
        }
        document.getElementById('result').dataset.ok = 'true';
    })().catch(error => document.getElementById('result').textContent = error.stack);
    </script></html>`);
    try {
        for (const width of [1280, 500]) {
            const capture = process.env.FACILITY_CAPTURE ? ['--screenshot=' + join(tmpdir(), 'buildino-facilities-' + process.env.FACILITY_CAPTURE + '-' + width + '.png')] : [];
            const output = execFileSync(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--allow-file-access-from-files', '--window-size=' + width + ',1100', '--user-data-dir=' + join(directory, 'profile-' + width), '--virtual-time-budget=3000', '--dump-dom', ...capture, pathToFileURL(fixture).href], { encoding: 'utf8', timeout: 30000, maxBuffer: 1024 * 1024, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
            const result = output.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0] || output.slice(-1000);
            assert.match(result, /data-ok="true"/, result);
        }
    } finally {
        if (dirname(resolve(directory)) === resolve(tmpdir()) && directory.includes('buildino-facilities-')) rmSync(directory, { recursive: true, force: true, maxRetries: 3, retryDelay: 100 });
    }
});
