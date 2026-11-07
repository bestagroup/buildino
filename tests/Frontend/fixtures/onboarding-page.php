<?php

// Actual Blade, isolated from application data and external services.
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array');
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
app('session.store')->start();
app('request')->setLaravelSession(app('session.store'));
$errors = new \Illuminate\Support\ViewErrorBag;
$mode = $argv[1] ?? 'standalone';
$province = app(\App\Services\System\IranLocations::class)->forForm()[0];
$province['cities'] = [$province['cities'][0]];
if ($mode === 'register') {
    $source = file_get_contents(resource_path('views/auth/register.blade.php'));
    $source = str_replace("@vite('resources/js/app.js')", '', $source);
    $html = \Illuminate\Support\Facades\Blade::render($source, [
        'errors' => $errors, 'personas' => config('self_registration.personas'), 'selectedPersona' => 'block_manager',
        'locationProvinces' => [$province], 'selectedProvinceId' => '', 'selectedCityId' => '', 'invitationToken' => '',
    ]);
    // The test loads the production JS itself; never call the application's servers.
    echo preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);
    exit;
}
$building = ['id' => 1, 'title' => $mode === 'block' ? 'بلوک آفتاب' : 'ساختمان سرو',
    'complexTitle' => 'مجتمع بهار', 'complexId' => 10, 'complexType' => $mode === 'block' ? 'unlinked' : 'standalone',
    'structureType' => $mode === 'legacy' ? null : ($mode === 'block' ? 'complex_block' : 'standalone'),
    'address' => 'نشانی قبلی', 'canUpdate' => true, 'postalCode' => '1234567890', 'parkingCount' => 3, 'storageCount' => 1];
$setupConfig = ['actorId' => 1, 'apiBase' => '/api/v1', 'csrfToken' => 'fixture',
    'buildings' => collect($mode === 'complex' ? [] : [$building]),
    'complexes' => collect($mode === 'complex' ? [['id' => 10, 'title' => 'مجتمع بهار', 'code' => 'CMP-TEST', 'workspaceType' => 'complex']] : []),
    'canCreateComplex' => false, 'canCreateBuilding' => $mode === 'complex', 'canInvite' => true, 'canManageOwnership' => true,
    'locations' => [$province], 'initialBuildingId' => $mode === 'complex' ? 0 : 1, 'initialComplexId' => $mode === 'complex' ? 10 : 0, 'completedSetup' => null,
    'completeUrl' => '/management/building-setup', 'dashboardUrl' => '/management', 'unitsUrl' => '/management/operations/units',
    'invitationsUrl' => '/management/operations/invitations', 'ownershipUrl' => '/management/operations/ownerships'];
$source = file_get_contents(resource_path('views/management/building-setup.blade.php'));
$start = strpos($source, "@section('content')") + strlen("@section('content')");
$markup = substr($source, $start, strpos($source, '@endsection', $start) - $start);
echo \Illuminate\Support\Facades\Blade::render($markup, ['setupConfig' => $setupConfig, 'connections' => collect(), 'connectionReviews' => collect(), 'errors' => $errors]);
echo '<script>window.BuildinoBuildingSetupConfig='.json_encode($setupConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT).';</script>';
