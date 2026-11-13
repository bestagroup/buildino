import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFile, execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { tmpdir } from 'node:os';
import { promisify } from 'node:util';
import { build } from 'esbuild';
import { compileScript, parse } from '@vue/compiler-sfc';

const project = fileURLToPath(new URL('../../', import.meta.url));
const domModule = process.env.JSDOM_MODULE;
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

test('credit notes: signed input, review, issue action, ordered finance tabs and responsive layout', { skip: !existsSync(chrome) && !domModule }, async () => {
    const configs = JSON.parse(execFileSync('php', ['-r', '$r=(require "config/management_crud.php")["resources"]; echo json_encode(array_filter($r, fn($v)=>$v["group"]==="finance"));'], { cwd: project, encoding: 'utf8' }));
    const chargeTabs = ['expense-categories','charge-formulas','expenses','charge-periods','invoices'];
    const groups = JSON.parse(execFileSync('php', ['-r', 'require "vendor/autoload.php"; echo json_encode(\\App\\Support\\ManagementPageCatalog::FINANCE_GROUPS);'], { cwd: project, encoding: 'utf8' }));
    const bundle = await build({
        absWorkingDir: project, bundle: true, write: false, format: 'iife', define: { 'process.env.NODE_ENV': '"production"' },
        stdin: { contents: `import {createApp} from 'vue'; import Invoices from './resources/js/pages/management/finance/Invoices.vue'; import Records from './resources/js/pages/management/finance/Records.vue'; import './resources/js/buildino-jalali-datepicker.js'; let app; window.mountFinance = props => { app?.unmount(); app=createApp(['invoices','invoice-installments','payments'].includes(props.resourceKey)?Invoices:Records,props); app.mount('#app'); };`, resolveDir: project },
        plugins: [{ name: 'vue-fixture', setup(builder) {
            builder.onResolve({ filter: /\.css$/ }, args => ({ path: args.path, namespace: 'style' }));
            builder.onLoad({ filter: /.*/, namespace: 'style' }, () => ({ contents: '', loader: 'js' }));
            builder.onResolve({ filter: /^@inertiajs\/vue3$/ }, () => ({ path: 'head', namespace: 'stub' }));
            builder.onLoad({ filter: /.*/, namespace: 'stub' }, () => ({ contents: 'export const Head={render:()=>null};' }));
            builder.onLoad({ filter: /\.vue$/ }, args => { const { descriptor } = parse(readFileSync(args.path,'utf8'),{filename:args.path}); return { contents:compileScript(descriptor,{id:'finance-test-'+args.path.split('/').pop(),inlineTemplate:true,fs:{fileExists:existsSync,readFile:path=>readFileSync(path,'utf8')}}).content,loader:'ts' }; });
        } }],
    });
    const html = `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    ${['/public/css/buildino-fonts.css','/public/css/buildino-management.css','/public/css/buildino-crud.css','/public/css/buildino-design-system.css','/public/css/buildino-unit-ownerships.css','/resources/css/management-inertia.css','/resources/css/finance-workspace.css','/resources/css/operation-records.css','/node_modules/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css'].map(path=>'<link rel="stylesheet" href="'+path+'">').join('')}
    <div id="app" style="padding:16px;max-width:1200px;margin:auto"></div><pre id="result" hidden></pre>
    <script>window.requestAnimationFrame=callback=>setTimeout(()=>callback(performance.now()),16); window.cancelAnimationFrame=clearTimeout; window.addEventListener('error',event=>document.querySelector('#result').textContent=event.message+' '+event.lineno);</script>
    <script src="/test.js"></script><script>
    const configs=${JSON.stringify(configs)}, groups=${JSON.stringify(groups)}, core=['invoices','invoice-installments','payments'], chargeTabs=${JSON.stringify(chargeTabs)};
    let failList=false, emptyList=false; let signedTotal=1001; let posts=[], requests=[], active='invoices', failSave=false, hold=false, release, invoiceStatus='draft', paymentStatus='pending', paymentMethod='cash', initial={building_id:'1'};
    const invoice=()=>({id:11,building_id:1,unit_id:101,invoice_number:'INV-11',status:invoiceStatus,issue_date:'2026-10-29',due_date:'2026-11-30',subtotal:1001,discount_amount:0,penalty_amount:0,total_amount:signedTotal,paid_amount:0,outstanding_amount:Math.max(0,signedTotal),credit_remaining_amount:Math.max(0,-signedTotal),unit:{unit_number:'۱۰۱'},payer:{name:'ساکن نمونه'},items:[{title:'شارژ ساختمان',quantity:1,unit_amount:1001,description:'شرح قبلی',metadata:{source:'preserve'},charge_item_id:7}],installments:[]});
    const sample=()=>({id:11,building_id:1,title:'سند مالی نمونه',amount:1001,status:'pending',version:1,event_type:'payment_verified',points:5,is_active:true,is_verified:false,account_holder_name:'مدیر ساختمان',iban:'IR123456789012345678901234',bank_name:'بانک نمونه',required_points:50,expense_date:'2026-10-29',income_date:'2026-10-29',allocation_method:'equal',payer_responsibility:'owner'});
    window.fetch=async(path,options={})=>{
        const url=new URL(path,location.origin); requests.push(url.pathname+url.search);
        const reply=(value,status=200)=>new Response(JSON.stringify(value),{status,headers:{'Content-Type':'application/json'}});
        if(options.method && options.method!=='GET') {
            posts.push({path:url.pathname,method:options.method,body:options.body?JSON.parse(options.body):{}});
            if(hold) await new Promise(resolve=>release=resolve);
            if(failSave) return reply({message:'عنوان نیاز به اصلاح دارد.',errors:{title:['عنوان نیاز به اصلاح دارد.']}},422);
            if(url.pathname.endsWith('/issue')) invoiceStatus='issued';
            return reply({data:invoice()},201);
        }
        if(failList) return reply({code:'SUBSCRIPTION_INACTIVE',message:'اشتراک ساختمان قابل استفاده نیست.'},403);
        if(emptyList) return reply({data:[],meta:{current_page:1,last_page:1,total:0}});
        if(url.pathname==='/api/v1/invoices/11') return reply({data:invoice()});
        const data=core.includes(active)?active==='payments'&&url.pathname.endsWith('/payments')?[{id:21,building_id:1,payment_number:'PAY-21',amount:1001,status:paymentStatus,method:paymentMethod}]:[invoice()]:[sample()];
        return reply({data,meta:{current_page:1,last_page:1,total:1},current_page:1,last_page:1,total:1});
    };
    const props=key=>{
        const prefix=configs[key].permission.split('.')[0], abilities={};
        for(const p of ['invoices','payments',prefix]) for(const a of ['view','create','update','delete','issue','adjust','verify','approve','reject','pay','complete','fail']) abilities[p+'.'+a]=true;
        return {resourceKey:key,title:configs[key].title,resource:configs[key],csrfToken:'test',workspace:{buildings:[1,2].map(id=>({id,title:'ساختمان '+id,currency:'IRR',abilities:{...abilities}})),units:[{id:101,building_id:1,label:'بلوک اصلی / همکف / واحد ۱۰۱'}],groups,chargeTabs,navigation:Object.keys(configs).map(key=>({key,title:configs[key].title,url:'/management/operations/'+key})),prefix,actionPermissions:Object.fromEntries((configs[key].actions||[]).map(a=>[a.key,prefix+'.'+(key==='loyalty-claims'?'update':a.key==='paid'?'pay':a.key)])),blocks:[],lookups:Object.fromEntries(['fund_id','financial_category_id','building_bank_account_id','block_id'].map(key=>[key,[{value:1,label:'گزینه مجاز',building_id:1}]])),initial:{...initial}}};
    };
    const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)], tick=()=>new Promise(r=>setTimeout(r,10));
    const expect=(value,message)=>{if(!value) throw Error(message);};
    const until=async predicate=>{for(let i=0;i<150;i++){if(predicate())return;await tick();}throw Error('Timed out: '+predicate+' / '+$('#app').textContent);};
    const input=async(name,value)=>{let el=$('[name="'+name+'"]');expect(el,'Missing input '+name);if(el.type==='hidden')el=el.previousElementSibling;el.value=value;el.dispatchEvent(new Event(el.tagName==='SELECT'?'change':'input',{bubbles:true}));await tick();};
    const submit=async()=>{const el=$('#finance-form')||$('#finance-record-form');el.dispatchEvent(new Event('submit',{cancelable:true}));await tick();};
    const click=async selector=>{$(selector).click();await tick();};
    const mount=async(key,override)=>{active=key;history.replaceState({},'','/management/operations/'+key);window.mountFinance(override||props(key));await until(()=>$('[data-finance-open]'));};
    const fillRequired=async()=>{for(const el of $$('.finance-editor [required]')){if(el.type==='checkbox'||el.disabled)continue;const value=el.tagName==='SELECT'?[...el.options].find(o=>o.value)?.value:el.dataset.buildinoDateType?'۱۴۰۵/۰۸/۰۷':el.type==='number'?'100':el.name==='iban'?'IR123456789012345678901234':'نمونه آزمایشی';await input(el.name||el.nextElementSibling?.name,value);}};
    (async()=>{
        await mount('invoices');
        expect($$('.finance-tabs a').map(a=>a.getAttribute('href').split('?')[0].split('/').pop()).join(',')===chargeTabs.join(','),'Financial tab order incorrect');
        expect($$('.finance-tabs a').every(a=>a.href.includes('building_id=1')),'Building context lost');
        await click('[data-finance-create]');await input('unit_id','101');await submit();
        await input('items.0.title','طلب قبلی واحد');await input('items.0.unit_amount','−۲۰۰٬۰۰۰');
        expect($('[name="items.0.unit_amount"]').value==='-200000','Signed Persian input not normalized');
        await submit();expect($('.finance-review').textContent.includes('۲۰۰٬۰۰۰'),'Credit absent from review');
        await submit();await until(()=>$('[data-finance-edit]'));
        expect(posts.at(-1).body.items[0].unit_amount===-200000,'Credit sign lost from request');
        signedTotal=-200000;await mount('invoices');await click('[data-finance-open]');
        expect($('[data-finance-issue]'),'Negative draft cannot be issued');
        expect(!$('[data-finance-pay]')&&!$('[data-finance-plan]'),'Credit note exposed a cash payment or installment action');
        expect($('#app').textContent.includes('مانده طلب برای آینده'),'Remaining credit is not visible');
        for(const key of ['expenses','expense-categories']) {
            await mount(key);expect($$('.finance-tabs a').length===5,'Charge tabs missing on '+key);
        }
        const restricted=props('invoices');restricted.workspace.navigation=restricted.workspace.navigation.filter(link=>link.key!=='expenses');
        await mount('invoices',restricted);expect($$('.finance-tabs a').length===4,'Unauthorized expense tab exposed');
        if (!${Boolean(domModule)}) expect(document.documentElement.scrollWidth<=innerWidth,'Page overflows viewport');
        for (const key of ['invoices','invoice-installments','payments','expenses','bank-accounts']) {
            await mount(key);
            failList=true;
            const refresh=()=>$$('button').find(button=>button.textContent.includes('به‌روزرسانی'));
            refresh().click(); await until(()=>$('[data-finance-load-failed]'));
            expect(!$('#app').textContent.includes('مورد ثبت‌شده'),'Failed request showed a record count: '+key);
            expect(!$('#app').textContent.includes('هنوز'),'Failed request showed empty-state: '+key);
            expect(!$('#app').textContent.includes('سند نمایش‌داده‌شده'),'Failed request showed a financial total: '+key);
            failList=false; refresh().click(); await until(()=>$('[data-finance-open]'));
            expect(!$('[data-finance-load-failed]'),'Retry did not recover: '+key);
            emptyList=true; refresh().click(); await until(()=>$('#app').textContent.includes('هنوز'));
            expect(!$('[data-finance-load-failed]'),'Successful empty response treated as failure: '+key);
            emptyList=false;
        }
        window.scrollTo(0,0);$('#result').dataset.ok='true';$('#result').textContent='Passed';
    })().catch(error=>{$('#result').textContent=error.stack;});
    </script></html>`;
    if (domModule) {
        const { JSDOM } = await import(domModule);
        const dom = new JSDOM(html.replace('<script src="/test.js"></script>', () => '<script>' + bundle.outputFiles[0].text.replace(/<\/script/gi, '<\\/script') + '</script>'), {
            url: 'http://localhost/management/operations/invoices', runScripts: 'dangerously', pretendToBeVisual: true,
            beforeParse(window) { window.Response = Response; window.scrollTo = () => {}; window.CSS = { escape: value => value }; window.matchMedia = () => ({ matches: false, addEventListener() {}, removeEventListener() {} }); },
        });
        try {
            for (let i=0;i<1000;i++) {
                const result = dom.window.document.querySelector('#result');
                if (result?.textContent) { assert.equal(result.dataset.ok, 'true', result.textContent); return; }
                await new Promise(resolve=>setTimeout(resolve,10));
            }
            assert.fail('DOM workflow timed out: '+dom.window.document.body.textContent);
        } finally { dom.window.close(); }
    }
    const server=createServer((request,response)=>{
        const path=new URL(request.url,'http://localhost').pathname;
        if(path==='/test.js'){response.setHeader('Content-Type','application/javascript');response.end(bundle.outputFiles[0].text);return;}
        if(path.startsWith('/public/')||path.startsWith('/resources/css/')||path.startsWith('/node_modules/')||path.startsWith('/fonts/')){
            try{const file=join(project,path.startsWith('/fonts/')?'public'+path:path.slice(1));response.setHeader('Content-Type',path.endsWith('.css')?'text/css':'font/woff2');response.end(readFileSync(file));}catch{response.writeHead(404).end();}return;
        }
        response.setHeader('Content-Type','text/html; charset=utf-8');response.end(html);
    });
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    try{
        for(const width of [1280,500]){
            const capture=process.env.FINANCE_CAPTURE?['--screenshot='+join(tmpdir(),'buildino-finance-'+process.env.FINANCE_CAPTURE+'-'+width+'.png')]:[];
            const {stdout}=await promisify(execFile)(chrome,['--headless','--disable-gpu','--no-sandbox','--no-first-run','--force-device-scale-factor=1','--window-size='+width+',1000','--virtual-time-budget=15000','--dump-dom',...capture,'http://127.0.0.1:'+server.address().port+'/management/operations/invoices'],{encoding:'utf8',timeout:30000,maxBuffer:3*1024*1024,windowsHide:true});
            const result=stdout.match(/<pre id="result"[^>]*>([\s\S]*?)<\/pre>/)?.[0]||stdout.slice(-1000);assert.match(result,/data-ok="true"/,width+': '+result);
        }
    }finally{await new Promise(resolve=>server.close(resolve));}
});
