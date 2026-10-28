import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

test('portal invitation: actual markup, localized codes, busy states, recovery and legacy pending links', { skip: !existsSync(chrome) }, () => {
    const directory = mkdtempSync(join(tmpdir(), 'buildino-portal-invitation-'));
    const view = readFileSync(join(project, 'resources/views/portal/layouts/app.blade.php'), 'utf8');
    const markup = view.match(/<section class="core-flow" data-pending-invitation[\s\S]*?<\/section>/)[0].replace(/\{\{[\s\S]*?\}\}/g, '/portal/resident');
    const source = readFileSync(join(project, 'public/js/buildino-core-invitation.js'), 'utf8');
    const fixture = join(directory, 'invitation.html');
    writeFileSync(fixture, `<!doctype html><html><meta charset="utf-8"><pre id="result"></pre><script>
    const markup=${JSON.stringify(markup)}, source=${JSON.stringify(source)};
    const expect=(value,message)=>{if(!value)throw Error(message);};
    const tick=()=>new Promise(r=>setTimeout(r,10));
    const setup=async({area='resident',pending=null,legacy=false,digits=6}={})=>{
        const frame=document.createElement('iframe');document.body.append(frame);const w=frame.contentWindow;
        w.document.open();w.document.write(markup);w.document.close();
        if(legacy)w.document.querySelector('[data-invitation-entry]').remove();
        const stored=new Map(pending?[['buildino.pending-invitation',JSON.stringify({token:pending})]]:[]);
        Object.defineProperty(w,'sessionStorage',{value:{getItem:key=>stored.get(key)??null,setItem:(key,value)=>stored.set(key,value),removeItem:key=>stored.delete(key)}});
        w.BuildinoPortal={area,invitationCodeDigits:digits};const calls=[];let release,reject;
        w.BuildinoCoreFlows={request:(path,options)=>{calls.push({path,body:JSON.parse(options.body)});return new Promise((resolve,fail)=>{release=resolve;reject=fail;});}};
        w.eval(source);await tick();
        const $=selector=>w.document.querySelector(selector);
        const reply=async()=>{release({data:{status:'sent',unit_id:4,relation_type:'tenant',unit:{unit_number:'۱۰۱'}}});await tick();};
        return {w,$,calls,stored,reply,fail:async status=>{reject(Object.assign(new Error('مشکل ارتباط'),{status}));await tick();},close:()=>frame.remove()};
    };
    (async()=>{
        let s=await setup();expect(!s.$('[data-pending-invitation]').hidden&&s.calls.length===0,'Resident entry must initialize without a pending invitation');
        const input=s.$('[data-invitation-code]'), form=s.$('form');expect(input.maxLength===6&&input.pattern==='[0-9]{6}','Configured code length ignored');
        input.value='12';form.dispatchEvent(new s.w.Event('submit',{cancelable:true}));expect(!input.validity.valid&&s.calls.length===0,'Invalid code requested backend');
        input.value='۱۲٣۴۵۶';input.dispatchEvent(new s.w.Event('input'));expect(input.value==='123456'&&input.validity.valid,'Localized digits were rejected');
        form.dispatchEvent(new s.w.Event('submit',{cancelable:true}));expect(input.disabled&&form.querySelector('button').disabled,'Code form must lock while resolving');
        form.dispatchEvent(new s.w.Event('submit',{cancelable:true}));expect(s.calls.length===1&&s.calls[0].path==='/api/v1/unit-invitations/resolve'&&s.calls[0].body.token==='123456','Duplicate or incorrect resolve request');
        await s.reply();expect(!input.disabled&&!s.$('[data-invitation-accept]').hidden&&s.$('[data-invitation-details]').textContent.includes('۱۰۱'),'Invitation summary missing');s.close();
        s=await setup({area:'provider'});expect(s.$('[data-pending-invitation]').hidden&&s.calls.length===0,'Provider without invitation should not see entry');s.close();
        s=await setup({legacy:true});expect(s.calls.length===0,'Legacy markup must not throw or send an empty credential');s.close();
        s=await setup({pending:'a'.repeat(32),legacy:true});expect(s.calls.length===1,'Legacy link must resume without a code input');await s.reply();expect(!s.$('[data-invitation-accept]').hidden,'Legacy pending link failed');s.close();
        s=await setup({pending:'123456'});await s.fail(0);expect(!s.$('[data-invitation-retry]').hidden,'Network failure must allow retry');s.$('[data-invitation-retry]').click();expect(s.calls.length===2,'Retry failed');await s.fail(403);expect(s.$('[data-invitation-accept]').hidden&&s.$('[data-invitation-retry]').hidden&&!s.stored.has('buildino.pending-invitation'),'Forbidden invitation retained');s.close();
        document.getElementById('result').dataset.ok='true';
    })().catch(error=>document.getElementById('result').textContent=error.stack);
    </script></html>`);
    try {
        const output = execFileSync(chrome, ['--headless','--disable-gpu','--no-sandbox','--no-first-run','--allow-file-access-from-files','--user-data-dir='+join(directory,'profile'),'--virtual-time-budget=4000','--dump-dom',pathToFileURL(fixture).href], { encoding:'utf8',timeout:30000,maxBuffer:1024*1024,windowsHide:true,stdio:['ignore','pipe','pipe'] });
        assert.match(output.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0]||'No result',/data-ok="true"/);
    } finally {
        if (dirname(resolve(directory))===resolve(tmpdir())&&directory.includes('buildino-portal-invitation-')) rmSync(directory,{recursive:true,force:true,maxRetries:3,retryDelay:100});
    }
});
