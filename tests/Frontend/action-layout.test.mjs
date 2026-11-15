import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const bars = [
    ['crud-form-actions', 'crud-button--primary'],
    ['ownership-editor__actions', 'ownership-button--primary'],
    ['ownership-dialog__actions', 'ownership-button--danger'],
    ['ui-dialog__actions', 'ui-button--danger'],
    ['ui-drawer__footer', 'crud-button--primary'],
    ['user-create-dialog__actions', 'ownership-button--primary'],
    ['ownership-actions structure-form-actions', 'ownership-button--primary'],
    ['finance-actions users-form-actions', 'ownership-button--primary'],
    ['ownership-actions finance-pagination', 'ownership-button--primary'],
    ['modal-footer', 'portal-primary-button'],
    ['registration-wizard-actions', 'registration-submit'],
    ['setup-actions', 'setup-button--primary'],
];

for (const width of [1280, 390]) {
    test(`RTL form actions at ${width}px keep cancel right and submit left`, { skip: !existsSync(chrome) }, () => {
        const directory = mkdtempSync(join(tmpdir(), 'buildino-action-layout-'));
        try {
            const files = ['public/css/buildino-bootstrap.rtl.min.css', 'public/css/buildino-sweetalert2.min.css', 'public/css/buildino-design-system.css', 'public/css/buildino-crud.css',
                'public/css/buildino-unit-ownerships.css', 'resources/css/management-inertia.css',
                'resources/css/user-create-dialog.css', 'public/css/buildino-building-setup.css',
                'public/css/buildino-core-flows.css', 'public/css/buildino-charge-formulas.css',
                'public/css/buildino-action-layout.css'];
            const css = files.map(path => readFileSync(join(project, path), 'utf8')).join('\n');
            const html = `<!doctype html><html dir="rtl" lang="fa"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
            <style>${css} body{margin:12px} .bar{width:100%;box-sizing:border-box;margin:12px 0} button{min-width:70px;min-height:44px}</style>
            ${bars.map(([group, primary]) => `<div class="bar ${group}"><button type="button" data-back>بازگشت</button><button type="submit" class="${primary}" data-next>تأیید</button></div>`).join('')}
            <div class="bar swal2-actions" style="display:flex;flex-direction:row-reverse"><button class="swal2-confirm" data-next>تأیید</button><button class="swal2-cancel" data-back>انصراف</button></div>
            <div class="crud-form-actions" hidden id="hidden"><button type="submit">ثبت</button></div>
            <pre id="result"></pre><script>
            try {
                for (const bar of document.querySelectorAll('.bar')) {
                    const back=bar.querySelector('[data-back]').getBoundingClientRect(), next=bar.querySelector('[data-next]').getBoundingClientRect(), bounds=bar.getBoundingClientRect();
                    if(next.right>back.left+1 || Math.abs(next.top-back.top)>2)throw Error('Wrong order or wrapping: '+bar.className);
                    if(next.left<bounds.left-1 || back.right>bounds.right+1)throw Error('Overflow: '+bar.className);
                }
                if(getComputedStyle(document.getElementById('hidden')).display!=='none')throw Error('Hidden actions became visible');
                document.getElementById('result').dataset.ok='true';
            } catch(error) { document.getElementById('result').textContent=error.message; }
            </script></html>`;
            const path = join(directory, 'index.html'); writeFileSync(path, html);
            const result = execFileSync(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run',
                '--force-device-scale-factor=1', `--window-size=${width},900`, '--dump-dom', pathToFileURL(path).href],
                { encoding: 'utf8', windowsHide: true, timeout: 30000, maxBuffer: 2 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] });
            const outcome = result.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0] || 'No browser result';
            assert.match(outcome, /data-ok="true"/);
        } finally { rmSync(directory, { recursive: true, force: true }); }
    });
}
