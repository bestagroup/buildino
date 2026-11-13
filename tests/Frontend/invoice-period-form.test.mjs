import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { build } from 'esbuild';
import { compileScript, parse } from '@vue/compiler-sfc';

test('invoice form selects charge period and 1–5 installments without dates or financial summaries', { skip: !process.env.JSDOM_MODULE }, async () => {
    const { JSDOM } = await import(process.env.JSDOM_MODULE);
    const bundle = await build({ bundle: true, write: false, format: 'iife', define: { 'process.env.NODE_ENV': '"production"' },
        stdin: { contents: `import {createApp} from 'vue'; import './resources/js/buildino-jalali-datepicker.js'; import Invoices from './resources/js/pages/management/finance/Invoices.vue'; let app; window.mountInvoice=props=>{app?.unmount();app=createApp(Invoices,props);app.mount('#app')};`, resolveDir: process.cwd() },
        plugins: [{ name: 'vue-test', setup(builder) {
            builder.onResolve({ filter: /\.css$/ }, args => ({ path: args.path, namespace: 'style' }));
            builder.onLoad({ filter: /.*/, namespace: 'style' }, () => ({ contents: '', loader: 'js' }));
            builder.onResolve({ filter: /^@inertiajs\/vue3$/ }, () => ({ path: 'head', namespace: 'stub' }));
            builder.onLoad({ filter: /.*/, namespace: 'stub' }, () => ({ contents: 'export const Head={render:()=>null};' }));
            builder.onLoad({ filter: /\.vue$/ }, args => { const { descriptor } = parse(readFileSync(args.path, 'utf8')); return { contents: compileScript(descriptor, { id: args.path, inlineTemplate: true }).content, loader: 'ts' }; });
        } }],
    });

    const dom = new JSDOM('<div id="app"></div>', { url: 'https://example.test/management/operations/invoices', runScripts: 'outside-only', pretendToBeVisual: true });
    const w = dom.window, writes = [];
    w.CSS = { escape: value => value };
    w.HTMLElement.prototype.scrollIntoView = () => {};
    let row = { id: 9, building_id: 1, unit_id: 1, invoice_number: 'INV-9', status: 'draft', total_amount: 1001, outstanding_amount: 1001, paid_amount: 0, start_charge_period_id: 4, period_installment_count: 5,
        period_allocations: [{ period_id: 4, title: 'مهر', sequence: 1, amount: 200, issued_invoice_id: null }], items: [{ title: 'بدهی', quantity: 1, unit_amount: 1001 }] };
    w.fetch = async (url, options = {}) => {
        if (options.method && options.method !== 'GET') writes.push(JSON.parse(options.body));
        const detail = String(url).endsWith('/invoices/9') || options.method === 'POST';
        return { ok: true, status: 200, headers: { get: () => 'application/json' }, json: async () => ({ data: detail ? row : [row], meta: { total: 1 } }) };
    };
    w.eval(bundle.outputFiles[0].text);
    const tick = () => new Promise(resolve => setTimeout(resolve, 20));
    const $ = selector => w.document.querySelector(selector);
    const input = async (name, value) => { const el = $(`[name="${name}"]`); assert.ok(el, name); el.value = value; el.dispatchEvent(new w.Event(el.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true })); await tick(); };
    const props = { resourceKey: 'invoices', title: 'صورتحساب واحد', csrfToken: 'test', workspace: {
        prefix: 'invoices', actionPermissions: {}, initial: { building_id: '1' }, navigation: [], groups: [], blocks: [], lookups: {},
        buildings: [{ id: 1, title: 'ساختمان', currency: 'IRR', abilities: { 'invoices.view': true, 'invoices.create': true, 'invoices.update': true, 'invoices.issue': true } }],
        units: [{ id: 1, building_id: 1, label: 'واحد ۱' }],
        periods: [{ id: 4, building_id: 1, title: 'مهر', status: 'draft' }, { id: 8, building_id: 2, title: 'خارج محدوده', status: 'draft' }],
    } };
    try {
        w.mountInvoice(props); await tick(); $('[data-finance-create]').click(); await tick();
        for (const name of ['issue_date', 'due_date', 'period_start', 'period_end', 'discount_amount', 'penalty_amount']) assert.equal($(`[name="${name}"]`), null);
        assert.equal($('[name="start_charge_period_id"] option[value="8"]'), null);
        assert.equal($('[name="period_installment_count"] option[value="6"]'), null);
        assert.equal($('.finance-metrics'), null); assert.equal($('.finance-line-total'), null);
        await input('unit_id', '1'); await input('start_charge_period_id', '4'); await input('period_installment_count', '5');
        await input('items.0.title', 'بدهی قبلی'); await input('items.0.unit_amount', '۱۰۰۱');
        $('#finance-form').dispatchEvent(new w.Event('submit', { cancelable: true })); await tick();
        assert.equal(writes.length, 0);
        assert.ok($('.finance-review').textContent.includes('مهر'));
        $('#finance-form').dispatchEvent(new w.Event('submit', { cancelable: true }));
        $('#finance-form').dispatchEvent(new w.Event('submit', { cancelable: true })); await tick();
        assert.equal(writes.length, 1, $('#app').textContent);
        assert.equal(writes[0].start_charge_period_id, 4); assert.equal(writes[0].period_installment_count, 5);
        assert.equal(writes[0].items[0].unit_amount, 1001);
        assert.equal('issue_date' in writes[0], false);
        assert.equal($('[data-finance-issue]'), null); assert.equal($('[data-finance-plan]'), null);
        assert.ok($('#app').textContent.includes('در انتظار محاسبه و صدور دوره'));
    } finally { dom.window.close(); }
});
