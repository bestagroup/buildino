import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFile, execFileSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { join, extname } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

test('financial and provider Blade workspaces: readable responsive records, safe submissions and keyboard dialogs', { skip: !existsSync(chrome) }, async () => {
    const pages = JSON.parse(execFileSync('php', ['tests/Frontend/fixtures/resident-workspace.php','finance'], { cwd:project, encoding:'utf8', maxBuffer:3*1024*1024 }));
    const script = `<script>
    const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)], expect=(ok,message)=>{if(!ok)throw Error(message)}, sleep=ms=>new Promise(r=>setTimeout(r,ms));
    const wait=async(fn,message)=>{for(let n=0;n<100;n++){if(fn())return;await sleep(40)}throw Error(message)};
    const mode=new URLSearchParams(location.search).get('mode'), errors=[];window.addEventListener('error',e=>errors.push(e.message));
    window.addEventListener('load',async()=>{try{
        await document.fonts.ready;await sleep(150);
        if(mode==='manager'){
            $('[data-statement-deposit]').click();expect($('#statement-deposit-dialog').open,'Receipt dialog did not open');
            const dialog=$('#statement-deposit-dialog'),form=$('[data-deposit-form]');
            form.elements.amount.value='123000';form.elements.amount.dispatchEvent(new Event('input',{bubbles:true}));
            expect(form.elements.amount.value.replaceAll(',','')==='123000','Amount separator damaged input');
            const box=dialog.getBoundingClientRect();expect(box.left>=0&&box.right<=innerWidth&&box.top>=0&&box.bottom<=innerHeight,'Receipt dialog exceeds viewport');
            expect(!form.reportValidity(),'Receipt form accepts missing reference/file');
            window.confirm=()=>false;dialog.querySelector('[data-deposit-close]').click();expect(dialog.open,'Dirty receipt was discarded without confirmation');
            window.confirm=()=>true;dialog.querySelector('[data-deposit-close]').click();await wait(()=>!dialog.open,'Receipt dialog did not close');await sleep(80);expect(document.activeElement===$('[data-statement-deposit]'),'Receipt close focus: '+document.activeElement?.outerHTML.slice(0,200));
            if(window.parent!==window){
                const messages=[];window.parent.addEventListener('message',event=>{if(event.source===window&&event.data?.type==='buildino:unit-workspace')messages.push(event.data)});
                $('[data-statement-deposit]').click();form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
                await sleep(40);messages.length=0;
                form.elements.reference.value='AFTER-SUBMIT';form.elements.reference.dispatchEvent(new Event('input',{bubbles:true}));
                await sleep(40);expect(messages.length&&messages.every(message=>message.busy),'Editing a pending receipt clears the parent busy guard');
            }
        }
        if(mode==='statements'){
            if(innerWidth<701)expect($('.portal-topbar').getBoundingClientRect().height<100,'Monthly statement title is squeezed into a vertical column');
            const record=$('.statement-record');record.querySelector('summary').click();expect(record.open,'Statement details cannot expand');
            const form=record.querySelector('[data-statement-settle]');expect(form,'Payable statement has no settlement');
            let posts=0;window.Swal.fire=async()=>({isConfirmed:true});const original=window.fetch;window.fetch=async(url,options)=>{if(String(url).includes('/settle')){posts++;return new Response(JSON.stringify({errors:{settlement:['موجودی کافی نیست']}}),{status:posts===1?422:500,headers:{'content-type':'application/json'}})}return original(url,options)};
            form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
            await wait(()=>!form.querySelector('[data-settle-error]').hidden,'Settlement validation was hidden');expect(posts===1,'Settlement submitted twice');
            form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));await wait(()=>form.querySelector('[data-settle-error] button'),'Unknown settlement has no recovery');
            form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));expect(posts===2&&form.querySelector('[type=submit]').disabled,'Uncertain settlement can be repeated');
        }
        if(mode==='sms'){
            let posts=0;const original=window.fetch;window.fetch=async(url,options)=>{if(!String(url).endsWith('/sms'))return original(url,options);posts++;await sleep(40);return new Response(JSON.stringify({status:posts===1?'accepted':'unknown'}),{status:200,headers:{'content-type':'application/json'}})};
            const inputs=$$('.charge-recipient:not(:disabled)');expect(inputs.length===2,'Fixture lacks two real recipients');
            expect($('#charge-send').disabled,'Sending enabled without selection');inputs[0].click();expect($('#charge-select-all').indeterminate,'Partial selection has no mixed state');
            $('#charge-send').click();expect($('#charge-confirm').open,'Send skipped confirmation');$('[data-sms-cancel]').click();expect(posts===0,'Cancel sent SMS');
            $('#charge-select-all').click();$('#charge-send').click();$('[data-sms-confirm]').click();$('[data-sms-confirm]').click();
            await wait(()=>posts===2&&$('#charge-progress').value===2,'Batch never completed');
            expect(inputs.every(input=>input.disabled),'Attempted recipients allow another claim');expect($('#charge-send').disabled,'Send enabled after all recipients processed');
            expect($$('[data-send-status]').some(node=>node.textContent.includes('نامشخص')),'Unknown result misreported: '+$$('[data-send-status]').map(node=>node.textContent).join(' / '));expect($('#charge-progress').value===2,'Progress does not match completed recipients');
        }
        if(mode.startsWith('provider')){
            expect($$('.portal-nav [aria-current="page"]').length===1,'Provider navigation has ambiguous current page');
            if(mode==='provider'){
                const original=window.fetch;let posts=0;window.fetch=async(url,options)=>{if(String(url).includes('/bank-accounts')&&options?.method==='POST'){posts++;return new Response(JSON.stringify({errors:{iban:['شماره شبا معتبر نیست']}}),{status:posts===1?422:500,headers:{'content-type':'application/json'}})}return original(url,options)};
                $('[data-bs-target="#bankAccountModal"]').click();await wait(()=>$('#bankAccountModal.show'),'Bank modal missing');
                const form=$('[data-portal-form="provider-bank"]');form.elements.account_holder_name.value='کاربر آزمایشی';form.elements.iban.value='IR820540102680020817909002';
                form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
                await wait(()=>form.elements.iban.getAttribute('aria-invalid')==='true','Provider field error is inaccessible');expect(posts===1,'Provider form submitted twice');
                expect(form.elements.account_holder_name.value==='کاربر آزمایشی','Provider validation erased inputs');
                form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));await wait(()=>form.dataset.residentUncertain==='1','Provider unknown result permits another send');
                form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));expect(posts===2,'Unknown bank request repeated');
                const box=$('#bankAccountModal .modal-content').getBoundingClientRect();expect(box.left>=0&&box.right<=innerWidth&&box.height<innerHeight,'Provider form does not fit mobile viewport');
                window.bootstrap.Modal.getOrCreateInstance($('#bankAccountModal')).hide();await wait(()=>!$('#bankAccountModal.show'),'Bank modal did not close');
                $('[data-bs-target="#payoutModal"]').click();await wait(()=>$('#payoutModal.show'),'Payout modal missing');
                const payout=$('[data-portal-form="provider-payout"]');payout.elements.amount.value='12,000';
                let confirmations=0;window.Swal.fire=async()=>{confirmations++;await sleep(60);return{isConfirmed:false}};
                payout.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));payout.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
                await sleep(120);expect(confirmations===1,'Duplicate payout submit opens multiple confirmations');
                window.bootstrap.Modal.getOrCreateInstance($('#payoutModal')).hide();await wait(()=>!$('#payoutModal.show'),'Cancelled payout cannot close');
                $('[data-provider-quote]').click();await wait(()=>$('#providerQuoteModal.show'),'Quote modal missing');
                expect($('[data-provider-quote-currency]').textContent==='تومان','Quote label ignores building currency');
                const quote=$('[data-portal-form="provider-quote"]');quote.elements.amount.value='123,000';let quotePosts=0;
                const schedule=window.setTimeout;window.setTimeout=(fn,delay,...args)=>delay===650?0:schedule(fn,delay,...args);
                window.fetch=async(url,options)=>{if(String(url).includes('/quotes')){quotePosts++;expect(JSON.parse(options.body).amount===123000,'Quote amount was converted or separator damaged payload');return quotePosts===1?new Response(JSON.stringify({errors:{amount:['آزمون مبلغ']}}),{status:422,headers:{'content-type':'application/json'}}):new Response('<html>maintenance</html>',{status:200,headers:{'content-type':'text/html'}})}return original(url,options)};
                quote.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));await wait(()=>quote.querySelector('[aria-invalid=true]'),'Quote validation not inline');
                quote.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));await wait(()=>quote.dataset.residentUncertain==='1','HTML success response was mistaken for saved quote');
            }else if(mode!=='provider-detail'){
                await wait(()=>window.DataTable?.isDataTable($('table.js-server-datatable')),'Provider list did not initialize');
                expect(!$$('[data-dt-filter=status] option').some(option=>option.textContent==='سررسید گذشته'),'Unrelated invoice statuses shown in provider list');
            }
            if(innerWidth<981){$('#portalSidebarToggle').click();expect($('.portal-main').inert&&!$('#portalSidebar').inert,'Provider mobile menu does not trap background');document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));expect(!$('.portal-main').inert&&$('#portalSidebar').inert,'Provider mobile menu cannot close');}
        }
        expect(document.documentElement.scrollWidth<=innerWidth,'Workspace has horizontal overflow');
        for(const theme of ['light','dark']){document.documentElement.dataset.theme=theme;await sleep(20);expect(getComputedStyle($('.portal-content')||document.body).color!=='rgba(0, 0, 0, 0)','Theme text missing');}
        expect(!errors.length,errors.join('; '));$('#result').dataset.ok='true';
    }catch(error){$('#result').textContent=error.stack}});
    </script>`;
    new Function(script.slice(8,-9));
    const server=createServer((req,res)=>{
        const url=new URL(req.url,'http://localhost'),path=url.pathname;
        if(path==='/device'){res.setHeader('content-type','text/html; charset=utf-8');res.end('<body style="margin:0"><iframe style="border:0;width:'+Number(url.searchParams.get('width'))+'px;height:1000px" src="/page?'+url.searchParams+'"></iframe><pre id="result" hidden></pre><script>setInterval(()=>{const r=document.querySelector("iframe").contentDocument.querySelector("#result");if(r&&(r.dataset.ok||r.textContent))document.querySelector("#result").outerHTML=r.outerHTML},30)</script>');return;}
        if(path==='/page'){
            res.setHeader('content-type','text/html; charset=utf-8');res.end(pages[url.searchParams.get('mode')].replaceAll(/https?:\/\/(localhost|127\.0\.0\.1)(:8000)?/g,'http://127.0.0.1:'+server.address().port).replace('</head>','<style>*,*::before,*::after{transition-duration:0s!important;animation-duration:0s!important;scroll-behavior:auto!important}</style></head>').replace('</body>',()=>'<pre id="result" hidden></pre>'+script+'</body>'));return;
        }
        if(path.includes('/datatables/')){res.setHeader('content-type','application/json');res.end(JSON.stringify({...pages.tables[path.split('/').pop()],draw:Number(url.searchParams.get('draw'))||1}).replaceAll('http://localhost','http://127.0.0.1:'+server.address().port));return;}
        if(path.startsWith('/api/')){res.setHeader('content-type','application/json');res.end('{"data":[]}');return;}
        try{res.setHeader('content-type',({'.css':'text/css','.js':'application/javascript','.svg':'image/svg+xml','.woff2':'font/woff2'})[extname(path)]||'application/octet-stream');res.end(readFileSync(join(project,'public',path)));}catch{res.statusCode=404;res.end('Not found');}
    });
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    try{
        for(const width of (process.env.WORKSPACE_WIDTHS?.split(',').map(Number)||[320,390,768,1440]))for(const mode of (process.env.WORKSPACE_MODES?.split(',')||['statements','manager','sms','provider','provider-services','provider-wallet','provider-payouts','provider-detail'])){
            const target=(width<500?'/device?width='+width+'&':'/page?')+'mode='+mode;
            const capture=process.env.WORKSPACE_CAPTURE?['--screenshot='+join(tmpdir(),'buildino-finance-'+mode+'-'+width+'.png')]:[];
            const {stdout}=await promisify(execFile)(chrome,['--headless','--disable-gpu','--no-sandbox','--no-first-run','--force-device-scale-factor=1','--window-size='+Math.max(500,width)+',1000','--virtual-time-budget=7000','--dump-dom',...capture,'http://127.0.0.1:'+server.address().port+target],{timeout:30000,maxBuffer:3*1024*1024,windowsHide:true});
            if(!stdout.includes('data-ok="true"'))writeFileSync(join(tmpdir(),'buildino-finance-failure.html'),stdout);
            assert.match(stdout.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0]||stdout.slice(-600),/data-ok="true"/,width+'/'+mode);
        }
    }finally{await new Promise(resolve=>server.close(resolve));}
});
