import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFile, execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
test('management layout: mobile sidebar, header spacing, access grid and real import upload/preview', { skip: !existsSync(chrome) }, async () => {
    const render = mode => execFileSync('php', ['tests/Frontend/fixtures/user-import-page.php', mode], { cwd: project, encoding: 'utf8' });
    const upload = render('upload'), preview = render('preview');
    const styles = ['buildino-management', 'buildino-crud', 'buildino-unit-ownerships', 'buildino-design-system', 'buildino-user-import', 'buildino-brand', 'buildino-action-layout'];
    const page = mode => `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${styles.map(name => '<link rel="stylesheet" href="/public/css/' + name + '.css">').join('')}<body class="management-body sidebar-collapsed"><div class="management-shell">
    <aside class="sidebar" id="managementSidebar"><div class="sidebar__top"><button type="button" id="sidebarMobileClose" class="sidebar-mobile-close">بستن</button><a class="sidebar__brand" href="#"><span class="buildino-brand-image--horizontal-light">بیلدینو</span><span class="buildino-brand-compact">B</span></a><button id="sidebarCollapse" class="sidebar-collapse-button">جمع</button></div><nav><section class="nav-group is-open"><button class="nav-group__title"><span>صفحات مدیریت</span></button><div class="nav-group__items"><a class="nav-link" href="#dashboard"><span class="nav-link__label">داشبورد</span></a><a class="nav-link" href="#operations"><span class="nav-link__label">مرکز عملیات</span></a></div></section></nav></aside>
    <button id="sidebarBackdrop" class="sidebar-backdrop" aria-label="بستن منو"></button>
    <main class="main-area"><header class="topbar"><div class="topbar__start"><button class="icon-button mobile-menu" id="sidebarToggle">منو</button><div class="page-heading"><h1>ورود کاربران از اکسل</h1></div></div><div></div><div class="topbar__center"></div><div></div></header><div class="page-content">
    ${mode === 'preview' ? preview : upload}
    <section class="crud-group"><div class="crud-resource-grid crud-resource-grid--access">${Array.from({ length: 5 }, (_, index) => '<a class="crud-resource-card" href="#">گزینه ' + index + '</a>').join('')}</div></section>
    </div></main></div><pre id="result" hidden></pre><script>localStorage.setItem('buildino-sidebar-collapsed','1');</script><script src="/public/js/buildino-management.js"></script><script>
    const $=s=>document.querySelector(s),expect=(value,message)=>{if(!value)throw Error(message);};
    (async()=>{
        if(innerWidth<=1040){
            expect(!document.body.classList.contains('sidebar-collapsed'),'Desktop collapse leaked onto mobile');
            expect($('#managementSidebar').inert,'Closed mobile sidebar remains focusable');
            $('#sidebarToggle').click();expect($('#sidebarToggle').getAttribute('aria-expanded')==='true'&&$('.main-area').inert,'Open sidebar state incorrect');
            expect(getComputedStyle($('.nav-link__label')).display!=='none','Mobile navigation labels missing');
            const close=$('#sidebarMobileClose'),links=[...document.querySelectorAll('.sidebar .nav-link')];
            close.focus();document.dispatchEvent(new KeyboardEvent('keydown',{key:'Tab',shiftKey:true,bubbles:true,cancelable:true}));expect(document.activeElement===links.at(-1),'Backward focus trap failed');
            document.dispatchEvent(new KeyboardEvent('keydown',{key:'Tab',bubbles:true,cancelable:true}));expect(document.activeElement===close,'Forward focus trap failed');
            close.click();expect(!$('.main-area').inert&&document.activeElement===$('#sidebarToggle'),'Close did not release page/focus');
            $('#sidebarToggle').click();document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));expect($('#managementSidebar').inert,'Escape failed');
            $('#sidebarToggle').click();$('#sidebarBackdrop').click();expect(!document.body.classList.contains('sidebar-open'),'Backdrop failed');
            const header=$('.topbar').getBoundingClientRect();expect(header.left>=12&&header.right<=innerWidth-12,'Header touches screen edges');
        }else{
            const cards=[...document.querySelectorAll('.crud-resource-grid--access > *')].map(el=>el.getBoundingClientRect());
            expect(cards[0].top===cards[2].top&&cards[3].top===cards[4].top&&cards[3].top>cards[0].top,'Access cards must be three then two');
        }
        expect(document.documentElement.scrollWidth<=innerWidth,'Page overflows');
        if(${mode === 'preview'}){
            const table=$('.import-preview__table');expect(table&&table.textContent.includes('علی'),'Preview lost actual workbook rows');
            expect($('input[name="token"]').value==='preview-token','Commit token lost');
            expect($('.import-preview__actions button[type="submit"]').textContent.includes('۱ ردیف'),'Commit count wrong');
            expect($('.import-preview__scroll').clientWidth<=innerWidth,'Preview scroll region escapes viewport');
        }else{
            const file=$('#users-file');expect(file.required&&file.accept==='.xlsx'&&file.form.enctype==='multipart/form-data','Upload contract changed');
            expect(!file.form.checkValidity(),'Empty upload accepted');
            const files=new DataTransfer();files.items.add(new File(['fixture'],'users.xlsx',{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}));file.files=files.files;
            expect(file.form.checkValidity()&&file.form.action.includes('/preview'),'Valid workbook cannot be submitted');
        }
        await new Promise(resolve=>setTimeout(resolve,250));$('#result').dataset.ok='true';
    })().catch(error=>$('#result').textContent=error.stack);
    </script></body></html>`;
    const server = createServer((req, res) => {
        const address = new URL(req.url, 'http://localhost'), path = address.pathname;
        if (path.startsWith('/public/')) { res.setHeader('Content-Type', path.endsWith('.css') ? 'text/css' : 'application/javascript'); res.end(readFileSync(join(project, path))); return; }
        // Windows Chrome has a minimum outer window width. An iframe gives the
        // page an actual 320/390px viewport instead of a cropped 500px screenshot.
        if (path === '/device') {
            const width = Number(address.searchParams.get('width')), mode = address.searchParams.get('mode') === 'preview' ? 'preview' : 'upload';
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end('<html><body style="margin:0"><iframe src="/' + mode + '" style="display:block;border:0;width:' + width + 'px;height:1000px"></iframe><pre id="result" hidden></pre><script>const timer=setInterval(()=>{const r=document.querySelector("iframe").contentDocument.querySelector("#result");if(r&&(r.dataset.ok||r.textContent)){document.querySelector("#result").outerHTML=r.outerHTML;clearInterval(timer);}},25);</script></body></html>'); return;
        }
        res.setHeader('Content-Type', 'text/html; charset=utf-8'); res.end(page(path === '/preview' ? 'preview' : 'upload'));
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    try {
        for (const width of [1440, 390, 320]) for (const mode of ['upload', 'preview']) {
            const capture = process.env.MANAGEMENT_CAPTURE ? ['--screenshot=' + join(tmpdir(), 'buildino-import-' + mode + '-' + width + '.png')] : [];
            const target = width < 500 ? '/device?width=' + width + '&mode=' + mode : '/' + mode;
            const { stdout } = await promisify(execFile)(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--force-device-scale-factor=1', '--window-size=' + Math.max(width, 500) + ',1000', '--virtual-time-budget=5000', '--dump-dom', ...capture, 'http://127.0.0.1:' + server.address().port + target], { timeout: 30000, maxBuffer: 2 * 1024 * 1024, windowsHide: true });
            assert.match(stdout.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0] || stdout.slice(-1000), /data-ok="true"/, width + '/' + mode);
        }
    } finally { await new Promise(resolve => server.close(resolve)); }
});
