import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

test('portal local assets: dialogs stay above backdrops, dropdown, mobile menu and theme work', { skip: !existsSync(chrome) }, () => {
    const directory = mkdtempSync(join(tmpdir(), 'buildino-portal-assets-'));
    const asset = path => pathToFileURL(join(project, 'public', path)).href;
    const fixture = join(directory, 'portal.html');
    writeFileSync(fixture, `<!doctype html><html lang="fa" dir="rtl" data-theme="light"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    ${['buildino-bootstrap.rtl.min.css','buildino-fonts.css','buildino-foundation.css','buildino-portal.css','buildino-design-system.css'].map(file => '<link rel="stylesheet" href="' + asset('css/' + file) + '">').join('')}
    <body class="portal-body"><div class="portal-shell"><aside class="portal-sidebar" id="portalSidebar">منوی پرتال</aside><button class="portal-sidebar-backdrop" id="portalSidebarBackdrop">بستن</button>
    <main class="portal-main"><header class="portal-topbar"><button id="portalSidebarToggle">منو</button><button id="portalThemeToggle">پوسته</button><div class="dropdown"><button id="notifications" data-bs-toggle="dropdown">اعلان‌ها</button><div class="dropdown-menu">اعلان جدیدی ندارید</div></div></header>
    <div class="portal-content"><section class="portal-hero"><h2>پنل ارائه‌دهنده خدمات</h2><button id="open-bank" data-bs-toggle="modal" data-bs-target="#bankAccountModal">حساب بانکی جدید</button></section>
    <div class="modal fade" id="bankAccountModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content portal-modal"><div class="modal-header"><h5>ثبت حساب بانکی</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="بستن"></button></div><div class="modal-body"><label class="portal-field"><span>شماره شبا</span><input name="iban" dir="ltr"></label></div></form></div></div></div></main></div>
    <pre id="result" hidden></pre>
    ${['buildino-bootstrap.bundle.min.js','buildino-sweetalert2.all.min.js','buildino-jdate.js','buildino-portal.js'].map(file => '<script src="' + asset('js/' + file) + '"></script>').join('')}
    <script>
    const expect=(value,message)=>{if(!value)throw Error(message);};
    const event=(element,name)=>new Promise(resolve=>element.addEventListener(name,resolve,{once:true}));
    (async()=>{await document.fonts.ready;
        expect(window.bootstrap && window.Swal && window.JDate,'Local UI runtimes missing');
        const modal=document.getElementById('bankAccountModal');
        expect(modal.parentElement===document.body,'Animated content must not contain modal');
        expect(getComputedStyle(modal).display==='none','Closed modal is visible');
        const shown=event(modal,'shown.bs.modal');document.getElementById('open-bank').click();await shown;
        const body=modal.querySelector('.modal-content').getBoundingClientRect();
        expect(document.elementFromPoint(body.left+body.width/2,body.top+body.height/2)?.closest('.modal')===modal,'Backdrop covers the modal');
        expect(body.left>=0 && body.right<=innerWidth,'Modal exceeds viewport');
        const hidden=event(modal,'hidden.bs.modal');modal.querySelector('.btn-close').click();await hidden;
        expect(!document.querySelector('.modal-backdrop') && !document.body.classList.contains('modal-open'),'Modal cleanup failed');
        document.getElementById('notifications').click();expect(document.querySelector('.dropdown-menu').classList.contains('show'),'Dropdown failed');document.getElementById('notifications').click();
        document.getElementById('portalSidebarToggle').click();expect(document.body.classList.contains('portal-sidebar-open'),'Mobile menu failed');document.getElementById('portalSidebarBackdrop').click();expect(!document.body.classList.contains('portal-sidebar-open'),'Mobile menu cannot close');
        const theme=document.documentElement.dataset.theme;document.getElementById('portalThemeToggle').click();expect(document.documentElement.dataset.theme!==theme,'Theme toggle failed');
        expect(document.documentElement.scrollWidth<=innerWidth,'Portal overflows viewport');document.getElementById('result').dataset.ok='true';
    })().catch(error=>document.getElementById('result').textContent=error.stack);
    </script></body></html>`);
    try {
        for (const width of [1440, 500]) {
            const output = execFileSync(chrome, ['--headless','--disable-gpu','--no-sandbox','--no-first-run','--allow-file-access-from-files','--window-size='+width+',1000','--user-data-dir='+join(directory,'profile-'+width),'--virtual-time-budget=5000','--dump-dom',pathToFileURL(fixture).href], { encoding:'utf8',timeout:30000,maxBuffer:1024*1024,windowsHide:true,stdio:['ignore','pipe','pipe'] });
            assert.match(output.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0] || 'No result', /data-ok="true"/, 'Portal UI failed at '+width);
        }
    } finally {
        if (dirname(resolve(directory)) === resolve(tmpdir()) && directory.includes('buildino-portal-assets-')) rmSync(directory, { recursive:true,force:true,maxRetries:3,retryDelay:100 });
    }
});
