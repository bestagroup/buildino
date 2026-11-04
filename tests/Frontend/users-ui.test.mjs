import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFile, execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';
import { build } from 'esbuild';
import { parse, compileScript } from '@vue/compiler-sfc';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
test('users: actual Vue wizard, scoped actions, validation, P05, retries and responsive layout', { skip: !existsSync(chrome) }, async () => {
    const resource = JSON.parse(execFileSync('php', ['-r', 'echo json_encode((require "config/management_crud.php")["resources"]["users"]);'], { cwd: project, encoding: 'utf8' }));
    const bundle = await build({ absWorkingDir: project, bundle: true, write: false, format: 'iife', define: { 'process.env.NODE_ENV': '"production"' },
        stdin: { contents: `import {createApp} from 'vue'; import Page from './resources/js/pages/management/access/Users.vue'; import Dialog from './resources/js/components/management/UserCreateDialog.vue'; let app; window.mountUsers=props=>{app?.unmount();app=createApp(Page,props);app.mount('#app');}; window.mountUserDialog=props=>createApp(Dialog,props).mount('#dialog');`, resolveDir: project },
        plugins: [{ name: 'vue-fixture', setup(builder) {
            builder.onResolve({ filter: /\.css$/ }, args => ({ path: args.path, namespace: 'css-fixture' }));
            builder.onLoad({ filter: /.*/, namespace: 'css-fixture' }, () => ({ contents: '', loader: 'js' }));
            builder.onResolve({ filter: /^@inertiajs\/vue3$/ }, () => ({ path: 'head', namespace: 'fixture' }));
            builder.onLoad({ filter: /.*/, namespace: 'fixture' }, () => ({ contents: 'export const Head={render:()=>null};' }));
            builder.onLoad({ filter: /\.vue$/ }, args => { const parsed = parse(readFileSync(args.path, 'utf8'), { filename: args.path }); if (parsed.errors.length) throw parsed.errors[0]; return { contents: compileScript(parsed.descriptor, { id: args.path, inlineTemplate: true, fs: { fileExists: existsSync, readFile: path => readFileSync(path, 'utf8') } }).content, loader: 'ts' }; });
        } }],
    });
    const styles = ['/public/css/buildino-fonts.css', '/public/css/buildino-management.css', '/public/css/buildino-design-system.css', '/public/css/buildino-unit-ownerships.css', '/resources/css/management-inertia.css', '/resources/css/finance-workspace.css', '/resources/css/user-workspace.css', '/resources/css/user-create-dialog.css'];
    const html = `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${styles.map(path => '<link rel="stylesheet" href="' + path + '">').join('')}<main id="app" style="padding:16px;max-width:1150px;margin:auto"></main><pre id="result" hidden></pre><script>window.requestAnimationFrame=callback=>setTimeout(()=>callback(performance.now()),16);window.cancelAnimationFrame=clearTimeout;</script><script src="/fixture.js"></script><script>
    const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)],expect=(value,message)=>{if(!value)throw Error(message);};
    const tick=()=>new Promise(r=>setTimeout(r,15));const until=async fn=>{for(let i=0;i<100;i++){if(fn())return;await tick();}throw Error('Timeout: '+fn+' / '+$('#app').textContent);};
    const input=async(name,value)=>{const el=$('[name="'+name+'"]');expect(el,'Missing '+name);el.value=value;el.dispatchEvent(new Event(el.tagName==='SELECT'?'change':'input',{bubbles:true}));await tick();};
    const submit=async()=>{$('#users-form').dispatchEvent(new Event('submit',{cancelable:true}));await tick();};
    let readOnly=false,fail='',posts=[],patches=[],deletes=[],queries=[];
    let data=[{id:1,first_name:'علی',last_name:'مدیر',mobile:'09121111111',is_active:true,is_blocked:false,can_update:true,can_delete:false}, {id:2,first_name:'سارا',last_name:'رضایی',mobile:'09122222222',is_active:false,is_blocked:false,can_update:false,can_delete:false}, {id:3,first_name:'محمد',last_name:'احمدی',mobile:'09123333333',is_active:true,is_blocked:true,can_update:true,can_delete:true}];
    window.fetch=async(path,options={})=>{
        const url=new URL(path,location.origin),method=options.method||'GET';
        const reply=(body,status=200)=>new Response(JSON.stringify(body),{status,headers:{'Content-Type':'application/json'}});
        if(method==='POST'){
            const body=JSON.parse(options.body);posts.push(body);
            if(fail==='validation'){fail='';return reply({errors:{mobile:['شماره تکراری است.']}},422);}
            if(fail==='server'){fail='';return reply({message:'SQLSTATE sensitive database detail'},500);}
            const row={...body,id:4,can_update:true,can_delete:true,mobile_verified_at:body.verify_mobile?'2026-11-04':null,email_verified_at:body.verify_email?'2026-11-04':null};data.push(row);return reply({data:row},201);
        }
        if(method==='PATCH'){const body=JSON.parse(options.body);patches.push(body);const row=data.find(r=>r.id===Number(url.pathname.split('/').at(-1)));Object.assign(row,body);return reply({data:row});}
        if(method==='DELETE'){const id=Number(url.pathname.split('/').at(-1));deletes.push(id);data=data.filter(r=>r.id!==id);return reply({success:true});}
        queries.push(url.searchParams);let rows=data.filter(r=>!url.searchParams.get('search')||r.first_name.includes(url.searchParams.get('search')));
        const status=url.searchParams.get('status');if(status)rows=rows.filter(r=>status==='blocked'?r.is_blocked:!r.is_blocked&&r.is_active===(status==='active'));
        rows.sort((a,b)=>b.id-a.id);const page=Number(url.searchParams.get('page')||1);return reply({data:rows.slice((page-1)*2,page*2).map(r=>({...r,...(readOnly?{can_update:false,can_delete:false}:{})})),total:rows.length,current_page:page,last_page:Math.ceil(rows.length/2)});
    };
    const props=()=>({resource:${JSON.stringify(resource)},csrfToken:'test',workspace:{userId:1,canCreate:!readOnly,canAssign:!readOnly,canAccessFlow:!readOnly,navigation:[]}});
    history.replaceState({},'', '/management/operations/users?flow=P05&from=ownership&unit_id=7');mountUsers(props());
    (async()=>{
        await until(()=>$('.users-item'));expect(!$('.user-registration-dialog'),'Old user dialog still rendered');
        expect($('[data-ownership-return] a').getAttribute('href')==='/management/operations/ownerships?unit_id=7&resume=ownership','Ownership return lost unit or accepted arbitrary destination');
        $('[data-users-add]').click();await tick();expect(!$('.users-list')&&$('#users-form'),'Editor did not replace list');
        await submit();expect($('[aria-invalid="true"]')&&posts.length===0,'Required validation bypassed');
        await input('first_name','<img src=x onerror=alert(1)>');await input('last_name','جدید');await input('mobile','09124444444');await input('email','invalid');await submit();expect($('[name="email"]').getAttribute('aria-invalid')==='true','Invalid email accepted');
        await input('email','user@example.test');await submit();expect(!$('[name="first_name"]')&&$('[name="verify_mobile"]'),'Step form appended instead of replaced');
        expect(!$('[name="is_active"]'),'P05 user could be prematurely activated');expect(document.documentElement.scrollWidth<=innerWidth,'Switch stage overflows');
        await submit();expect($('.finance-review')&&!$('.finance-review img'),'Review or escaping failed');
        const previous=$$('.users-form-actions button').find(el=>el.type==='button');previous.click();await tick();$$('.users-form-actions button').find(el=>el.type==='button').click();await tick();expect($('[name="mobile"]').value==='09124444444','Back lost entered data');
        await submit();await submit();fail='validation';await submit();expect($('[name="mobile"]').getAttribute('aria-invalid')==='true'&&$('#users-form'),'Server errors lost form');
        await input('mobile','09125555555');await submit();await submit();await submit();await until(()=>$('[data-users-success]'));
        expect(posts.length===2&&posts[1].is_active===false&&!('password'in posts[1]),'Creation changed initial password/activation contract');
        expect($('[data-ownership-return] a').getAttribute('href').includes('owner_id=4'),'Return did not carry created owner');
        expect($('[data-users-success] a').href.includes('user_id=4')&&$('[data-users-success] a').href.includes('flow=P05'),'Role assignment lost created user');
        expect(!$('#users-form')&&document.documentElement.scrollWidth<=innerWidth,'Success layout wrong');
        $('[data-users-edit]').click();await tick();await input('first_name','نام ویرایش‌شده');await submit();await submit();await submit();await until(()=>patches.length===1&&!$('#users-form'));
        expect(Object.keys(patches[0]).join(',')==='first_name','Edit overwrote untouched fields');
        const search=$('.users-filters input');search.value='ناموجود';search.dispatchEvent(new Event('input',{bubbles:true}));$('.users-filters').dispatchEvent(new Event('submit',{cancelable:true}));await until(()=>!$('.users-item'));expect(queries.at(-1).get('search')==='ناموجود','Search never reached server');
        search.value='';search.dispatchEvent(new Event('input',{bubbles:true}));await inputStatus('blocked');await until(()=>$('.users-item'));
        expect($$('.users-item').length===1&&$('.users-item').textContent.includes('محمد'),'Status filter wrong');
        $$('.users-item button').find(el=>el.textContent.includes('حذف')).click();await until(()=>$('[role="alertdialog"]'));$$('[role="alertdialog"] button').find(el=>el.textContent.trim()==='حذف کاربر').click();await until(()=>deletes.length===1&&!$('[role="alertdialog"]'));expect(deletes[0]===3,'Wrong deletion target');
        $('[data-users-add]').click();await tick();await input('first_name','تست');await input('last_name','شبکه');await input('mobile','09126666666');await submit();await submit();fail='server';await submit();
        expect($('[data-users-next]').disabled&&!$('#app').textContent.includes('SQLSTATE')&&!$('[data-users-success]'),'Unknown result leaked SQL or announced success');
        $('[data-users-error] button').click();await until(()=>!$('#users-form'));const count=posts.length;await tick();expect(posts.length===count,'Mutation automatically retried');
        readOnly=true;history.replaceState({},'', '/management/operations/users');mountUsers(props());await until(()=>$('.users-item'));
        expect(!$('[data-users-add]')&&!$('[data-users-edit]'),'Read-only user sees write controls');
        expect(document.documentElement.scrollWidth<=innerWidth,'User list overflows');
        const host=document.createElement('div');host.id='dialog';host.setAttribute('data-user-create-root','');document.body.append(host);mountUserDialog({fields:props().resource.fields,csrfToken:'test'});
        let createdEvent;window.addEventListener('buildino:user-created',event=>createdEvent=event.detail);
        const openDialog=()=>window.dispatchEvent(new CustomEvent('buildino:user-create',{detail:{unitId:7,unitLabel:'واحد ۱۰۱'}}));
        openDialog();await until(()=>$('.user-create-dialog')?.open);
        expect([...$$('.user-create-dialog input')].map(el=>el.name).join(',')==='first_name,last_name,mobile,email,national_code','Modal field order is inconsistent');
        if(innerWidth===500)expect(matchMedia('(prefers-reduced-motion: reduce)').matches&&getComputedStyle($('.user-create-dialog')).animationName==='none','Reduced motion still animates modal');
        const dialogSubmit=async()=>{$('.user-create-dialog form').dispatchEvent(new Event('submit',{cancelable:true}));await tick();};
        await dialogSubmit();expect($('.user-create-dialog [aria-invalid="true"]'),'Modal required validation missing');
        await input('first_name','مالک مودال');await input('last_name','آزمایشی');await input('mobile','09127777777');
        $('.user-create-dialog').dispatchEvent(new Event('cancel',{cancelable:true}));await until(()=>!$('.user-create-dialog').open);
        openDialog();await tick();expect($('.user-create-dialog [name="mobile"]').value==='09127777777','Cancel lost entered user details');
        fail='validation';await dialogSubmit();expect($('.user-create-dialog [name="mobile"]').getAttribute('aria-invalid')==='true','Modal lost server validation');
        await input('mobile','09128888888');await dialogSubmit();await until(()=>$('[data-user-create-done]'));
        expect(createdEvent?.unitId===7&&createdEvent?.user?.id===4,'Modal did not return created user with unit');
        expect($('.user-create-dialog').open&&$('.user-create-dialog').scrollWidth<=$('.user-create-dialog').clientWidth,'Success modal overflows or closes unexpectedly');
        $('[data-user-create-done]').click();await until(()=>!$('.user-create-dialog').open);
        openDialog();await tick();await input('first_name','تست');await input('last_name','شبکه');await input('mobile','09129999999');fail='server';await dialogSubmit();
        expect($('[data-user-create-save]').disabled&&!$('.user-create-dialog').textContent.includes('SQLSTATE'),'Unknown modal result leaked SQL or allowed retry');
        $('#result').dataset.ok='true';
    })().catch(e=>$('#result').textContent=e.stack);
    async function inputStatus(value){const el=$('.users-filters select');el.value=value;el.dispatchEvent(new Event('change',{bubbles:true}));await tick();$('.users-filters').dispatchEvent(new Event('submit',{cancelable:true}));await tick();}
    </script></html>`;
    const server = createServer((req, res) => {
        const path = new URL(req.url, 'http://localhost').pathname;
        if (path === '/fixture.js') { res.setHeader('Content-Type', 'application/javascript'); res.end(bundle.outputFiles[0].text); return; }
        if (path.startsWith('/public/') || path.startsWith('/resources/css/')) { try { res.setHeader('Content-Type', path.endsWith('.css') ? 'text/css' : 'application/octet-stream'); res.end(readFileSync(join(project, path))); } catch { res.writeHead(404).end(); } return; }
        res.setHeader('Content-Type', 'text/html; charset=utf-8'); res.end(html);
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    try { for (const width of [1280, 500]) {
        const capture = [...(process.env.USERS_CAPTURE ? ['--screenshot=' + join(tmpdir(), 'buildino-users-' + width + '.png')] : []), ...(width===500?['--force-prefers-reduced-motion=reduce']:[])];
        const { stdout } = await promisify(execFile)(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--no-first-run', '--force-device-scale-factor=1', '--window-size=' + width + ',1000', '--virtual-time-budget=7000', '--dump-dom', ...capture, 'http://127.0.0.1:' + server.address().port], { timeout: 30000, maxBuffer: 2 * 1024 * 1024, windowsHide: true });
        const result = stdout.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0] || stdout.slice(-500); assert.match(result, /data-ok="true"/, width + ': ' + result);
    } } finally { await new Promise(resolve => server.close(resolve)); }
});
