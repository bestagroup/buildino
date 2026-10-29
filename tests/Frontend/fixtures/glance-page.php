<?php

// Render the actual report template using unpersisted models; never connect to application data.
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array');
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);

$building = (new \App\Models\Building())->forceFill(['id' => 1, 'title' => 'ساختمان شمیم', 'currency' => 'IRR']);
$rows = collect();
foreach ([[101, 1, '۱۰۱', ['علی رضایی'], ['سارا احمدی'], true, true, 3, 10000, 4000], [102, 1, '102', ['<img src=x onerror="window.glanceXss=true">'], [], false, true, 0, 0, 9000], [201, 2, '۲۰۱', ['رضا کریمی'], ['مریم نوری'], true, false, 2, 2000, 0]] as [$id, $blockId, $number, $owners, $residents, $occupied, $active, $people, $debt, $available]) {
    $block = (new \App\Models\Block())->forceFill(['id' => $blockId, 'title' => $blockId === 1 ? 'بلوک آفتاب' : 'بلوک بهار']);
    $floor = (new \App\Models\Floor())->forceFill(['id' => $blockId, 'block_id' => $blockId, 'floor_number' => 1, 'title' => 'طبقه اول'])->setRelation('block', $block);
    $unit = (new \App\Models\Unit())->forceFill(['id' => $id, 'unit_number' => $number, 'title' => '', 'is_active' => $active])->setRelation('floor', $floor);
    $rows->push(['unit' => $unit, 'floor' => $floor, 'owners' => collect($owners), 'residents' => collect($residents), 'occupied' => $occupied, 'people' => $people,
        'debt' => $debt, 'available' => $available, 'balance' => $available + 1000, 'locked' => 1000, 'walletInactive' => ! $active,
        'net' => $debt - $available, 'shortfall' => max(0, $debt - $available), 'surplus' => max(0, $available - $debt),
        'latest' => $id === 101 ? 5000 : null, 'latestSource' => $id === 101 ? 'invoice' : 'none']);
}
$period = (new \App\Models\ChargePeriod())->forceFill(['id' => 1, 'title' => 'شارژ آبان', 'period_start' => '2026-10-23', 'period_end' => '2026-11-21', 'status' => 'calculated']);
$report = ['rows' => $rows, 'floors' => $rows->groupBy(fn ($row) => $row['floor']->id), 'period' => $period, 'currency' => 'IRR', 'generatedAt' => \Carbon\Carbon::parse('2026-10-29 14:30:00', 'Asia/Tehran'),
    'totals' => ['units' => 3, 'occupied' => 2, 'people' => 5, 'debt' => 12000, 'available' => 13000, 'shortfall' => 8000, 'surplus' => 9000, 'net' => -1000]];
$buildings = collect([$building]);
if (($argv[1] ?? '') === 'empty') { $report = null; $building = null; $buildings = collect(); }
$source = file_get_contents(resource_path('views/management/at-a-glance.blade.php'));
$start = strpos($source, "@section('content')") + strlen("@section('content')");
$markup = substr($source, $start, strpos($source, '@endsection', $start) - $start);
echo \Illuminate\Support\Facades\Blade::render($markup, ['building' => $building, 'buildings' => $buildings, 'report' => $report, 'canViewInvoices' => true, 'canViewPeriods' => true]);
