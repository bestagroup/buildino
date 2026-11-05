<?php

namespace Tests\Feature\Financial;

use App\Models\ChargePeriod;
use App\Models\Unit;
use App\Services\Charge\ResidentCountHistory;
use App\Services\OccupancyService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesChargePeriodScenario;
use Tests\TestCase;

/** Run only against a dedicated disposable MySQL/MariaDB database ending in _test. */
final class ChargePeriodConcurrencyTest extends TestCase
{
    use CreatesChargePeriodScenario;

    private array $workers = [];

    private array $signals = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('CHARGE_CONCURRENCY_TESTS') !== '1') {
            $this->markTestSkipped('Opt-in real MySQL/MariaDB concurrency test.');
        }
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertStringEndsWith('_test', DB::connection()->getDatabaseName());
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }
        foreach ($this->signals as $signal) {
            @unlink($signal);
        }
        if ($this->app) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        parent::tearDown();
    }

    private function startWorker(string $action, ChargePeriod $period, int $actor, string $token): array
    {
        $signal = tempnam(sys_get_temp_dir(), 'charge-worker-');
        $this->signals[] = $signal;
        $worker = new Process([
            PHP_BINARY, base_path('tests/Support/charge-period-worker.php'),
            $action, (string) $period->id, (string) $actor, $token, $signal,
        ], base_path(), [
            'CHARGE_TEST_DATABASE_CONFIG' => json_encode(config('database.connections.mysql'), JSON_THROW_ON_ERROR),
        ]);
        $worker->setTimeout(15);
        $worker->start();
        $this->workers[] = $worker;
        $deadline = microtime(true) + 10;
        do {
            $id = (int) file_get_contents($signal);
            if ($id > 0) {
                return [$worker, $id];
            }
            usleep(10000);
        } while (microtime(true) < $deadline && $worker->isRunning());
        $this->fail('Worker did not connect: '.$worker->getErrorOutput());
    }

    private function awaitRowLock(int $connectionId): void
    {
        $deadline = microtime(true) + 10;
        do {
            $waiting = DB::selectOne(
                "SELECT COUNT(*) AS total FROM information_schema.innodb_trx WHERE trx_mysql_thread_id = ? AND trx_state = 'LOCK WAIT'",
                [$connectionId]
            );
            if ((int) $waiting->total === 1) {
                $this->assertTrue(true);

                return;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $this->fail('The competing connection did not wait for the row lock.');
    }

    private function workerResult(Process $worker): array
    {
        $worker->wait();
        $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());

        return json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_two_calculation_confirmations_commit_only_once(): void
    {
        [, $actor, , $period, , $service] = $this->chargePeriodScenario();
        $token = $service->preview($period)['review_token'];
        DB::beginTransaction();
        ChargePeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
        [$first, $firstId] = $this->startWorker('calculate', $period, $actor->id, $token);
        [$second, $secondId] = $this->startWorker('calculate', $period, $actor->id, $token);
        $this->awaitRowLock($firstId);
        $this->awaitRowLock($secondId);
        DB::commit();
        $results = [$this->workerResult($first), $this->workerResult($second)];
        $this->assertEqualsCanonicalizing(['ok', 'rejected'], array_column($results, 'result'));
        $rejected = array_values(array_filter($results, fn ($result) => $result['result'] === 'rejected'))[0];
        $this->assertSame(['review_token'], $rejected['fields']);
        $this->assertSame(1, $period->fresh()->calculation_revision);
        $this->assertDatabaseCount('charge_calculations', 1);
    }

    public function test_two_issuance_requests_create_only_one_invoice(): void
    {
        [, $actor, , $period, , $service] = $this->chargePeriodScenario();
        $service->calculate($period, $service->preview($period)['review_token']);
        DB::beginTransaction();
        ChargePeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
        [$first, $firstId] = $this->startWorker('issue', $period, $actor->id, '');
        [$second, $secondId] = $this->startWorker('issue', $period, $actor->id, '');
        $this->awaitRowLock($firstId);
        $this->awaitRowLock($secondId);
        DB::commit();
        $results = [$this->workerResult($first), $this->workerResult($second)];
        $this->assertEqualsCanonicalizing(['ok', 'rejected'], array_column($results, 'result'));
        $this->assertDatabaseCount('unit_invoices', 1);
        $this->assertSame('issued', $period->fresh()->status->value);
    }

    public function test_calculation_waits_for_resident_count_writer_and_rejects_stale_review(): void
    {
        [$graph, $actor, , $period, , $service] = $this->chargePeriodScenario();
        $token = $service->preview($period)['review_token'];
        DB::beginTransaction();
        Unit::whereKey($graph['unit']->id)->lockForUpdate()->firstOrFail();
        app(ResidentCountHistory::class)->record($graph['unit'], 5, '2026-10-01');
        [$worker, $id] = $this->startWorker('calculate', $period, $actor->id, $token);
        $this->awaitRowLock($id);
        DB::commit();
        $this->assertSame(['result' => 'rejected', 'fields' => ['review_token']], $this->workerResult($worker));
        $this->assertDatabaseCount('charge_calculations', 0);
    }

    public function test_issuance_waits_for_occupancy_writer_and_rejects_changed_calculation(): void
    {
        [$graph, $actor, $occupancy, $period, , $service] = $this->chargePeriodScenario();
        $service->calculate($period, $service->preview($period)['review_token']);
        DB::beginTransaction();
        Unit::whereKey($graph['unit']->id)->lockForUpdate()->firstOrFail();
        app(OccupancyService::class)->end($occupancy, $actor, '2026-10-10');
        [$worker, $id] = $this->startWorker('issue', $period, $actor->id, '');
        $this->awaitRowLock($id);
        DB::commit();
        $this->assertSame(['result' => 'rejected', 'fields' => ['calculation']], $this->workerResult($worker));
        $this->assertDatabaseCount('unit_invoices', 0);
    }
}
