import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { build } from 'esbuild';
import { compileScript, parse } from '@vue/compiler-sfc';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
test('Select2: actual menu selection, Vue single/multiple models, dependent Blade fields, disabled fieldsets and modal position', { skip: !existsSync(chrome) }, async () => {
    const bundle = await build({ absWorkingDir: project, bundle: true, write: false, format: 'iife', define: { 'process.env.NODE_ENV': '"production"' },
        stdin: { resolveDir: project, contents: `import {createApp,h,ref} from 'vue'; import DynamicForm from './resources/js/components/management/DynamicForm.vue';
        const model=ref({single:'a',many:['a','b']}), locked=ref(false), visible=ref(true), changes=ref(0);
        const fields=ref([{name:'single',label:'Single choice',type:'select',required:true,options:[{value:'a',label:'Alpha'},{value:'b',label:'Beta'}]},
            {name:'many',label:'Multiple choices',type:'multiselect',options:[{value:'a',label:'Alpha'},{value:'b',label:'Beta'}]}]);
        window.s2Fixture={model,locked,visible,changes,fields};
        createApp({render:()=>visible.value?h('fieldset',{disabled:locked.value},[h(DynamicForm,{modelValue:model.value,fields:fields.value,mode:'create',lookupBase:'/lookups',csrfToken:'test',groupOptional:false,'onUpdate:modelValue':value=>{model.value=value;changes.value++;}})]):null}).mount('#vue-host');` },
        plugins: [{ name: 'fixture', setup(builder) {
            builder.onResolve({ filter: /\.css$/ }, args => ({ path: args.path, namespace: 'css' }));
            builder.onLoad({ filter: /.*/, namespace: 'css' }, () => ({ contents: '', loader: 'js' }));
            builder.onLoad({ filter: /\.vue$/ }, args => { const { descriptor } = parse(readFileSync(args.path, 'utf8'), { filename: args.path });
                return { contents: compileScript(descriptor, { id: 'select-test-' + args.path, inlineTemplate: true, fs: { fileExists: existsSync, readFile: path => readFileSync(path,'utf8') } }).content, loader: 'ts' }; });
        } }],
    });
    const directory = mkdtempSync(join(tmpdir(), 'buildino-select2-'));
    try {
        const css = ['node_modules/select2/dist/css/select2.css','resources/css/buildino-select2.css','resources/css/management-inertia.css'].map(path => readFileSync(join(project,path),'utf8')).join('\n');
        const html = `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <style>${css}body{margin:16px}label{display:block;width:250px;margin:14px 0}label select{min-height:46px;height:46px}dialog{width:300px;max-width:80vw}fieldset{min-width:0}</style>
        <form id="native"><label>Province<select id="province" name="province" required><option value="">Choose province</option><option value="1">One</option><option value="2">Two</option></select></label>
        <label>City<select id="city" name="city" disabled><option value="">Choose province first</option></select></label>
        <label>Optional<select id="optional" name="optional"><option value="">All</option><option value="a">Alpha</option></select></label></form>
        <div id="vue-host"></div><dialog id="modal"><label>Inside dialog<select id="inside"><option value="">Choose</option><option value="1">One</option></select></label></dialog>
        <pre id="result"></pre><script>window.requestAnimationFrame=callback=>setTimeout(()=>callback(performance.now()),16);window.cancelAnimationFrame=clearTimeout;</script><script>${bundle.outputFiles[0].text}</script><script>
        const tick=()=>new Promise(r=>setTimeout(r,20)),expect=(ok,message)=>{if(!ok)throw Error(message);},$=s=>document.querySelector(s);
        const until=async fn=>{for(let i=0;i<100;i++){if(fn())return;await tick();}throw Error('Timeout: '+fn+' '+JSON.stringify({native:$('[name=single]')?.matches(':disabled'),fieldset:$('#vue-host fieldset')?.disabled,options:control($('[name=single]'))?.options.get('disabled')}));};
        const control=select=>window.jQuery(select).data('select2');
        const choose=async(select,text)=>{window.jQuery(select).select2('open');await tick();
            const option=[...document.querySelectorAll('.select2-results__option--selectable')].find(el=>el.textContent.trim()===text);
            expect(option,'Missing menu option '+text);option.dispatchEvent(new MouseEvent('mouseup',{bubbles:true}));await tick();};
        window.addEventListener('DOMContentLoaded',()=>{(async()=>{
            await until(()=>control($('#province'))&&control($('[name=single]'))&&control($('[name=many]')));
            expect(getComputedStyle($('#province')).height==='1px','Hidden select enlarged by shared form CSS');
            for(const type of ['pointerdown','mousedown','click']) {
                control($('#province')).close();
                const event=new MouseEvent(type,{bubbles:true,cancelable:true});
                $('#province').dispatchEvent(event);
                expect(event.defaultPrevented&&control($('#province')).isOpen(),'Native activation not routed to Select2: '+type);
            }
            control($('#province')).close();
            let nativeChanges=0;$('#province').addEventListener('change',()=>{nativeChanges++;$('#city').innerHTML='<option value="">City</option><option value="'+$('#province').value+'">Dependent city</option>';$('#city').disabled=false;});
            await choose($('#province'),'Two');await until(()=>control($('#city'))&&!control($('#city')).options.get('disabled'));
            expect(nativeChanges===1&&$('#province').value==='2','Native change duplicated or selection lost');
            await choose($('#city'),'Dependent city');expect(new FormData($('#native')).get('city')==='2','Dependent payload incorrect');
            expect(window.jQuery($('[name=many]')).val().join(',')==='a,b','Initial multiselect selection lost');
            const before=window.s2Fixture.changes.value;await choose($('[name=single]'),'Beta');
            expect(window.s2Fixture.model.value.single==='b'&&window.s2Fixture.changes.value===before+1,'Vue selection not synchronized exactly once');
            const count=window.s2Fixture.changes.value;window.s2Fixture.model.value={single:'a',many:['b']};await tick();
            expect(control($('[name=single]')).$selection.text().includes('Alpha')&&window.jQuery($('[name=many]')).val().join(',')==='b','External Vue selection not refreshed');
            expect(window.s2Fixture.changes.value===count,'Model refresh emitted a false change');
            window.s2Fixture.fields.value[0].options[0].label='Renamed';await tick();expect(control($('[name=single]')).$selection.text().includes('Renamed'),'Cached option label stale');
            window.s2Fixture.locked.value=true;await until(()=>control($('[name=single]')).options.get('disabled'));window.jQuery($('[name=single]')).select2('open');
            expect(!control($('[name=single]')).isOpen(),'Disabled fieldset stayed interactive');
            window.s2Fixture.locked.value=false;await until(()=>!control($('[name=single]')).options.get('disabled'));
            await choose($('#optional'),'Alpha');let cleared=0;$('#optional').addEventListener('change',()=>cleared++);
            const clear=control($('#optional')).$selection[0].querySelector('.select2-selection__clear');expect(clear,'Optional choice cannot be cleared');
            clear.dispatchEvent(new MouseEvent('mousedown',{bubbles:true,cancelable:true}));await tick();
            expect($('#optional').value===''&&cleared===1,'Clear did not emit exactly one native change');window.jQuery($('#optional')).select2('close');
            $('#native').reset();await tick();expect($('#province').value===''&&control($('#province')).$selection.text().includes('Choose province'),'Native reset not refreshed');
            $('#modal').showModal();await choose($('#inside'),'One');window.jQuery($('#inside')).select2('open');await tick();
            expect($('#modal').querySelector('.select2-dropdown'),'Dialog dropdown escaped top layer');
            const field=control($('#inside')).$container[0].getBoundingClientRect(),menu=$('#modal .select2-dropdown').getBoundingClientRect();
            expect(Math.min(Math.abs(menu.top-field.bottom),Math.abs(menu.bottom-field.top))<8&&Math.abs(menu.left-field.left)<8,'Dialog dropdown detached from field '+JSON.stringify({field:field.toJSON(),menu:menu.toJSON(),parent:getComputedStyle($('#modal')).position}));
            window.jQuery($('#inside')).select2('close');$('#modal').close();
            const source=$('[name=single]');window.jQuery(source).select2('open');await tick();window.s2Fixture.visible.value=false;await tick();
            expect(!document.querySelector('.select2-container--open .select2-dropdown')&&!window.jQuery(source).data('select2'),'Unmount left an orphaned menu');
            expect(document.documentElement.scrollWidth<=innerWidth,'Viewport overflow');$('#result').dataset.ok='true';
        })().catch(error=>$('#result').textContent=error.stack);});
        </script></html>`;
        const path=join(directory,'index.html');writeFileSync(path,html);
        for(const width of [1280,390]) {
            const output=execFileSync(chrome,['--headless','--disable-gpu','--no-sandbox','--no-first-run', '--force-device-scale-factor=1', '--window-size='+width+',1000','--virtual-time-budget=6000','--dump-dom',pathToFileURL(path).href],
                {encoding:'utf8',windowsHide:true,timeout:30000,maxBuffer:3*1024*1024,stdio:['ignore','pipe','ignore']});
            assert.match(output.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0]||'Missing browser result',/data-ok="true"/);
        }
    } finally {rmSync(directory,{recursive:true,force:true});}
});
