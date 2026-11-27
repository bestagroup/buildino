import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { fileURLToPath } from 'node:url';

const project = fileURLToPath(new URL('../../', import.meta.url));
test('all selects, including calendar controls and legacy opt-outs, use the shared Select2 adapter', { skip: !process.env.JSDOM_MODULE }, async () => {
    const { JSDOM } = await import(process.env.JSDOM_MODULE);
    const bundle = await build({
        absWorkingDir: project, bundle: true, write: false, format: 'iife', loader: { '.css': 'empty' },
        stdin: { resolveDir: project, contents: "import './resources/js/buildino-select2.js'; import './resources/js/buildino-jalali-datepicker.js';" },
    });
    const dom = new JSDOM(`<!doctype html><html dir="rtl"><body><form>
        <label>Year<select name="year" data-select2="off"><option>1405</option><option>1406</option></select></label>
        <select id="legacy" data-native-select><option>A</option></select>
        <input id="date" name="date" type="date" value="2026-09-23">
        <input id="time" name="time" type="time" value="10:00">
        </form></body></html>`, { runScripts: 'outside-only', pretendToBeVisual: true });
    const w = dom.window;
    const tick = () => new Promise(resolve => setTimeout(resolve, 30));
    const instance = select => w.jQuery(select).data('select2');
    try {
        w.eval(bundle.outputFiles[0].text);
        await tick();
        const year = w.document.querySelector('[name=year]');
        assert.ok(instance(year));
        assert.ok(instance(w.document.querySelector('#legacy')));
        year.value = '1406'; w.BuildinoSelect2.refresh(year);
        assert.match(instance(year).$selection.text(), /1406/);
        const activation = new w.MouseEvent('click', { bubbles: true, cancelable: true });
        year.dispatchEvent(activation);
        assert.ok(activation.defaultPrevented);
        assert.ok(instance(year).isOpen());
        instance(year).close();
        const choose = async (select, value) => {
            const widget = instance(select);
            assert.ok(widget);
            assert.equal(widget.dropdown.$dropdownParent[0].tagName, 'JDP-CONTAINER');
            widget.open(); await tick();
            const data = widget.dataAdapter.item(w.jQuery([...select.options].find(option => option.value === value)));
            widget.trigger('select', { data }); await tick();
        };
        w.jalaliDatepicker.show(w.document.querySelector('#date')); await tick();
        let month = w.document.querySelector('jdp-container .jdp-month select');
        assert.ok(month);
        const nextMonth = [...month.options].find(option => option.value !== month.value).value;
        await choose(month, nextMonth);
        assert.equal(w.document.querySelector('jdp-container .jdp-month select').value, nextMonth);
        assert.notEqual(w.document.querySelector('jdp-container').style.display, 'none');
        w.jalaliDatepicker.hide();
        w.jalaliDatepicker.show(w.document.querySelector('#time')); await tick();
        const timeControls = [...w.document.querySelectorAll('jdp-container .jdp-time select')];
        assert.equal(timeControls.length, 2);
        for (const select of timeControls) assert.ok(instance(select));
        await choose(timeControls[1], '14');
        const minutes = w.document.querySelectorAll('jdp-container .jdp-time select')[0];
        await choose(minutes, '35');
        assert.equal(new w.FormData(w.document.querySelector('form')).get('time'), '14:35');
        const dynamic = w.document.createElement('select'); dynamic.innerHTML = '<option>Dynamic</option>';
        w.document.body.append(dynamic); await tick();
        assert.ok(instance(dynamic));
        dynamic.remove(); await tick();
        assert.equal(instance(dynamic), undefined);
    } finally { dom.window.close(); }
});
