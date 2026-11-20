import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

for (const [skin, theme] of [['management', 'light'], ['management', 'dark'], ['portal', 'light'], ['portal', 'dark']]) test(`field focus changes only its border and preserves errors in ${skin} ${theme} mode`, { skip: !existsSync(chrome) }, () => {
    const directory = mkdtempSync(join(tmpdir(), 'buildino-field-focus-'));
    try {
        const asset = name => pathToFileURL(join(process.cwd(), name)).href;
        // Simulate the browser's autofill pseudo-state while retaining the real selectors and cascade.
        const autofillCss = file => readFileSync(file, 'utf8').replace(/:-webkit-autofill|:autofill/g, '[data-test-autofilled]');
        const html = `<!doctype html><html dir="rtl" data-theme="${theme}"><head>
        ${[`public/css/buildino-${skin}.css`, 'resources/css/buildino-select2.css', 'public/css/buildino-building-setup.css', 'public/css/buildino-design-system.css'].map(file => `<link rel="stylesheet" href="${asset(file)}">`).join('')}
        <style>${autofillCss('public/css/buildino-field-focus.css')}</style><style>${autofillCss('public/css/buildino-login.css')}</style>
        <style>input[data-test-autofilled] {background-color:rgb(232,240,254)!important} .has-error .select2-selection {border:1px solid #bd3340!important} *,*::before,*::after{transition:none!important}</style></head><body class="${skin}-body">
        <div class="ui-field"><input id="text"><textarea id="area"></textarea><select id="native"><option>A</option></select><input id="invalid" aria-invalid="true"><input id="disabled" disabled></div>
        <div class="login-card"><label class="auth-input" id="wrap"><input id="inner"></label><label class="auth-input core-auth-password" id="autofill-wrap"><input id="autofill" data-test-autofilled></label></div>
        <div class="setup-counter" id="counter"><input id="counter-input"></div>
        <div class="has-error"><span class="select2-container--default"><span id="invalid-select" aria-invalid="true" tabindex="0" class="select2-selection select2-selection--single"></span></span></div>
        <span class="select2-container--default"><span id="select2" tabindex="0" class="select2-selection select2-selection--single"></span></span>
        <button id="button">button</button><pre id="result"></pre><script>
        const $=id=>document.getElementById(id), focus=id=>{$(id).focus();return getComputedStyle($(id));}, expect=(v,m)=>{if(!v)throw Error(m)};
        try {
            for(const id of ['text','area','native']){const before=getComputedStyle($(id)),bg=before.backgroundColor,border=before.borderTopColor,s=focus(id);expect(s.outlineStyle==='none'&&s.boxShadow==='none','Extra focus border on '+id);expect(s.backgroundColor===bg,'Focus changed background on '+id);expect(s.borderTopColor!==border,'Focus border did not change on '+id);}
            const errorColor=getComputedStyle($('invalid')).borderTopColor;const invalid=focus('invalid');expect(invalid.borderTopColor===errorColor,'Invalid field lost its error border');
            const wrapperBefore=getComputedStyle($('wrap')),wrapperBg=wrapperBefore.backgroundColor,wrapperBorder=wrapperBefore.borderTopColor;focus('inner');expect(getComputedStyle($('inner')).outlineStyle==='none'&&getComputedStyle($('wrap')).outlineStyle==='none','Wrapped field gained an outline');expect(getComputedStyle($('wrap')).backgroundColor===wrapperBg,'Wrapped focus changed background');expect(getComputedStyle($('wrap')).borderTopColor!==wrapperBorder,'Wrapped focus border did not change');
            const autofillBefore=getComputedStyle($('autofill')).boxShadow,autofillWrapBefore=getComputedStyle($('autofill-wrap')).backgroundColor;expect(autofillBefore!=='none','Autofill did not match the login field');focus('autofill');expect(getComputedStyle($('autofill')).boxShadow===autofillBefore,'Focus removed the autofill surface');expect(getComputedStyle($('autofill-wrap')).backgroundColor===autofillWrapBefore,'Autofilled wrapper background changed');
            const counterBg=getComputedStyle($('counter')).backgroundColor;focus('counter-input');expect(getComputedStyle($('counter-input')).outlineStyle==='none'&&getComputedStyle($('counter')).outlineStyle==='none','Counter gained an outline');expect(getComputedStyle($('counter')).backgroundColor===counterBg,'Counter focus changed background');
            $('invalid-select').parentElement.classList.add('select2-container--focus');const badSelect=focus('invalid-select');expect(badSelect.borderTopColor==='rgb(189, 51, 64)','Select2 focus hid validation error');expect(badSelect.outlineStyle==='none','Invalid Select2 gained an outline');
            const selectBefore=getComputedStyle($('select2')),selectBg=selectBefore.backgroundColor,selectBorder=selectBefore.borderTopColor;$('select2').parentElement.classList.add('select2-container--focus');const select=focus('select2');expect(select.outlineStyle==='none'&&select.boxShadow==='none','Select2 gained an outline or shadow');expect(select.backgroundColor===selectBg,'Select2 focus changed background');expect(select.borderTopColor!==selectBorder,'Select2 focus border did not change');
            $('disabled').focus();expect(document.activeElement.id!=='disabled','Disabled field gained focus');
            const button=focus('button');expect(button.outlineStyle!=='none','Button focus lost');
            $('result').textContent='PASS';
        } catch(e) { $('result').textContent=e.stack; }
        </script></body></html>`;
        const fixture = join(directory, 'index.html'); writeFileSync(fixture, html);
        const output = execFileSync(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--allow-file-access-from-files', '--dump-dom', pathToFileURL(fixture).href], { encoding: 'utf8', windowsHide: true, stdio: ['ignore', 'pipe', 'ignore'] });
        assert.equal(output.match(/<pre id="result">([^]*?)<\/pre>/)?.[1], 'PASS');
    } finally { rmSync(directory, { recursive: true, force: true }); }
});
