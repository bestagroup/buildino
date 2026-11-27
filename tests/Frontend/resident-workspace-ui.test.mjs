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

test('resident real Blade pages: navigation, slots, inline validation, payment retry and responsive records', { skip: !existsSync(chrome) }, async () => {
    const pages = JSON.parse(execFileSync('php', ['tests/Frontend/fixtures/resident-workspace.php'], { cwd: project, encoding: 'utf8', maxBuffer: 2 * 1024 * 1024 }));
    const script = `<script>
    const $=selector=>document.querySelector(selector), expect=(value,message)=>{if(!value)throw Error(message)}, sleep=ms=>new Promise(resolve=>setTimeout(resolve,ms));
    const wait=async(test,message)=>{for(let i=0;i<80;i++){if(test())return;await sleep(50)}throw Error(message)};
    const errors=[];window.addEventListener('error',event=>errors.push(event.message));
    const contrast=element=>{
        const luminance=color=>color.match(/[\\d.]+/g).slice(0,3).map(Number).map(value=>value/255).map(value=>value<=.04045?value/12.92:Math.pow((value+.055)/1.055,2.4)).reduce((sum,value,index)=>sum+value*[.2126,.7152,.0722][index],0);
        const style=getComputedStyle(element),a=luminance(style.color),b=luminance(style.backgroundColor);
        return (Math.max(a,b)+.05)/(Math.min(a,b)+.05);
    };
    window.addEventListener('load',async()=>{try{
        await document.fonts.ready;
        const mode=new URLSearchParams(location.search).get('mode');
        await wait(()=>window.BuildinoPortalApi,'Portal runtime missing');
        if(['units','wallet','services','loyalty'].includes(mode)){
            expect($('[data-resident-workspace]').dataset.residentResource===mode,'Workspace resource was lost');
            expect(document.querySelectorAll('.portal-nav [aria-current="page"]').length===1,'Sidebar current page is ambiguous');
            if(mode==='units'){
                expect($('.portal-unit-card'),'Unit cards missing');
                $('[data-open-topup]').click();await wait(()=>$('#topUpModal.show'),'Topup modal missing');
                expect($('#topUpModal [name="unit_id"]').value===$('.portal-unit-card').dataset.unitId,'Topup targets a different unit');
                expect(!/[0-9]/.test($('#topUpModal [data-topup-title]').textContent),'Dynamically updated unit title uses Latin digits');
                if(innerWidth<701){
                    const box=$('#topUpModal .modal-content').getBoundingClientRect();
                    expect(box.height<500&&box.top>=8&&box.bottom<=innerHeight-8,'Short wallet dialog stretches beyond the mobile viewport');
                }
            }
            if(mode==='services'){
                $('[data-resident-create]').click();await wait(()=>$('#serviceModal.show'),'Service form missing');
                const form=$('[data-portal-form="service"]');expect(['unit_id','type','priority','title','description'].every(name=>form.elements.namedItem(name)),'Service payload fields missing');
                expect(window.jQuery(form.elements.unit_id).hasClass('select2-hidden-accessible'),'Service unit selector was not enhanced');
            }
            if(mode==='wallet')await wait(()=>$('.resident-mobile-records'),'Wallet table did not initialize');
            if(mode==='loyalty')expect($('[data-loyalty-balance]'),'Loyalty balance missing');
        }
        if(mode==='home'){
            const primary=getComputedStyle($('.resident-account .portal-primary-button'));
            expect(primary.backgroundColor!=='rgba(0, 0, 0, 0)'&&primary.backgroundColor!==primary.color,'Primary action has no visible contrast');
            expect(contrast($('.resident-account .portal-primary-button'))>=4.5,'Primary action text contrast is insufficient in light mode');
            expect(!$('body.resident-glass')&&!$('#guestModal'),'Home still renders all process forms');
            expect($('.resident-shortcuts').children.length===6,'Missing work shortcuts');
            if(innerWidth<981){
                expect($('#portalSidebar').inert,'Closed mobile sidebar can receive focus');
                $('#portalSidebarToggle').click();await sleep(20);
                expect(!$('#portalSidebar').inert&&$('.portal-main').inert,'Sidebar does not trap mobile background');
                expect($('#portalSidebarToggle').getAttribute('aria-expanded')==='true','Missing expanded state');
                document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));await sleep(20);
                expect($('#portalSidebar').inert&&document.activeElement===$('#portalSidebarToggle'),'Escape did not restore focus');
                const header=$('.portal-topbar').getBoundingClientRect();expect(header.left>=11&&header.right<=innerWidth-11,'Header touches screen edges');
            }
            $('#portalThemeToggle').click();expect(document.documentElement.dataset.theme==='dark','Dark mode failed');
            await sleep(400);expect(contrast($('.resident-account .portal-primary-button'))>=4.5,'Primary action text contrast is insufficient in dark mode');
        }
        if(mode==='invoices'){
            const record=$('.statement-record');expect(record,'Monthly statement records missing');
            expect(!/[0-9]/.test(record.textContent),'Statement digits are not Persian');
            record.querySelector('summary').click();expect(record.open,'Statement details cannot open');
            expect(record.querySelector('table caption'),'Line items have no accessible context');
            expect($('.portal-nav [aria-current="page"]').href.includes('/operations/invoices'),'Statement navigation has no current page');
        }
        if(mode==='reservations'||mode==='reservation-manual'){
            $('[data-resident-create]').click();await wait(()=>$('.modal.show'),'Reservation modal missing');
            const form=$('[data-portal-form="reservation"]'),date=form.elements.reservation_date;
            expect(form.querySelector('[type="submit"]').disabled,'Reservation submitted without a slot');
            date.value='2026-12-01';date.dispatchEvent(new Event('change',{bubbles:true}));
            await wait(()=>$('.resident-slot-list button'),'Availability failure has no retry');$('.resident-slot-list button').click();
            await wait(()=>$('.resident-slot input'),'Available slots missing');
            expect(document.querySelectorAll('.resident-slot').length===1,'Unavailable slot was offered');
            expect($('.resident-slot-status:last-child').textContent.includes('۶۰'),'Actual policy not shown');
            $('.resident-slot input').click();expect(!form.querySelector('[type="submit"]').disabled,'Slot selection did not enable submission');
            expect($('.resident-slot small').textContent.includes('۱۰٬۰۰۰'),'Default slot price was lost');
            expect(window.jQuery(form.elements.facility_id).hasClass('select2-hidden-accessible'),'Existing Select2 was not reused');
            form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
            await wait(()=>window.__reservationPayload,'Reservation was not submitted');
            if(mode!=='reservation-manual')expect(window.__reservationPayload.facility_time_slot_id===1&&!('start_time' in window.__reservationPayload),'Slot ID missing or invalid manual times sent to the real API contract');
            date.value='2026-12-02';date.dispatchEvent(new Event('change',{bubbles:true}));
            await wait(()=>!form.elements.start_time.disabled&&form.elements.start_time.closest('.portal-field').hidden===false,'Supported explicit-time reservation is unavailable');
            form.elements.start_time.value='18:00';form.elements.end_time.value='19:00';form.elements.end_time.dispatchEvent(new Event('input',{bubbles:true}));
            window.__reservationPayload=null;form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
            await wait(()=>window.__reservationPayload,'Manual reservation not submitted');
            expect(window.__reservationPayload.start_time==='18:00'&&!window.__reservationPayload.facility_time_slot_id,'Manual reservation payload invalid');
        }
        if(mode==='guests'||mode==='guests-json'){
            $('[data-resident-create]').click();await wait(()=>$('.modal.show'),'Guest modal missing');
            const form=$('[data-portal-form="guest"]');form.elements.first_name.value='علی';form.elements.last_name.value='رضایی';
            form.elements.mobile.value='09121112233';form.elements.expected_entry_at.value='2026-12-01T18:00';
            // Dispatch the native submit event so the test is independent of the datepicker's presentation input.
            form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
            if(mode==='guests-json'){
                await wait(()=>form.dataset.residentUncertain==='1','Truncated successful response permits unsafe retry');
                form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));expect(window.__guestPosts===1,'Truncated response caused duplicate submission');
                expect(form.querySelector('[role="alert"] a'),'Truncated response has no visible recovery');
                $('#result').dataset.ok='true';return;
            }
            await wait(()=>form.querySelector('[role="alert"]'),'Validation error was not inline');
            expect(form.elements.mobile.getAttribute('aria-invalid')==='true','Invalid field was not identified');
            expect(window.__guestPosts===1,'Duplicate submit reached the API');
            expect(form.elements.first_name.value==='علی','Validation erased entered data');
            const field=form.elements.first_name,before=getComputedStyle(field).backgroundColor;field.focus();
            expect(getComputedStyle(field).backgroundColor===before,'Focus changes input background');
            form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
            await wait(()=>form.dataset.residentUncertain==='1','Unknown result allows unsafe retry');
            form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));expect(window.__guestPosts===2,'Unknown result was submitted again');
        }
        if(mode==='support'){
            $('[data-ticket-conversation]').click();await wait(()=>$('.modal.show'),'Ticket conversation unavailable');
            const form=$('[data-ticket-reply-form]');form.elements.message.value='پاسخ آزمایشی';
            form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
            await wait(()=>form.querySelector('[role="alert"]'),'Ticket reply validation has no visible message');
            form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
            await wait(()=>form.dataset.residentUncertain==='1','Ticket reply unsafe retry');
            expect(form.querySelector('[role="alert"] a'),'Ticket reply uncertainty has no recovery link');
        }
        if(mode==='invoice'){
            let settlements=0;
            const nativeFetch=window.fetch;window.fetch=async(url,options)=>{
                if(String(url).includes('/statements/')&&options?.method==='POST'){
                    settlements++;
                    return new Response(JSON.stringify({errors:{settlement:['موجودی کیف پول کافی نیست']}}),{status:422,headers:{'content-type':'application/json'}});
                }return nativeFetch(url,options);
            };
            window.confirm=()=>true;window.Swal.fire=async()=>({isConfirmed:true});
            const pay=$('[data-pay-invoice]');pay.click();pay.click();await wait(()=>settlements===1&&!pay.disabled,'Wallet settlement validation did not finish');
            expect(settlements===1,'Duplicate click submitted two monthly settlements');
        }
        expect(document.documentElement.scrollWidth<=innerWidth,'Page overflows viewport');
        if($('.modal.show')){const box=$('.modal.show .modal-content').getBoundingClientRect();expect(box.left>=0&&box.right<=innerWidth,'Modal exceeds viewport');}
        expect(!errors.length,errors.join('; '));$('#result').dataset.ok='true';
    }catch(error){$('#result').textContent=error.stack}});
    </script>`;
    let availability = 0;
    const server = createServer(async (req, res) => {
        const url = new URL(req.url, 'http://localhost'), path = url.pathname;
        if (path === '/device') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end('<html><body style="margin:0"><iframe style="display:block;border:0;width:' + Number(url.searchParams.get('width')) + 'px;height:1000px" src="/page?' + url.searchParams + '"></iframe><pre id="result" hidden></pre><script>const timer=setInterval(()=>{const r=document.querySelector("iframe").contentDocument.querySelector("#result");if(r&&(r.dataset.ok||r.textContent)){document.querySelector("#result").outerHTML=r.outerHTML;clearInterval(timer)}},25)</script></body></html>'); return;
        }
        if (path === '/page') {
            availability = 0;
            const mode = url.searchParams.get('mode');
            const before = `<script>window.__guestPosts=0;window.__replyPosts=0;const originalFetch=window.fetch;window.fetch=async(url,options)=>{
                if(String(url).includes('/reservations')&&options?.method==='POST'){window.__reservationPayload=JSON.parse(options.body);return new Response(JSON.stringify({errors:{description:['آزمون قرارداد درخواست']}}),{status:422,headers:{'content-type':'application/json'}})}
                if(String(url).includes('/messages')&&options?.method==='POST'){window.__replyPosts++;return new Response(JSON.stringify({errors:{message:['پیام معتبر نیست']}}),{status:window.__replyPosts===1?422:500,headers:{'content-type':'application/json'}})}
                if(String(url).includes('/guest-visits')&&options?.method==='POST'){window.__guestPosts++;if(location.search.includes('guests-json'))return new Response('{',{status:200,headers:{'content-type':'application/json'}});return new Response(JSON.stringify(window.__guestPosts===1?{errors:{'guest.mobile':['شماره معتبر نیست']}}:{}),{status:window.__guestPosts===1?422:500,headers:{'content-type':'application/json'}})}return originalFetch(url,options)};</script>`;
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(pages[mode === 'guests-json' ? 'guests' : mode === 'reservation-manual' ? 'reservations' : mode].replaceAll(/https?:\/\/(localhost|127\.0\.0\.1)(:8000)?/g, 'http://127.0.0.1:' + server.address().port).replace('<head>', '<head>' + before).replace('</head>', '<style>*,*::before,*::after{transition-duration:0s!important;animation-duration:0s!important;scroll-behavior:auto!important}</style></head>').replace('</body>', '<pre id="result" hidden></pre>' + script + '</body>')); return;
        }
        res.setHeader('Content-Type', 'application/json');
        if (path.includes('/datatables/')) { res.end(JSON.stringify({ ...(pages.tables[path.split('/').pop()] || pages.rows), draw: Number(url.searchParams.get('draw')) || 1 }).replaceAll('http://localhost', 'http://127.0.0.1:' + server.address().port)); return; }
        if (path.includes('/availability')) {
            if(url.searchParams.get('date')==='2026-12-02'){res.end(JSON.stringify({data:{facility_is_active:true,slots:[],schedules:[],blackouts:[]}}));return;}
            availability++;
            if (availability === 1) { res.statusCode = 503; res.end('{}'); return; }
            res.end(JSON.stringify({ data: { facility_is_active: true, slots: [{ id: 1, start_time: '18:00:00', end_time: '19:00:00', price: null, remaining_capacity: 1, is_available: true }, { id: 2, is_available: false }] } })); return;
        }
        if (path.startsWith('/api/')) { res.end(JSON.stringify({ data: [] })); return; }
        try {
            const file = join(project, 'public', path); res.setHeader('Content-Type', ({ '.css': 'text/css', '.js': 'application/javascript', '.svg': 'image/svg+xml', '.woff2': 'font/woff2' })[extname(path)] || 'application/octet-stream');
            res.end(readFileSync(file));
        } catch { res.statusCode = 404; res.end('Not found'); }
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    try {
        for (const width of (process.env.RESIDENT_WIDTHS?.split(',').map(Number) || (process.env.RESIDENT_MODES ? [1440] : [1440, 390, 320]))) for (const mode of (process.env.RESIDENT_MODES?.split(',') || ['home', 'invoices', 'guests', 'reservations', 'invoice', 'support', 'guests-json', 'units', 'wallet', 'services', 'loyalty'])) {
            const target = (width < 500 ? '/device?width=' + width + '&' : '/page?') + 'mode=' + mode + (mode === 'invoices' ? '&status=issued' : '');
            const capture = process.env.RESIDENT_CAPTURE ? ['--screenshot=' + join(tmpdir(), 'buildino-resident-new-' + mode + '-' + width + '.png')] : [];
            const { stdout } = await promisify(execFile)(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--force-device-scale-factor=1', '--window-size=' + Math.max(width, 500) + ',1000', '--virtual-time-budget=7000', '--dump-dom', ...capture, 'http://127.0.0.1:' + server.address().port + target], { timeout: 30000, maxBuffer: 2 * 1024 * 1024, windowsHide: true });
            assert.match(stdout.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0] || stdout.slice(-600), /data-ok="true"/, width + '/' + mode);
        }
    } finally { await new Promise(resolve => server.close(resolve)); }
});
