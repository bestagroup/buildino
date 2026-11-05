<?php

use App\Models\ChargePeriod;
use App\Models\User;
use App\Services\ChargePeriodService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Separate application process and database connection for concurrency regression tests.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql' => json_decode(getenv('CHARGE_TEST_DATABASE_CONFIG'), true, flags: JSON_THROW_ON_ERROR)]);
DB::purge('mysql');
$period = ChargePeriod::findOrFail($argv[2]);
$service = app(ChargePeriodService::class);
file_put_contents($argv[5], (string) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
try {
    if ($argv[1] === 'calculate') {
        $service->calculate($period, $argv[4]);
    } else {
        $service->issue($period, User::findOrFail($argv[3]));
    }
    echo json_encode(['result' => 'ok'], JSON_THROW_ON_ERROR);
} catch (ValidationException $exception) {
    echo json_encode(['result' => 'rejected', 'fields' => array_keys($exception->errors())], JSON_THROW_ON_ERROR);
}
