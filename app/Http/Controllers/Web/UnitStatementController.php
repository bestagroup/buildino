<?php

namespace App\Http\Controllers\Web;

use App\Enums\WalletTopUpStatus;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\WalletTopUp;
use App\Services\Files\FileStorageService;
use App\Services\InvoiceAccessService;
use App\Services\MonthlyUnitStatementService;
use App\Services\PaymentService;
use App\Services\Wallet\WalletTopUpService;
use App\Services\Web\ManagementHeaderContextService;
use App\Services\Web\PortalAccessService;
use App\Services\Web\ResidentPortalWorkspace;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class UnitStatementController extends Controller
{
    public function index(Request $request, ?Unit $unit = null)
    {
        $manager = $request->routeIs('management.*');
        if ($manager) {
            abort_unless($unit?->exists, 404);
            $building = $unit->floor->block->building;
            abort_unless(app(PermissionChecker::class)->allows($request->user(), 'invoices.view', $building), 403);
            $units = collect([$unit]);
        } else {
            $units = app(PortalAccessService::class)->residentUnits($request->user());
            $availableUnits = $units;
            if ($request->filled('unit_id')) {
                $request->validate(['unit_id' => ['integer', 'min:1']]);
                abort_unless($units->contains('id', $request->integer('unit_id')), 403);
                $units = $units->where('id', $request->integer('unit_id'));
            }
        }
        $statements = app(MonthlyUnitStatementService::class)->statements($units->pluck('id')->all(), $manager ? null : $request->user());
        $balances = $units->mapWithKeys(function ($unit) {
            $currency = $unit->floor->block->building->currency ?: 'IRR';
            $wallet = $unit->wallets()->where('currency', $currency)->first();

            return [$unit->id => $wallet?->availableBalance() ?? 0];
        });
        $canDeposit = $manager && app(PermissionChecker::class)->allows($request->user(), 'unit-wallet-deposits.create', $building);

        return view('shared.unit-statements', [
            'resource' => 'invoices', 'statementUnits' => $availableUnits ?? $units,
            'manager' => $manager, 'units' => $units, 'statements' => $statements, 'balances' => $balances,
            'deposits' => $manager ? WalletTopUp::where('target_type', $unit->getMorphClass())->where('target_id', $unit->id)->with('payment.fileRelations.file')->latest('id')->limit(50)->get() : collect(),
            'canDeposit' => $canDeposit, 'user' => $request->user(),
            'portalData' => $manager ? [] : [...app(ResidentPortalWorkspace::class)->context($request->user()), 'area' => 'resident', 'header' => app(ManagementHeaderContextService::class)->context($request->user())],
            'portalAreas' => ['resident' => true, 'provider' => app(PortalAccessService::class)->hasProviderAccess($request->user())],
        ]);
    }

    public function settle(Request $request, UnitInvoice $invoice)
    {
        $units = app(PortalAccessService::class)->residentUnits($request->user());
        abort_unless($units->contains('id', $invoice->unit_id), 403);
        abort_unless(app(InvoiceAccessService::class)->canPay($request->user(), $invoice), 403);
        app(MonthlyUnitStatementService::class)->settle($invoice, $request->user(), $request->user());
        if ($request->expectsJson()) {
            return response()->json(['message' => 'تسویه انجام شد.']);
        }

        return back()->with('status', 'تسویه از کیف پول واحد انجام شد و مبلغ به کیف پول ساختمان انتقال یافت.');
    }

    public function deposit(Request $request, Unit $unit)
    {
        $building = $unit->floor->block->building;
        abort_unless(app(PermissionChecker::class)->allows($request->user(), 'unit-wallet-deposits.create', $building), 403);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'reference' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $file = null;
        try {
            DB::transaction(function () use ($request, $unit, $building, $data, &$file) {
                Unit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
                $topup = app(WalletTopUpService::class)->create($building, $request->user(), $unit, [
                    'amount' => $data['amount'], 'method' => 'bank_transfer',
                    'idempotency_key' => 'manager-deposit:'.$building->id.':'.hash('sha256', trim($data['reference'])),
                    'description' => 'رسید '.$data['reference'].' · '.($data['description'] ?? ''),
                    'collect_outstanding' => false,
                ]);
                if ($topup->status === WalletTopUpStatus::Credited) {
                    return;
                }
                $file = app(FileStorageService::class)->store($request->file('receipt'), $request->user(), $topup->payment, ['category' => 'receipt', 'purpose' => 'unit_wallet_deposit']);
                app(PaymentService::class)->verify($topup->payment, $request->user());
            });
        } catch (\Throwable $exception) {
            if ($file && ! \App\Models\File::whereKey($file->id)->exists()) {
                Storage::disk($file->disk)->delete($file->path);
            }
            throw $exception;
        }

        return back()->with('status', 'واریزی و سند ثبت شد؛ موجودی کیف پول واحد افزایش یافت.');
    }
}
