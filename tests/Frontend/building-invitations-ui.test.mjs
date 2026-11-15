import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

for (const width of [1280, 390]) test(`building invitations: selection, partial delivery, resend, validation and layout at ${width}px`, { skip: !existsSync(chrome) }, () => {
    const directory = mkdtempSync(join(tmpdir(), 'buildino-invitations-'));
    try {
        const history = `<section class="invitation-panel" id="invitation-history"><header class="invitation-heading"><h2>سوابق ارسال</h2><a data-refresh-invitations href="https://buildino.test/history">به‌روزرسانی</a></header><div class="invitation-table-wrap"><table class="invitation-table"><thead><tr><th>مخاطب</th><th>واحد</th><th>وضعیت</th><th>زمان‌ها</th><th>عملیات</th></tr></thead><tbody><tr><td data-label="مخاطب"><div class="invitation-person"><strong>نام خانوادگی مخاطب دعوت</strong><bdi>۰۹۱۲۳۴۵۶۷۸۹</bdi></div></td><td data-label="واحد">۱</td><td data-label="وضعیت">ارسال تأیید نشده</td><td data-label="زمان‌ها"><time data-invitation-date datetime="2026-11-15T10:00:00+03:30"></time></td><td data-label="عملیات" class="invitation-row-actions"><button data-resend="9" data-unit="1" data-user="7">ارسال مجدد</button><span class="invitation-row-result" data-row-result></span></td></tr></tbody></table></div></section>`;
        const rows = [1, 2, 3].map(id => `<tr><td class="invitation-check"><input type="checkbox" data-recipient data-unit="${id}" data-user="7" ${id === 3 ? 'disabled' : ''}></td><td data-label="مخاطب"><div class="invitation-person"><strong>نام و نام خانوادگی مخاطب</strong><bdi>۰۹۱۲۳۴۵۶۷۸۹</bdi></div></td><td data-label="واحد"><span class="invitation-unit">${id}</span></td><td data-label="وضعیت"><span class="invitation-state" data-row-result>آماده ارسال</span></td></tr>`).join('');
        const fixture = join(directory, 'test.html');
        writeFileSync(fixture, `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="csrf-token" content="csrf"><style>:root{--text:#182434;--muted:#708090;--border:#dde4ed;--surface:#fff;--surface-soft:#f5f8fc;--surface-strong:#eaf0f6}*{box-sizing:border-box}body{margin:16px}button,input{font:inherit}input{max-width:100%}</style><link rel="stylesheet" href="${pathToFileURL(join(process.cwd(), 'public/css/buildino-building-invitations.css')).href}"><div class="invitation-page" data-building-invitations data-send-url="https://buildino.test/send"><section class="invitation-panel"><header class="invitation-heading"><h2>مخاطبان</h2><label class="invitation-select-all"><input type="checkbox" data-select-page>انتخاب همه این صفحه</label></header><div class="invitation-table-wrap"><table class="invitation-table"><thead><tr><th>انتخاب</th><th>مخاطب</th><th>واحد</th><th>وضعیت</th></tr></thead><tbody>${rows}</tbody></table></div><footer class="invitation-sendbar"><div class="invitation-expiry"><label for="invitation-hours">اعتبار دعوت (ساعت)</label><input id="invitation-hours" type="number" min="1" max="720" value="72" required></div><span data-selected-count></span><button data-send-selected>ارسال دعوت‌نامه</button></footer></section><div data-send-result hidden></div>${history}</div><pre id="result"></pre><script>
        const history = ${JSON.stringify(history)}, writes = [];
        let reads = 0;
        window.fetch = async (url, options) => {
            if (!options?.method) { reads++; return {ok:true,text:async()=>history}; }
            const payload = JSON.parse(options.body); writes.push(payload);
            return {ok:payload.unit_id===1 && !payload.invitation_id,json:async()=>({message:'Failure reference: test-reference'})};
        };
        </script><script>${readFileSync('public/js/buildino-building-invitations.js', 'utf8')}</script><script>
        const expect=(ok,message)=>{if(!ok)throw Error(message)};
        const until=async predicate=>{for(let i=0;i<100;i++){if(predicate())return;await new Promise(r=>setTimeout(r,5))}throw Error('Timed out')};
        (async()=>{
            const root=document.querySelector('[data-building-invitations]'), send=root.querySelector('[data-send-selected]'), all=root.querySelector('[data-select-page]'), recipients=[...root.querySelectorAll('[data-recipient]')];
            expect(send.disabled,'No recipient disables send');
            recipients[0].click(); expect(all.indeterminate,'Partial selection');
            all.click(); expect(recipients[1].checked&&!recipients[2].checked,'Select only available recipients');
            expect(root.querySelector('[data-selected-count]').textContent.includes('۲'),'Persian selected count');
            const hours=root.querySelector('#invitation-hours'); hours.value='0'; send.click(); expect(!writes.length,'Invalid expiry cannot send'); hours.value='72';
            send.click(); send.click(); await until(()=>!root.hasAttribute('aria-busy'));
            expect(writes.length===2 && writes[0].unit_id===1 && writes[1].unit_id===2,'No duplicate requests; preserve unit pairs');
            expect(!recipients[0].checked&&recipients[1].checked,'Retain only failed selections');
            expect(!root.querySelector('[data-send-result]').hidden&&root.querySelector('[data-send-result]').textContent.includes('۱ ارسال تأیید نشد'),'Visible partial failure');
            expect(reads===1,'Refresh history');
            expect(root.querySelector('[data-invitation-date]').textContent.includes('۱۴۰۵'),'Persian calendar');
            root.querySelector('[data-resend]').click(); await until(()=>!root.hasAttribute('aria-busy'));
            expect(writes[2].invitation_id===9,'Resend existing invitation');
            expect(root.querySelector('#invitation-history [data-row-result]').textContent.includes('test-reference'),'Failure remains after refresh');
            expect(!root.querySelector('[data-resend]').disabled,'Resend restored');
            expect(!root.querySelector('[data-select-page]').disabled,'Selection restored');
            expect(document.documentElement.scrollWidth<=innerWidth,'No page overflow');
            expect(root.querySelector('[data-select-page]').getBoundingClientRect().width>0,'Select all remains visible on mobile');
            const mainButton=send.getBoundingClientRect(), expiry=hours.getBoundingClientRect();
            if(innerWidth>700) expect(mainButton.right<=expiry.left,'Primary action on left');
            document.querySelector('#result').textContent='PASS';
        })().catch(error=>document.querySelector('#result').textContent=error.stack);
        </script></html>`);
        const output = execFileSync(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--allow-file-access-from-files', `--window-size=${width},1000`, '--virtual-time-budget=6000', '--dump-dom', pathToFileURL(fixture).href], { encoding: 'utf8', windowsHide: true, stdio: ['ignore', 'pipe', 'ignore'] });
        assert.equal(output.match(/<pre id="result">([^]*?)<\/pre>/)?.[1], 'PASS');
    } finally { rmSync(directory, { recursive: true, force: true }); }
});
