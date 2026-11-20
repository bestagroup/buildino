<?php

putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array');
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
\Illuminate\Support\Facades\URL::forceRootUrl('http://localhost');

$building = (new \App\Models\Building())->forceFill(['id' => 1, 'title' => 'ساختمان آفتاب']);
$data = ['building' => $building, 'buildings' => collect([$building]), 'errors' => new \Illuminate\Support\ViewErrorBag];
if (($argv[1] ?? '') === 'preview') {
    $data['token'] = 'preview-token';
    $data['rows'] = [['row' => 1, 'building' => 'ساختمان آفتاب', 'code' => 'BLD-1', 'unit' => '۱۰۱', 'floor' => 1,
        'area' => 120, 'bedrooms' => 2, 'parking_count' => 1, 'storage_count' => 1, 'usage_type' => 'residential',
        'owner_first' => 'علی', 'owner_last' => 'رضایی', 'owner_mobile' => '09121112233',
        'tenant_first' => 'سارا', 'tenant_last' => 'احمدی', 'tenant_mobile' => '09124445566', 'resident_count' => 3]];
}
$source = file_get_contents(resource_path('views/management/user-import.blade.php'));
$start = strpos($source, "@section('content')") + strlen("@section('content')");
echo \Illuminate\Support\Facades\Blade::render(substr($source, $start, strpos($source, '@endsection', $start) - $start), $data);
