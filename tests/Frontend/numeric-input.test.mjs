import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { build } from 'esbuild';
import { compileScript, parse } from '@vue/compiler-sfc';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
test('numeric fields group typing, preserve raw models and submission, validate limits and keep identifiers intact', { skip: !existsSync(chrome) }, async () => {
    const bundle = await build({ absWorkingDir: project, bundle: true, write: false, format: 'iife', define: { 'process.env.NODE_ENV': '"production"' },
        stdin: { resolveDir: project, contents: `import {createApp,h,ref} from 'vue';import UiNumberInput from './resources/js/components/management/UiNumberInput.vue';
        const value=ref('');window.numericModel=value;
        createApp({render:()=>h(UiNumberInput,{id:'vue-number',name:'vue_amount',modelValue:value.value,min:-1000000,max:1000000,step:0.01,'onUpdate:modelValue':v=>value.value=v})}).mount('#vue-app');` },
        plugins: [{ name: 'vue', setup(builder) { builder.onLoad({ filter: /\.vue$/ }, args => {
            const { descriptor } = parse(readFileSync(args.path, 'utf8'), { filename: args.path });
            return { contents: compileScript(descriptor, { id: 'numeric-test', inlineTemplate: true }).content, loader: 'ts' };
        }); } }],
    });
    const directory = mkdtempSync(join(tmpdir(), 'buildino-numeric-'));
    try {
        const html = `<!doctype html><html dir="rtl"><meta charset="utf-8"><form id="form">
        <input id="amount" type="number" name="amount" min="-2000000" max="2000000" step="0.01" value="1234.50" required>
        <input id="count" type="number" name="count" min="0" max="10000" step="1">
        <input id="phone" type="tel" name="mobile" value="09123456789"><input name="postal_code" value="0012345678">
        <button id="submit" type="submit">ثبت</button><button id="read" type="button">بررسی</button></form>
        <form id="vue-form"><div id="vue-app"></div></form><pre id="result"></pre>
        <script>${bundle.outputFiles[0].text}</script><script>
        const tick=()=>new Promise(r=>setTimeout(r,0)),expect=(value,message)=>{if(!value)throw Error(message);};
        const $=id=>document.getElementById(id);
        const enter=async(input,value)=>{input.focus();input.value=value;input.dispatchEvent(new Event('input',{bubbles:true}));await tick();};
        window.addEventListener('DOMContentLoaded',()=>{(async()=>{
            await tick();
            expect(!$('form').querySelector('input[type=number]'),'Native number fields remain');
            expect($('amount').value==='1,234.50','Initial decimal precision lost');
            let rawInput='',rawChange='',rawClick='',submitted=null;
            $('amount').addEventListener('input',()=>rawInput=$('amount').value);
            $('amount').addEventListener('change',()=>rawChange=$('amount').value);
            $('read').addEventListener('click',()=>rawClick=$('amount').value);
            $('form').addEventListener('submit',event=>{event.preventDefault();submitted=Object.fromEntries(new FormData($('form')));});
            await enter($('amount'),'−۱۲۳۴۵۶٫۷۰');
            expect($('amount').value==='-123,456.70'&&rawInput==='-123456.70','Signed Persian decimal/model mismatch');
            $('amount').dispatchEvent(new Event('change',{bubbles:true}));await tick();
            expect(rawChange==='-123456.70','Change handler received separator');
            $('read').click();await tick();expect(rawClick==='-123456.70','Click calculation received separator');
            expect(new FormData($('form')).get('amount')==='-123456.70','FormData contains separators');
            await enter($('count'),'١٢٣٤');expect($('count').value==='1,234','Arabic digits not grouped');
            $('form').requestSubmit();await tick();
            expect(submitted.amount==='-123456.70'&&submitted.count==='1234','Submit lost raw numeric values');
            expect(submitted.mobile==='09123456789'&&submitted.postal_code==='0012345678','Identifiers changed');
            await enter($('count'),'1.5');expect(!$('count').checkValidity(),'Integer step was lost');
            await enter($('count'),'10001');expect(!$('count').checkValidity(),'Maximum was lost');
            await enter($('count'),'-1');expect(!$('count').checkValidity(),'Minimum was lost');
            await enter($('count'),'abc');expect(!$('count').checkValidity(),'Nonnumeric input accepted');
            await enter($('count'),'');expect($('count').checkValidity(),'Optional blank rejected');
            await enter($('amount'),'');expect(!$('amount').checkValidity(),'Required blank accepted');
            await enter($('amount'),'123456');$('amount').setSelectionRange(2,2);$('amount').dispatchEvent(new Event('input',{bubbles:true}));await tick();
            expect($('amount').selectionStart===2,'Caret jumped while editing');
            $('form').reset();await tick();expect($('amount').value==='1,234.50'&&$('amount').checkValidity(),'Reset failed');
            const dynamic=document.createElement('input');dynamic.type='number';dynamic.value='1234567';$('form').append(dynamic);await tick();
            expect(dynamic.type==='text'&&dynamic.value==='1,234,567','Dynamic field not enhanced');
            await enter($('vue-number'),'-۱۲۳۴٫۵۰');
            expect($('vue-number').value==='-1,234.50'&&window.numericModel.value==='-1234.50','Vue model is formatted or trailing zero lost');
            expect(new FormData($('vue-form')).get('vue_amount')==='-1234.50','Vue native FormData is formatted');
            window.numericModel.value='765432.10';await tick();expect($('vue-number').value==='765,432.10','External model change not displayed');
            await enter($('vue-number'),'2000000');expect(!$('vue-number').checkValidity(),'Vue maximum ignored');
            window.numericModel.value='1000';await tick();expect($('vue-number').checkValidity(),'Vue external correction kept stale validity');
            $('result').dataset.ok='true';
        })().catch(error=>$('result').textContent=error.stack);});
        </script></html>`;
        const path = join(directory, 'index.html'); writeFileSync(path, html);
        const result = execFileSync(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--virtual-time-budget=3000', '--dump-dom', pathToFileURL(path).href],
            { encoding: 'utf8', timeout: 30000, maxBuffer: 2 * 1024 * 1024, windowsHide: true, stdio: ['ignore', 'pipe', 'ignore'] });
        assert.match(result.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0] || 'Missing browser result', /data-ok="true"/);
    } finally { rmSync(directory, { recursive: true, force: true }); }
});
