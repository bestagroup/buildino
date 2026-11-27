import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFile, execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { join, extname } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

test('actual facility Blade: generation, two-step booking, modal keyboard and responsive themes', { skip: !existsSync(chrome) }, async () => {
    const pages = JSON.parse(execFileSync('php', ['tests/Frontend/fixtures/facility-workspace.php'], { cwd: project, encoding: 'utf8', maxBuffer: 2*1024*1024 }));
    const script = `<script>
    const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)],expect=(ok,message)=>{if(!ok)throw Error(message)},sleep=ms=>new Promise(r=>setTimeout(r,ms));
    const wait=async(fn,message)=>{for(let n=0;n<100;n++){if(fn())return;await sleep(30)}throw Error(message)};
    window.addEventListener('load',async()=>{try{
        await document.fonts.ready;const mode=new URLSearchParams(location.search).get('mode');
        if(mode==='configuration'){
            await wait(()=>$('[data-facility-list] button'),'Directory not loaded from building query');
            $('[data-facility-new]').click(); const base=$('[data-facility-base-form]');
            expect(base.elements.code.value.startsWith('FAC-'),'New facility has no suggested code');
            expect(!$('.facility-manual[open]'),'Advanced form should not compete with the initial task');
            $('[data-facility-close]').click();await wait(()=>$('[data-facility-list] button'),'Directory not restored');
            $('[data-facility-list] button').click();await wait(()=>!$('[data-facility-base-fields]').disabled,'Editor not loaded');
            $('[data-facility-step-button="1"]').click();const form=$('[data-facility-schedule-form]');
            form.querySelector('[name=days]').checked=true;form.elements.start_time.value='08:00';form.elements.end_time.value='10:00';form.elements.slot_duration_minutes.value='60';
            form.dispatchEvent(new Event('input',{bubbles:true}));expect($('[data-facility-batch-preview]').textContent.includes('۰۸:۰۰'),'No Persian time preview');
            form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));await wait(()=>window.__batch,'Batch API was not called');
            expect(window.__batch.slot_duration_minutes===60&&window.__batch.days_of_week.length===1,'Incorrect batch payload');
            await wait(()=>!$('[data-facility-schedule-save]').disabled,'Batch save never settled');
            $('[data-facility-step-button="2"]').click();const approval=$('[data-facility-approval]');
            approval.value='manual';approval.dispatchEvent(new Event('change',{bubbles:true}));
            expect($('[data-facility-rule-form]').elements.requires_approval.checked&&!$('[data-facility-rule-form]').elements.auto_confirm.checked,'Approval selector produced contradictory flags');
            expect(window.jQuery(approval).data('select2'),'Shared Select2 missing');
        }else{
            await wait(()=>$('[data-reservation-history-state]').textContent.includes('رزرو'),'History did not load');
            expect($('[data-reservation-booking]').hidden,'Booking competes with directory');$('[data-reservation-new]').click();
            await wait(()=>$('[data-reservation-facilities] button'),'Facilities not loaded');$('[data-reservation-facilities] button').click();
            await wait(()=>$('.facility-booking-slot input:not(:disabled)'),'Auto date did not load availability');
            expect($$('[data-reservation-step-label]').length===2,'Booking has more than two steps');
            $('.facility-booking-slot input:not(:disabled)').click();$('[data-reservation-next]').click();
            expect(!$('[data-reservation-panel="1"]').hidden,'Review not visible');
            const description=$('[data-reservation-create-form]').elements.description;description.value='یادداشت محفوظ';
            const unit=$('[data-reservation-create-form]').elements.unit_id;unit.selectedIndex=1;unit.dispatchEvent(new Event('change',{bubbles:true}));
            $('[data-reservation-back-slots]').click();expect($('.facility-booking-slot input:checked'),'Back lost selected time');
            $('[data-reservation-next]').click();expect(description.value==='یادداشت محفوظ','Back lost description');
            $('[data-reservation-create]').click();await wait(()=>$('[data-reservation-detail]').open,'Result modal did not open');
            expect($('[data-reservation-detail-state]').textContent.includes('پرداخت'),'Paid reservation falsely reported confirmed');
            expect($('[data-reservation-booking]').hidden,'Completed form remains visible');
            expect($('[data-reservation-detail]').contains(document.activeElement),'Modal did not receive focus');
            $('[data-reservation-detail-close]').click();expect(!$('[data-reservation-detail]').open,'Close did not dismiss dialog');
        }
        expect(!window.__uiErrors.length,'Uncaught browser error: '+window.__uiErrors.join(';'));
        for(const theme of ['light','dark']){document.documentElement.dataset.theme=theme;await sleep(30);expect(document.documentElement.scrollWidth<=innerWidth,'Horizontal overflow at '+innerWidth+'/'+theme);}
        if(${Boolean(process.env.FACILITY_REAL_CAPTURE)}){
            document.documentElement.dataset.theme='light';
            if(mode==='configuration')$('[data-facility-step-button="1"]').click();
            else{$('[data-reservation-new]').click();await wait(()=>$('[data-reservation-facilities] button'),'Capture catalogue missing');$('[data-reservation-facilities] button').click();await wait(()=>$('.facility-booking-slot'),'Capture slots missing');}
            scrollTo(0,0);
        }
        $('#result').dataset.ok='true';
    }catch(error){$('#result').textContent=error.stack}});
    </script>`;
    const server = createServer(async (req,res) => {
        const url=new URL(req.url,'http://localhost'),path=url.pathname;
        if(path==='/device'){res.setHeader('content-type','text/html; charset=utf-8');res.end('<body style="margin:0"><iframe style="border:0;width:'+Number(url.searchParams.get('width'))+'px;height:1000px" src="/page?'+url.searchParams+'"></iframe><pre id="result" hidden></pre><script>setInterval(()=>{const r=document.querySelector("iframe").contentDocument.querySelector("#result");if(r&&(r.dataset.ok||r.textContent))document.querySelector("#result").outerHTML=r.outerHTML},25)</script>');return;}
        if(path==='/page'){
            res.setHeader('content-type','text/html; charset=utf-8');
            const before='<script>window.__uiErrors=[];window.addEventListener("error",e=>window.__uiErrors.push(e.message));const original=window.fetch;window.fetch=async(url,options)=>{if(String(url).includes("schedule-batches")&&options?.body)window.__batch=JSON.parse(options.body);return original(url,options)};window.confirm=()=>true;</script>';
            res.end(pages[url.searchParams.get('mode')].replaceAll(/https?:\/\/(localhost|127\.0\.0\.1)(:8000)?/g,'http://127.0.0.1:'+server.address().port).replace('<head>','<head>'+before).replace('</body>',()=>'<pre id="result" hidden></pre>'+script+'</body>'));return;
        }
        if(path.startsWith('/api/')){
            res.setHeader('content-type','application/json');let body='';for await(const chunk of req)body+=chunk;
            if(path.endsWith('/schedule-batches')){res.statusCode=201;res.end(JSON.stringify({data:pages.facility.schedules}));return;}
            if(path.endsWith('/availability')){res.end(JSON.stringify({data:{facility_is_active:true,date:url.searchParams.get('date'),slots:[{id:1,start_time:'10:00',end_time:'11:00',price:10000,remaining_capacity:3,is_available:true},{id:2,start_time:'11:00',end_time:'12:00',price:10000,remaining_capacity:0,is_available:false}]}}));return;}
            if(/\/facilities\/\d+\/reservations$/.test(path)){
                res.statusCode=201;res.end(JSON.stringify({data:{id:987,user_id:1,status:'payment_pending',final_amount:10000,reservation_date:JSON.parse(body).reservation_date,start_time:'10:00',end_time:'11:00',facility:{title:'سالن اجتماعات'},unit:{unit_number:'۱'},user:{first_name:'مدیر'},cancellations:[]}}));return;
            }
            if(/\/buildings\/\d+\/facilities$/.test(path)){res.end(JSON.stringify({ ...pages.facilities,data:pages.facilities.data.map(row=>({...row,is_active:true})) }));return;}
            if(/\/facilities\/\d+$/.test(path)){res.end(JSON.stringify({data:{...pages.facility,is_active:url.searchParams.has('active')||req.headers.referer?.includes('reservations')}}));return;}
            res.end('{"data":[],"meta":{"current_page":1,"last_page":1}}');return;
        }
        try{res.setHeader('content-type',({'.css':'text/css','.js':'application/javascript','.svg':'image/svg+xml','.woff2':'font/woff2'})[extname(path)]||'application/octet-stream');res.end(readFileSync(join(project,'public',path)));}catch{res.statusCode=404;res.end('Not found');}
    });
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    try{for(const width of (process.env.FACILITY_WIDTHS?.split(',').map(Number)||[320,390,768,1440]))for(const mode of ['configuration','reservations']){
        const capture=process.env.FACILITY_REAL_CAPTURE?['--screenshot='+join(tmpdir(),'buildino-facility-'+mode+'-'+width+'.png')]:[];
        const {stdout}=await promisify(execFile)(chrome,['--headless','--disable-gpu','--no-sandbox','--no-first-run','--force-device-scale-factor=1','--window-size='+Math.max(width,500)+',1000','--virtual-time-budget=6500','--dump-dom',...capture,'http://127.0.0.1:'+server.address().port+(width<500?'/device?width='+width+'&':'/page?')+'mode='+mode+'&building_id='+pages.buildingId],{timeout:30000,maxBuffer:3*1024*1024,windowsHide:true});
        assert.match(stdout.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0]||stdout.slice(-600),/data-ok="true"/,width+'/'+mode);
    }}finally{await new Promise(resolve=>server.close(resolve));}
});
