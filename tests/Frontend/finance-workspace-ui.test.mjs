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
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

test('finance: real Vue workflows, Jalali dates, financial safeguards, permissions and responsive layout', { skip: !existsSync(chrome) }, async () => {
    const configs = JSON.parse(execFileSync('php', ['-r', '$r=(require "config/management_crud.php")["resources"]; echo json_encode(array_filter($r, fn($v)=>$v["group"]==="finance"));'], { cwd: project, encoding: 'utf8' }));
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
    ${['/public/css/buildino-fonts.css','/public/css/buildino-management.css','/public/css/buildino-crud.css','/public/css/buildino-design-system.css','/public/css/buildino-unit-ownerships.css','/resources/css/management-inertia.css','/resources/css/finance-workspace.css','/node_modules/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css'].map(path=>'<link rel="stylesheet" href="'+path+'">').join('')}
    <div id="app" style="padding:16px;max-width:1200px;margin:auto"></div><pre id="result" hidden></pre>
    <script>window.requestAnimationFrame=callback=>setTimeout(()=>callback(performance.now()),16); window.cancelAnimationFrame=clearTimeout; window.addEventListener('error',event=>document.querySelector('#result').textContent=event.message+' '+event.lineno);</script>
    <script src="/test.js"></script><script>
    const configs=${JSON.stringify(configs)}, groups=${JSON.stringify(groups)}, core=['invoices','invoice-installments','payments'];
    let posts=[], requests=[], active='invoices', failSave=false, hold=false, release, invoiceStatus='draft', paymentStatus='pending', paymentMethod='cash', initial={building_id:'1'};
    const invoice=()=>({id:11,building_id:1,unit_id:101,invoice_number:'INV-11',status:invoiceStatus,issue_date:'2026-10-29',due_date:'2026-11-30',subtotal:1001,discount_amount:0,penalty_amount:0,total_amount:1001,paid_amount:0,outstanding_amount:1001,unit:{unit_number:'۱۰۱'},payer:{name:'ساکن نمونه'},items:[{title:'شارژ ساختمان',quantity:1,unit_amount:1001,description:'شرح قبلی',metadata:{source:'preserve'},charge_item_id:7}],installments:[]});
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
        if(url.pathname==='/api/v1/invoices/11') return reply({data:invoice()});
        const data=core.includes(active)?active==='payments'&&url.pathname.endsWith('/payments')?[{id:21,building_id:1,payment_number:'PAY-21',amount:1001,status:paymentStatus,method:paymentMethod}]:[invoice()]:[sample()];
        return reply({data,meta:{current_page:1,last_page:1,total:1},current_page:1,last_page:1,total:1});
    };
    const props=key=>{
        const prefix=configs[key].permission.split('.')[0], abilities={};
        for(const p of ['invoices','payments',prefix]) for(const a of ['view','create','update','delete','issue','adjust','verify','approve','reject','pay','complete','fail']) abilities[p+'.'+a]=true;
        return {resourceKey:key,title:configs[key].title,resource:configs[key],csrfToken:'test',workspace:{buildings:[1,2].map(id=>({id,title:'ساختمان '+id,currency:'IRR',abilities:{...abilities}})),units:[{id:101,building_id:1,label:'بلوک اصلی / همکف / واحد ۱۰۱'}],groups,navigation:Object.keys(configs).map(key=>({key,title:configs[key].title,url:'/management/operations/'+key})),prefix,actionPermissions:Object.fromEntries((configs[key].actions||[]).map(a=>[a.key,prefix+'.'+(key==='loyalty-claims'?'update':a.key==='paid'?'pay':a.key)])),blocks:[],lookups:Object.fromEntries(['fund_id','financial_category_id','building_bank_account_id','block_id'].map(key=>[key,[{value:1,label:'گزینه مجاز',building_id:1}]])),initial:{...initial}}};
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
        for(const key of ['invoices','expenses']){const cached=props(key);cached.workspace.groups=null;await mount(key,cached);expect($('[data-finance-create]'),'Missing groups blanked workspace '+key);}
        await mount('invoices'); expect($$('.finance-tabs a').length===3,'Related navigation must be grouped');
        await click('[data-finance-create]');expect(!$('.finance-table'),'Editor did not replace list');await submit();expect($('[aria-invalid="true"]')&&posts.length===0,'Missing unit accepted');
        await input('unit_id','101');await input('issue_date','۱۴۰۵/۰۸/۰۷');await input('due_date','۱۴۰۵/۰۹/۰۹');
        expect($('[name="issue_date"]').value==='2026-10-29','Jalali date did not synchronize canonical value');
        await submit();await input('items.0.title','شارژ آبان');await input('items.0.quantity','2');await input('items.0.unit_amount','500');await input('discount_amount','100');await input('penalty_amount','10');await submit();
        expect($('.finance-review').textContent.includes('۹۱۰')&&posts.length===0,'Invoice total/review wrong');
        hold=true;await submit();await until(()=>release);const n=posts.length;await submit();expect(posts.length===n,'Duplicate invoice write');hold=false;release();await until(()=>$('[data-finance-edit]'));
        expect(posts.at(-1).path==='/api/v1/units/101/invoices'&&posts.at(-1).body.issue_date==='2026-10-29'&&posts.at(-1).body.items[0].quantity===2,'Invoice payload wrong');
        await click('[data-finance-edit]');await submit();await input('items.0.title','ویرایش عنوان');await submit();await submit();await until(()=>$('[data-finance-issue]'));
        expect(posts.at(-1).body.items[0].metadata.source==='preserve'&&posts.at(-1).body.items[0].description==='شرح قبلی'&&posts.at(-1).body.items[0].charge_item_id===7,'Editing lost item metadata');
        const beforeIssue=posts.length;await click('[data-finance-issue]');expect($('[role="alertdialog"]')&&posts.length===beforeIssue,'Issue ran without confirmation');await click('[role="alertdialog"] .ui-dialog__actions button:last-child');await until(()=>$('[data-finance-plan]'));
        expect(!$('[data-finance-edit]')&&$('[data-finance-pay]'),'Issued invoice exposes draft editing');
        await click('[data-finance-plan]');await submit();expect($('[data-finance-error]')&&$('#finance-step-title').textContent.includes('برنامه'),'Invalid installments advanced');
        $('.ownership-more').open=true;await tick();await input('first_due','۱۴۰۷/۰۱/۰۱');const generator=$$('.ownership-more button').find(el=>el.textContent.includes('ساخت برنامه'));generator.click();await tick();
        expect($('[name="installments.0.due_date"]').value==='2028-03-20','Installment calendar lost canonical date');await submit();await submit();await until(()=>$('[data-finance-plan]'));
        const installments=posts.at(-1).body.installments;expect(installments.length===3&&installments.reduce((s,r)=>s+r.amount,0)===1001&&installments[2].amount===335,'Installment split lost remainder');
        await click('[data-finance-pay]');await input('amount','1002');await submit();expect($('#finance-step-title').textContent.includes('اطلاعات'),'Overpayment accepted');await input('amount','100');await submit();await submit();await until(()=>$('[data-finance-pay]'));
        expect(posts.at(-1).body.idempotency_key.length===36&&posts.at(-1).body.amount===100,'Payment idempotency missing');
        for(const [status,method,verify] of [['pending','cash',true],['processing','cash',true],['pending','online',false],['paid','cash',false]]){
            paymentStatus=status;paymentMethod=method;await mount('payments');await click('[data-finance-open]');expect(Boolean($('[data-finance-verify]'))===verify,'Payment verification gating '+status+'/'+method);if(status==='paid')expect($('a[href$="/receipt"]'),'Paid receipt missing');
        }
        await mount('invoice-installments');expect(!$('[data-finance-create]'),'Installment list cannot create invoice');
        for(const key of Object.keys(configs).filter(key=>!core.includes(key)&&!['charge-formulas','charge-periods'].includes(key))){
            await mount(key);expect(!$('.crud-sibling-nav'),'Legacy finance tabs remain');await click('[data-finance-open]');
            if(key==='bank-accounts'){await click('[data-finance-action="verify"]');expect($('.finance-review'),'Zero-field operation lacks review');const before=posts.length;await submit();await until(()=>$('[data-finance-open]'));expect(posts.length===before+1,'Bank verification not submitted');}
            else {const back=$$('.ownership-card button').find(el=>el.textContent.includes('بازگشت به فهرست'));back.click();await tick();}
            if(!$('[data-finance-create]'))continue;
            await click('[data-finance-create]');expect(!$('textarea[name="configuration"]')&&!$('.finance-table'),'Raw JSON or old list in editor');
            await fillRequired();await submit();
            if(!$('.finance-review')){await fillRequired();if(key==='loyalty-rules'){await input('starts_at','۱۴۰۷/۰۱/۰۱ ۱۴:۳۰');await input('ends_at','۱۴۰۷/۰۱/۰۲ ۱۴:۳۰');}await submit();}
            expect($('.finance-review'),'Missing final review for '+key+' / '+$('#app').textContent);
            const before=posts.length;await submit();await until(()=>$('[data-finance-open]'));expect(posts.length===before+1,'Generic write failed '+key);
            expect(!posts.at(-1).path.includes('{'),'Unresolved endpoint '+key);
            if(key==='loyalty-rules')expect(posts.at(-1).body.starts_at==='2028-03-20T14:30','Date and time conversion failed');
        }
        await mount('expenses');await click('[data-finance-create]');await fillRequired();await submit();await fillRequired();await submit();failSave=true;await submit();await until(()=>$('[data-finance-error]'));expect($('[name="title"]').value==='نمونه آزمایشی','Server validation discarded input');failSave=false;
        const p=props('invoices');for(const b of p.workspace.buildings)for(const key of Object.keys(b.abilities))b.abilities[key]=key.endsWith('.view');await mount('invoices',p);expect(!$('[data-finance-create]'),'Read-only create exposed');await click('[data-finance-open]');expect(!$('[data-finance-pay]')&&!$('[data-finance-plan]')&&!$('[data-finance-edit]'),'Read-only mutation exposed');
        await mount('invoices');await click('[data-finance-create]');await input('description','تغییر ذخیره نشده');await click('.finance-editor .ownership-card__heading button');expect($('.finance-editor [role="alert"]'),'Unsaved edit discarded');
        expect(document.documentElement.scrollWidth<=innerWidth,'Page overflows viewport');
        const capture=${JSON.stringify(process.env.FINANCE_CAPTURE||'')};
        if(capture==='list'){await mount('invoices');}
        if(capture==='editor'){await mount('invoices');await click('[data-finance-create]');await input('unit_id','101');await submit();await input('items.0.title','شارژ ماه آبان');await input('items.0.unit_amount','1250000');}
        if(capture==='generic'){await mount('expenses');await click('[data-finance-create]');await fillRequired();}
        window.scrollTo(0,0);$('#result').dataset.ok='true';$('#result').textContent='Passed';
    })().catch(error=>{$('#result').textContent=error.stack;});
    </script></html>`;
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
