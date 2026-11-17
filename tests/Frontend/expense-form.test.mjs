import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { build } from 'esbuild';
import { compileScript, parse } from '@vue/compiler-sfc';

test('expense form scopes, automatic title, legacy editing and minimal payload', { skip: !process.env.JSDOM_MODULE }, async () => {
    const { JSDOM } = await import(process.env.JSDOM_MODULE);
    const config = JSON.parse(execFileSync(process.env.PHP_BIN || 'php', ['-r', 'echo json_encode((require "config/management_crud.php")["resources"]["expenses"]);'], { encoding: 'utf8' }));
    const bundle = await build({ bundle: true, write: false, format: 'iife', define: { 'process.env.NODE_ENV': '"production"' },
        stdin: { contents: `import {createApp} from 'vue'; import './resources/js/buildino-jalali-datepicker.js'; import Records from './resources/js/pages/management/finance/Records.vue'; let app; window.mountExpense=props=>{app?.unmount();app=createApp(Records,props);app.mount('#app')};`, resolveDir: process.cwd() },
        plugins: [{ name: 'vue-test', setup(builder) {
            builder.onResolve({ filter: /\.css$/ }, args => ({ path: args.path, namespace: 'style' }));
            builder.onLoad({ filter: /.*/, namespace: 'style' }, () => ({ contents: '', loader: 'js' }));
            builder.onResolve({ filter: /^@inertiajs\/vue3$/ }, () => ({ path: 'head', namespace: 'stub' }));
            builder.onLoad({ filter: /.*/, namespace: 'stub' }, () => ({ contents: 'export const Head={render:()=>null};' }));
            builder.onLoad({ filter: /\.vue$/ }, args => { const { descriptor } = parse(readFileSync(args.path, 'utf8')); return { contents: compileScript(descriptor, { id: args.path, inlineTemplate: true }).content, loader: 'ts' }; });
        } }],
    });
    const dom = new JSDOM('<div id="app"></div>', { url: 'https://example.test/management/operations/expenses', runScripts: 'outside-only', pretendToBeVisual: true });
    const w = dom.window, writes = [];
    const row = { id: 9, building_id: 1, block_id: null, title: 'عنوان قبلی', financial_category_id: 7, payer_responsibility: 'unit', amount: 500, expense_date: '2026-11-13', payment_status: 'paid', fund_id: 12 };
    w.HTMLElement.prototype.scrollIntoView = () => {};
    w.fetch = async (url, options = {}) => { if (options.method && options.method !== 'GET') writes.push(JSON.parse(options.body)); return { ok: true, status: 200, headers: { get: () => 'application/json' }, json: async () => ({ data: [row], meta: { total: 1 } }) }; };
    w.eval(bundle.outputFiles[0].text);
    const tick = () => new Promise(resolve => setTimeout(resolve, 15));
    const $ = selector => w.document.querySelector(selector);
    const input = async (name, value) => { let el = $(`[name="${name}"]`); assert.ok(el, name); if (el.type === 'hidden') el = el.previousElementSibling; el.value = value; el.dispatchEvent(new w.Event(el.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true })); await tick(); };
    const props = (blockCount = 1, buildingCount = 1, wholeBuilding = true) => ({ resourceKey: 'expenses', title: config.title, resource: config, csrfToken: 'test', workspace: {
        prefix: 'expenses', actionPermissions: {}, initial: {}, units: [{id: 1, building_id: 1, block_id: 1, label: 'واحد یک'}], periods: [{id: 4, building_id: 1, title: 'آبان', status: 'draft'}, {id: 5, building_id: 1, title: 'بسته', status: 'closed'}], navigation: [], groups: [],
        buildings: Array.from({ length: buildingCount }, (_, i) => ({ id: i + 1, title: 'ساختمان ' + (i + 1), currency: 'IRR', abilities: { 'expenses.create': wholeBuilding, 'expenses.update': wholeBuilding } })),
        blocks: Array.from({ length: blockCount }, (_, i) => ({ value: i + 1, label: 'بلوک ' + (i + 1), building_id: 1, abilities: { create: true, update: true } })),
        lookups: { category_parent_id: [{value: 3, label: 'انرژی', building_id: 1}, {value: 99, label: 'بدون فرمول', building_id: 1}], financial_category_id: [{ value: 7, label: 'قبض آب', building_id: 1, parent_id: 3, parent_title: 'انرژی' }], block_id: Array.from({ length: blockCount }, (_, i) => ({ value: i + 1, label: 'بلوک ' + (i + 1), building_id: 1, abilities: { create: true } })) },
    } });
    try {
        w.mountExpense(props()); await tick();
        assert.equal($('[data-finance-building]'), null);
        $('[data-finance-create]').click(); await tick();
        for (const field of ['block_id', 'fund_id', 'payment_status']) assert.equal($(`[name="${field}"]`), null, field);
        assert.ok($('[data-expense-scope]').textContent.includes('بلوک 1'));
        assert.equal($('[name="payer_responsibility"] option[value="unit"]'), null);
        await input('category_parent_id', '3'); await input('financial_category_id', '7');
        assert.equal($('[name="title"]').value, 'قبض آب');
        await input('title', 'قبض آب آبان');
        await input('bill_total', '۵۰۰');
        assert.equal($('[name="category_parent_id"] option[value="99"]').textContent, 'بدون فرمول');
        for (const field of ['distribution_mode', 'expense_date']) assert.equal($(`[name="${field}"]`), null);
        assert.equal($('[data-bill-add-line]'), null);
        await input('start_charge_period_id', '4');
        assert.equal($('[name="start_charge_period_id"] option[value="5"]'), null);
        await input('unit_scope', 'selected');
        const target = $('[name="target_unit_ids"]'); target.options[0].selected = true; target.dispatchEvent(new w.Event('change', {bubbles: true})); await tick();
        assert.ok($('[name="installment_amount"]').readOnly);
        assert.equal($('[name="installment_amount"]').value, '۵۰۰');
        await input('period_installment_count', '3');
        assert.equal($('[name="installment_amount"]').value, '۱۶۶');
        assert.ok($('#finance-record-form').textContent.includes('۱۶۸'));
        await input('period_installment_count', '1');
        const names = [...w.document.querySelectorAll('#finance-record-form [name]')].map(el => el.name);
        const ordered = ['category_parent_id','financial_category_id','title','payer_responsibility','invoice_number','unit_scope','start_charge_period_id','period_installment_count','bill_total','installment_amount','description'];
        assert.deepEqual(names.filter(name => ordered.includes(name)), ordered);

        $('#finance-record-form').dispatchEvent(new w.Event('submit', { cancelable: true })); await tick();
        $('#finance-record-form').dispatchEvent(new w.Event('submit', { cancelable: true })); await tick();
        assert.equal(writes.length, 1, $('#app').textContent);
        assert.deepEqual(Array.from(writes[0].target_unit_ids), [1]); assert.equal(writes[0].items[0].unit_amount, 500); assert.equal(writes[0].start_charge_period_id, '4');
        assert.equal(String(writes[0].block_id), '1'); assert.equal(writes[0].title, 'قبض آب آبان');
        for (const field of ['fund_id', 'payment_status', 'category_parent_id']) assert.equal(field in writes[0], false);
        $('[data-finance-open]').click(); await tick(); $('[data-finance-edit]').click(); await tick();
        assert.equal($('[name="title"]').value, 'عنوان قبلی');
        assert.equal($('[name="payer_responsibility"]').value, '');
        assert.ok($('[data-expense-scope]').textContent.includes('کل ساختمان'));
        w.mountExpense(props(2)); await tick(); $('[data-finance-create]').click(); await tick(); assert.equal($('[name="block_id"]'), null);
        w.mountExpense(props(1, 1, false)); await tick(); $('[data-finance-create]').click(); await tick(); assert.equal($('[name="block_id"]'), null); assert.ok($('[data-expense-scope]').textContent.includes('بلوک 1'));
        w.mountExpense(props(1, 2)); await tick(); assert.ok($('[data-finance-building]')); assert.equal($('[data-finance-create]'), null);
    } finally { dom.window.close(); }
});
