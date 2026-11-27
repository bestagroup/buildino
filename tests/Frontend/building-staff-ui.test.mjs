import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFile, execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { join, extname } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const project = fileURLToPath(new URL('../../',import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

test('staff actual Blade: filters, services, explicit permissions, validation and native form payload', {skip:!existsSync(chrome)}, async()=>{
    const pages=JSON.parse(execFileSync('php',['tests/Frontend/fixtures/building-staff.php'],{cwd:project,encoding:'utf8',maxBuffer:2*1024*1024}));
    const script=`<script>
    const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)],expect=(ok,message)=>{if(!ok)throw Error(message)},sleep=ms=>new Promise(r=>setTimeout(r,ms));
    const wait=async(fn,message)=>{for(let n=0;n<80;n++){if(fn())return;await sleep(40)}throw Error(message)};
    window.addEventListener('load',async()=>{try{
        await document.fonts.ready;const mode=new URLSearchParams(location.search).get('mode');
        if(mode==='list'){
            const visible=()=>$$('[data-staff-record]').filter(row=>!row.hidden);
            expect(visible().length===2,'Real staff records missing');expect(!$('[data-staff-form]'),'Creation form competes with directory');
            const search=$('[data-staff-search]');search.value='۰۹۱۲۹۹۹۰۰۰۱';search.dispatchEvent(new Event('input',{bubbles:true}));expect(visible().length===1,'Persian phone search failed');
            search.value='';search.dispatchEvent(new Event('input',{bubbles:true}));const status=$('[data-staff-filter-status]');status.value='inactive';status.dispatchEvent(new Event('change',{bubbles:true}));expect(visible().length===1&&visible()[0].dataset.type==='guard','Status filter failed');
            const type=$('[data-staff-filter-type]');type.value='concierge';type.dispatchEvent(new Event('change',{bubbles:true}));expect(!visible().length&&!$('[data-staff-filter-empty]').hidden,'Combined filters have no empty feedback');
            type.value='';type.dispatchEvent(new Event('change',{bubbles:true}));const row=visible()[0];row.querySelector('summary').click();expect(row.querySelector('details').open,'Permission disclosure unavailable');
            expect(new URL(row.querySelector('a').href).searchParams.has('building_id'),'Edit link lost building scope');
            await wait(()=>window.jQuery?.(type).data('select2'),'Shared Select2 not initialized');
        }
        if(['create','edit','error'].includes(mode)){
            const form=$('[data-staff-form]'),type=$('#staff-type');
            expect(!$('.staff-record'),'Staff list competes with editor');
            const active=()=>form.querySelector('[data-staff-services]:not([hidden])');
            expect($$('[data-staff-services][hidden] input').every(input=>input.disabled),'Hidden services are submitted');
            if(mode==='error'){
                expect($('[data-staff-error-summary]'),'Server validation is hidden');expect(form.querySelectorAll('[name="permissions[]"]:checked').length===0,'Validation silently restored revoked permissions');expect(!active().querySelector('input:checked'),'Validation silently restored removed services');expect(!form.querySelector('[name=is_active][type=checkbox]').checked,'Validation restored active access');
            }else{
                type.value='guard';type.dispatchEvent(new Event('change',{bubbles:true}));expect(active().dataset.staffServices==='guard','Employee type did not switch services');
                expect(form.querySelectorAll('[name="permissions[]"]:checked').length===(mode==='edit'?1:0),'Employee type automatically granted permissions');
                expect(!form.reportValidity(),'Form accepts no service selection');expect(!$('[data-staff-services-error]').hidden,'Missing service has no visible error');
                active().querySelector('[value=security]').click();
                if(mode==='create'){form.elements.first_name.value='علی';form.elements.last_name.value='رضایی';form.elements.mobile.value='۰۹۱۲۹۹۹۰۰۰۳';form.elements.mobile.dispatchEvent(new Event('input',{bubbles:true}));expect(form.elements.mobile.value==='09129990003','Persian mobile digits were not normalized');}
                const permission=form.querySelector('[name="permissions[]"]');permission.checked=false;permission.dispatchEvent(new Event('change',{bubbles:true}));permission.click();expect($('[data-staff-permission-count]').textContent.includes('۱'),'Permission count did not update');
                const enabled=form.querySelector('[name=is_active][type=checkbox]');enabled.checked=false;
                expect(form.reportValidity(),'Valid staff form rejected');
                const values=new FormData(form);expect(values.getAll('services[]').join(',')==='security','Payload includes services from hidden employee types');expect(values.getAll('is_active').join(',')==='0','Inactive status was omitted');expect(values.getAll('permissions[]').length===1,'Explicit permissions were not serialized');
                const field=form.querySelector('input:not([type=hidden]):not([type=checkbox])');if(field){const before=getComputedStyle(field).backgroundColor;field.focus();expect(getComputedStyle(field).backgroundColor===before,'Input background changes on focus');}
                let submits=0;form.addEventListener('submit',event=>{if(!event.defaultPrevented)submits++;event.preventDefault()});form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));expect(submits===1&&$('[data-staff-save]').disabled,'Duplicate save was not blocked');
            }
        }
        if(mode==='empty')expect($('.staff-empty a'),'Empty directory has no create action');
        expect(document.documentElement.scrollWidth<=innerWidth,'Staff page overflows viewport');
        for(const theme of ['light','dark']){
            document.documentElement.dataset.theme=theme;await sleep(20);expect(document.documentElement.scrollWidth<=innerWidth,'Theme causes horizontal overflow');
            const button=$('.ownership-button--primary:not(:disabled)');
            if(button){const luminance=color=>color.match(/[\\d.]+/g).slice(0,3).map(Number).map(n=>n/255).map(n=>n<=.04045?n/12.92:Math.pow((n+.055)/1.055,2.4)).reduce((sum,n,index)=>sum+n*[.2126,.7152,.0722][index],0);const style=getComputedStyle(button),a=luminance(style.color),b=luminance(style.backgroundColor);expect((Math.max(a,b)+.05)/(Math.min(a,b)+.05)>=4.5,'Primary button contrast is insufficient');}
        }
        $('#result').dataset.ok='true';
    }catch(error){$('#result').textContent=error.stack}});
    </script>`;
    const server=createServer((req,res)=>{
        const url=new URL(req.url,'http://localhost'),path=url.pathname;
        if(path==='/device'){res.setHeader('content-type','text/html; charset=utf-8');res.end('<body style="margin:0"><iframe style="border:0;width:'+Number(url.searchParams.get('width'))+'px;height:1000px" src="/page?'+url.searchParams+'"></iframe><pre id="result" hidden></pre><script>setInterval(()=>{const r=document.querySelector("iframe").contentDocument.querySelector("#result");if(r&&(r.dataset.ok||r.textContent))document.querySelector("#result").outerHTML=r.outerHTML},25)</script>');return;}
        if(path==='/page'){res.setHeader('content-type','text/html; charset=utf-8');res.end(pages[url.searchParams.get('mode')].replaceAll(/https?:\/\/(localhost|127\.0\.0\.1)(:8000)?/g,'http://127.0.0.1:'+server.address().port).replace('</head>','<style>*,*::before,*::after{transition-duration:0s!important;animation-duration:0s!important}</style></head>').replace('</body>',()=>'<pre id="result" hidden></pre>'+script+'</body>'));return;}
        if(path.startsWith('/api/')){res.setHeader('content-type','application/json');res.end('{"data":[]}');return;}
        try{res.setHeader('content-type',({'.css':'text/css','.js':'application/javascript','.svg':'image/svg+xml','.woff2':'font/woff2'})[extname(path)]||'application/octet-stream');res.end(readFileSync(join(project,'public',path)));}catch{res.statusCode=404;res.end('Not found');}
    });
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    try{for(const width of (process.env.STAFF_WIDTHS?.split(',').map(Number)||[320,390,768,1440]))for(const mode of (process.env.STAFF_MODES?.split(',')||['list','create','edit','error','empty'])){
        const capture=process.env.STAFF_CAPTURE?['--screenshot='+join(tmpdir(),'buildino-staff-'+mode+'-'+width+'.png')]:[];
        const {stdout}=await promisify(execFile)(chrome,['--headless','--disable-gpu','--no-sandbox','--no-first-run','--force-device-scale-factor=1','--window-size='+Math.max(width,500)+',1000','--virtual-time-budget=6000','--dump-dom',...capture,'http://127.0.0.1:'+server.address().port+(width<500?'/device?width='+width+'&':'/page?')+'mode='+mode],{timeout:30000,maxBuffer:2*1024*1024,windowsHide:true});
        assert.match(stdout.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0]||stdout.slice(-600),/data-ok="true"/,width+'/'+mode);
    }}finally{await new Promise(resolve=>server.close(resolve));}
});
