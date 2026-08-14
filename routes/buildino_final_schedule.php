<?php

/*
|--------------------------------------------------------------------------
| Buildino scheduler composition
|--------------------------------------------------------------------------
|
| Scheduler definitions follow the same rule as API routes: execute them on
| every Laravel application boot and compose each schedule file exactly once.
|
*/

require __DIR__.'/domain_schedule.php';
require __DIR__.'/report_export_schedule.php';
require __DIR__.'/production_schedule.php';
