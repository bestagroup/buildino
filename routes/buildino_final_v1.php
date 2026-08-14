<?php

/*
|--------------------------------------------------------------------------
| Buildino V1 route composition
|--------------------------------------------------------------------------
|
| Laravel's application is recreated multiple times inside a PHPUnit process.
| Route files MUST be executed on every application boot. `require_once`
| is process-scoped and therefore causes routes to disappear after the first
| application instance.
|
| Every additive route file is composed exactly once from this aggregator.
|
*/

require __DIR__.'/wallet_operations_v1.php';
require __DIR__.'/service_marketplace_v1.php';
require __DIR__.'/provider_settlement_v1.php';
require __DIR__.'/wallet_accounting_v1.php';
require __DIR__.'/reporting_v1.php';
require __DIR__.'/report_exports_v1.php';
require __DIR__.'/payment_gateways_v1.php';
require __DIR__.'/production_readiness_v1.php';
require __DIR__.'/finalization_v1.php';
