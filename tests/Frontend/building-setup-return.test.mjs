import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';

const project = fileURLToPath(new URL('../../', import.meta.url));
const chrome = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const render = `require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$config = json_decode(file_get_contents('php://stdin'), true);
foreach (['buildings','complexes'] as $key) $config[$key] = collect($config[$key]);
$source = file_get_contents('resources/views/management/building-setup.blade.php');
$source = explode("@section('content')", $source, 2)[1];
$source = explode('@endsection', $source, 2)[0];
echo Illuminate\\Support\\Facades\\Blade::render($source, ['setupConfig'=>$config, 'connections'=>collect(), 'connectionReviews'=>collect(), 'errors'=>new Illuminate\\Support\\ViewErrorBag()]);`;

for (const scenario of ['completed', 'completed-with-stale-draft', 'incomplete-draft', 'legacy-review-draft']) {
    test('setup return: ' + scenario, { skip: !existsSync(chrome) }, () => {
        const directory = mkdtempSync(join(tmpdir(), 'buildino-setup-return-'));
        try {
            const completed = scenario.startsWith('completed');
            const config = {
                actorId: 9, apiBase: 'http://localhost/api/v1', completeUrl: 'http://localhost/management/building-setup', csrfToken: '',
                initialBuildingId: 7, initialComplexId: 0, startNew: false,
                buildings: [{ id: 7, title: 'ساختمان تست', structureType: 'standalone', complexId: 4, complexType: 'standalone', complexTitle: 'تست', canUpdate: true }], complexes: [], locations: [],
                canCreateBuilding: false, canCreateComplex: false, canManageOwnership: true, canInvite: true,
                ownershipUrl: 'http://localhost/management/operations/ownerships', invitationsUrl: 'http://localhost/management/operations/invitations',
                unitsUrl: 'http://localhost/management/operations/units', dashboardUrl: 'http://localhost/management',
                completedSetup: completed ? { buildingId: 7, complexId: 4, floors: 3, units: 6, firstUnitId: 71, canManageOwnership: true, canInvite: false } : null,
            };
            const markup = execFileSync('php', ['-r', render], { cwd: project, input: JSON.stringify(config), encoding: 'utf8', windowsHide: true });
            const engine = readFileSync(join(project, 'public/js/buildino-building-setup-engine.js'), 'utf8');
            const source = readFileSync(join(project, 'public/js/buildino-building-setup.js'), 'utf8');
            const html = `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8">${markup}<pre id="result"></pre>
            <script>${engine}</script><script>
            window.BuildinoBuildingSetupConfig = ${JSON.stringify(config)};
            const key='buildino.building-setup.9.7.all';let calls=0;
            window.fetch=async()=>{calls++;throw Error('Unexpected API call during return');};
            if(${scenario !== 'completed'}) {
                const block={title:'طبقات',startFloor:1,unitsPerFloor:2,defaultArea:'',defaultBedrooms:'',usage:'residential',floors:[]};
                BuildinoBuildingSetupEngine.resizeFloors(block,3);
                sessionStorage.setItem(key,JSON.stringify({version:3,step:${scenario === 'legacy-review-draft' ? 3 : 2},draft:{token:crypto.randomUUID(),mode:'existing',buildingId:7,blocks:[block],started:false}}));
            }
            </script><script>${source}</script><script>
            try {
                const expect=(condition,message)=>{if(!condition)throw Error(message);};
                const root=document.querySelector('[data-building-setup]'),form=root.querySelector('[data-setup-form]');
                expect(root.dataset.setupReady==='true','Setup did not initialize');
                expect(form.hidden===${completed},'Wrong form visibility on return');
                expect(root.querySelector('[data-setup-success]').hidden===${!completed},'Wrong completion visibility');
                if(${completed}) {
                    expect(root.querySelector('.setup-steps').hidden,'Wizard steps still visible');
                    expect(root.querySelector('[data-setup-ownership]').href.includes('unit_id=71'),'Ownership lost unit context');
                    expect(root.querySelector('[data-setup-invite]').hidden,'Scoped invitation permission ignored');
                    expect(new URL(location.href).searchParams.get('building_id')==='7','Return URL lost building context');
                    expect(!sessionStorage.getItem(key),'Stale draft survived completion');
                    form.dispatchEvent(new Event('submit',{cancelable:true}));
                } else {
                    expect(!root.querySelector('[data-setup-step="2"]').hidden,'Unfinished work did not resume at its saved step');
                    expect(!root.querySelector('[data-setup-resume]').hidden,'Draft recovery hint missing');
                }
                expect(calls===0,'Return unexpectedly wrote or loaded structure');
                document.getElementById('result').dataset.ok='true';
            } catch(error) {document.getElementById('result').textContent=error.stack;}
            </script></html>`;
            const fixture = join(directory, 'fixture.html'); writeFileSync(fixture, html);
            const output = execFileSync(chrome, ['--headless', '--disable-gpu', '--no-sandbox', '--allow-file-access-from-files', '--no-first-run', '--user-data-dir=' + join(directory, 'profile'), '--virtual-time-budget=2000', '--dump-dom', pathToFileURL(fixture).href], { encoding: 'utf8', timeout: 30000, maxBuffer: 1024 * 1024, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
            const result = output.match(/<pre id="result"[^>]*>[\s\S]*?<\/pre>/)?.[0];
            assert.match(result || output.slice(-1000), /data-ok="true"/);
        } finally {
            if (dirname(resolve(directory)) === resolve(tmpdir()) && directory.includes('buildino-setup-return-')) {
                rmSync(directory, { recursive: true, force: true, maxRetries: 3, retryDelay: 100 });
            }
        }
    });
}
