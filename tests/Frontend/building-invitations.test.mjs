import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { JSDOM } = require(process.env.JSDOM_MODULE || 'jsdom');
const source = readFileSync('public/js/buildino-building-invitations.js', 'utf8');
test('group sends preserve each unit/user pair, report partial failure and refresh history', async () => {
    const dom = new JSDOM(`<meta name="csrf-token" content="csrf"><div data-building-invitations data-send-url="/send"><input id="invitation-hours" type="number" min="1" max="720" value="72" required><input type="checkbox" data-select-page><table><tbody>${[1,2].map(id => `<tr><td><input type="checkbox" data-recipient data-unit="${id}" data-user="7"></td><td data-row-result></td></tr>`).join('')}</tbody></table><button data-send-selected>Send</button><div data-send-result></div><a data-refresh-invitations href="/history"></a><section id="invitation-history"></section></div>`, { url: 'https://buildino.test', runScripts: 'outside-only' });
    const { window } = dom;
    const writes = [];
    window.fetch = async (url, options) => {
        if (!options?.method) return { ok: true, text: async () => '<section id="invitation-history">Updated</section>' };
        const payload = JSON.parse(options.body); writes.push(payload);
        return { ok: payload.unit_id === 1, json: async () => ({ message: payload.unit_id === 1 ? 'OK' : 'ارسال تأیید نشد' }) };
    };
    window.eval(source);
    const select = window.document.querySelector('[data-select-page]'); select.checked = true; select.dispatchEvent(new window.Event('change'));
    const send = window.document.querySelector('[data-send-selected]'); send.click(); send.click();
    for (let i = 0; i < 30 && window.document.querySelector('[aria-busy]'); i++) await new Promise(resolve => setTimeout(resolve, 5));
    assert.deepEqual(writes.map(x => [x.unit_id, x.user_id]), [[1,7],[2,7]]);
    assert.match(window.document.querySelector('[data-send-result]').textContent, /1 ارسال تأیید شد؛ 1 ارسال تأیید نشد/);
    assert.equal(window.document.querySelector('#invitation-history').textContent, 'Updated');
    assert.equal(send.disabled, false);
    assert.equal(window.document.querySelector('[data-recipient]').checked, false);
    dom.window.close();
});
