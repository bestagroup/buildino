import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const context = { window: {} };
vm.runInNewContext(readFileSync(new URL('../../public/js/buildino-money.js', import.meta.url), 'utf8'), context);
const money = context.window.BuildinoMoney;

test('amount groups typed and pasted Latin, Persian and Arabic digits without precision loss', () => {
    assert.equal(money.format('1234567'), '1,234,567');
    assert.equal(money.format('۱۲۳٬۴۵۶٬۷۸۹'), '123,456,789');
    assert.equal(money.format('١٢٣٤'), '1,234');
    assert.equal(money.normalize('9,007,199,254,740,993'), '9007199254740993');
    assert.equal(money.format(''), '');
    assert.equal(money.format('12.5'), '12.5'); // Never silently multiply a fractional amount.
    assert.equal(money.normalize('-1,000'), '-1000');
});

test('editing within a grouped amount preserves the digit-relative cursor', () => {
    let onInput;
    const input = { value: '12345', selectionStart: 0,
        addEventListener: (_event, callback) => { onInput = callback; },
        setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end; },
    };
    money.mount(input);
    assert.equal(input.value, '12,345');
    input.value = '129,345'; input.selectionStart = 3;
    onInput();
    assert.equal(input.value, '129,345');
    assert.equal(input.selectionStart, 3);
    input.value = '۱۲۳۴'; input.selectionStart = 4;
    onInput();
    assert.equal(input.value, '1,234');
    assert.equal(input.selectionStart, 5);
    input.value = ''; input.selectionStart = 0; onInput();
    assert.equal(input.value, ''); assert.equal(input.selectionStart, 0);
});
