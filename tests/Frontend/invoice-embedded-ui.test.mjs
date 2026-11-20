import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';
import { build } from 'esbuild';
import { compileScript, parse } from '@vue/compiler-sfc';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
test('unit invoices: archive return only on standalone page, scoped list, details and payment inside workspace', { skip: !existsSync(chrome) }, async () => {
    const bundle = await build({ absWorkingDir: project, bundle: true, write: false, format: 'iife', define: { 'process.env.NODE_ENV': '"production"' },
        stdin: { contents: `import {createApp} from 'vue';import Page from './resources/js/pages/management/finance/Invoices.vue';let app;window.mountInvoice=props=>{app?.unmount();app=createApp(Page,props);app.mount('#app');};`, resolveDir: project },
        plugins: [{ name: 'vue-test', setup(builder) {
            builder.onResolve({ filter: /\.css$/ }, args => ({ path: args.path, namespace: 'style' }));
            builder.onLoad({ filter: /.*/, namespace: 'style' }, () => ({ contents: '', loader: 'js' }));
            builder.onResolve({ filter: /^@inertiajs\/vue3$/ }, () => ({ path: 'head', namespace: 'stub' }));
            builder.onLoad({ filter: /.*/, namespace: 'stub' }, () => ({ contents: 'export const Head={render:()=>null};' }));
            builder.onLoad({ filter: /\.vue$/ }, args => {
                const { descriptor } = parse(readFileSync(args.path, 'utf8'), { filename: args.path });
                return { contents: compileScript(descriptor, { id: args.path, inlineTemplate: true, fs: { fileExists: existsSync, readFile: path => readFileSync(path, 'utf8') } }).content, loader: 'ts' };
            });
        } }],
    });
    const styles = ['buildino-management', 'buildino-design-system', 'buildino-unit-ownerships', 'buildino-unit-workspace'];
    const html = `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${styles.map(name => '<link rel="stylesheet" href="/public/css/' + name + '.css">').join('')}<link rel="stylesheet" href="/resources/css/finance-workspace.css"><body><div id="app"></div><pre id="result" hidden></pre><script src="/test.js"></script><script>
    const $=s=>document.querySelector(s),expect=(value,message)=>{if(!value)throw Error(message);};
    const tick=()=>new Promise(resolve=>setTimeout(resolve,15));
    const until=async predicate=>{for(let i=0;i<120;i++){if(predicate())return;await tick();}throw Error('Timed out '+predicate);};
    const invoice={id:7,building_id:1,unit_id:101,invoice_number:'INV-7',status:'issued',total_amount:5000,outstanding_amount:5000,paid_amount:0,items:[],installments:[],unit:{unit_number:'۱۰۱'}};
    let requests=[];
    window.fetch=async(path,options={})=>{requests.push(String(path));return new Response(JSON.stringify({data:String(path).includes('/invoices/7')?invoice:[invoice],meta:{total:1,last_page:1}}),{headers:{'Content-Type':'application/json'}});};
    const props={archiveOnly:true,resourceKey:'invoices',title:'صورتحساب‌ها',csrfToken:'test',workspace:{initial:{building_id:'1',unit_id:'101'},navigation:[],groups:[],periods:[],buildings:[{id:1,title:'ساختمان',currency:'IRR',abilities:{'invoices.view':true,'payments.create':true}}],units:[{id:101,building_id:1,label:'واحد ۱۰۱'}]}};
    (async()=>{
        mountInvoice(props);await until(()=>$('[data-finance-open]'));
        expect($('[data-finance-archive-back]'),'Standalone archive lost return');
        document.body.setAttribute('data-glance-embedded','');mountInvoice(props);await until(()=>$('[data-finance-open]'));
        expect(!$('[data-finance-archive-back]'),'Embedded archive still shows return');
        expect(requests.at(-1).includes('/units/101/invoices?'),'Unit scope lost');
        $('[data-finance-open]').click();await until(()=>$('[data-finance-pay]'));
        expect($('#finance-detail-title')&&document.documentElement.scrollWidth<=innerWidth,'Invoice details failed or overflow');
        $('[data-finance-pay]').click();await until(()=>$('#finance-form'));
        expect($('.finance-editor')&&!$('#finance-form [name="unit_id"]'),'Payment must remain tied to selected invoice');
        const back=[...document.querySelectorAll('.finance-editor button')].find(el=>el.textContent.trim()==='بازگشت');back.click();await until(()=>$('[data-finance-pay]'));
        [...document.querySelectorAll('button')].find(el=>el.textContent.trim()==='بازگشت به فهرست').click();await until(()=>$('[data-finance-open]'));
        expect(!$('[data-finance-archive-back]'),'Return reappeared after detail');
        $('#result').dataset.ok='true';
    })().catch(error=>$('#result').textContent=error.stack);
    </script></body></html>`;
    const server = createServer((req, res) => {
        const path = new URL(req.url, 'http://localhost').pathname;
        if (path === '/test.js') { res.setHeader('Content-Type', 'application/javascript'); res.end(bundle.outputFiles[0].text); return; }
        if (path.endsWith('.css')) { res.setHeader('Content-Type', 'text/css'); res.end(readFileSync(join(project, path))); return; }
        res.setHeader('Content-Type', 'text/html; charset=utf-8'); res.end(html);
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    try {
        for (const width of [1280, 390]) {
            const { stdout } = await promisify(execFile)(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--window-size=' + width + ',900', '--virtual-time-budget=6000', '--dump-dom', 'http://127.0.0.1:' + server.address().port], { timeout: 30000, maxBuffer: 2 * 1024 * 1024, windowsHide: true });
            assert.match(stdout.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0] || stdout.slice(-1000), /data-ok="true"/, String(width));
        }
    } finally { await new Promise(resolve => server.close(resolve)); }
});
