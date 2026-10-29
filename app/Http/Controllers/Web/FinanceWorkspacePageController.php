<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\Building;
use App\Models\Block;
use App\Models\BuildingBankAccount;
use App\Models\FinancialCategory;
use App\Models\Fund;
use App\Services\Security\BuildingPermissionScopeService;
use App\Services\Web\ManagementUiContextService;
use App\Support\ManagementPageCatalog;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FinanceWorkspacePageController extends Controller
{
    public function __invoke(Request $request, string $resource, PermissionChecker $permissions, ManagementUiContextService $ui): Response
    {
        $user = $request->user();
        $configuration = $ui->resourceForUser($user, config("management_crud.resources.$resource"));
        $required = config("management_crud.resources.$resource.permission");
        $prefix = explode('.', $required)[0];
        $actionPermissions = [];
        foreach ($configuration['actions'] ?? [] as $action) {
            $actionPermissions[$action['key']] = $prefix.'.'.($resource === 'loyalty-claims' ? 'update' : match ($action['key']) { 'paid' => 'pay', default => $action['key'] });
        }
        $buildingIds = app(BuildingPermissionScopeService::class)->buildingIds($user, $required);
        $buildings = Building::query()->when($buildingIds !== null, fn ($query) => $query->whereIn('id', $buildingIds))->orderBy('title')->get()
            ->filter(fn ($building): bool => $resource === 'expenses' || $permissions->allows($user, $required, $building))
            ->map(function ($building) use ($user, $permissions, $prefix, $actionPermissions): array {
                $abilities = [];
                foreach (array_unique(['invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete', 'invoices.issue', 'invoices.adjust', 'payments.view', 'payments.create', 'payments.verify', 'charge-periods.view', "$prefix.create", "$prefix.update", "$prefix.delete", ...array_values($actionPermissions)]) as $permission) {
                    $abilities[$permission] = $permissions->allows($user, $permission, $building);
                }

                return ['id' => $building->id, 'title' => $building->title, 'currency' => $building->currency, 'abilities' => $abilities];
            })->values();
        $readableIds = in_array($resource, ['invoices', 'invoice-installments', 'payments'], true)
            ? $buildings->filter(fn (array $building): bool => $building['abilities']['invoices.view'])->pluck('id') : collect();
        $units = Unit::query()->whereHas('floor.block', fn ($query) => $query->whereIn('building_id', $readableIds))
            ->with('floor.block')->orderBy('unit_number')->get()
            ->map(fn (Unit $unit): array => [
                'id' => $unit->id, 'building_id' => $unit->floor->block->building_id,
                'label' => $unit->floor->block->title.' / '.($unit->floor->title ?: 'طبقه '.$unit->floor->floor_number).' / واحد '.$unit->unit_number,
            ])->values();
        $visible = $ui->visibleResourceKeys($user);
        $navigation = collect(config('management_crud.resources'))->filter(fn (array $item, string $key): bool => $item['group'] === 'finance' && in_array($key, $visible, true))
            ->map(fn (array $item, string $key): array => ['key' => $key, 'title' => $item['title'], 'url' => route('management.operations.show', $key)])->values();
        $scopeIds = $buildings->pluck('id');
        $lookups = [];
        $blocks = collect();
        if (in_array($resource, ['expenses', 'incomes'], true)) {
            $lookups['fund_id'] = Fund::query()->whereIn('building_id', $scopeIds)->orderBy('title')->get()
                ->map(fn ($fund): array => ['value' => $fund->id, 'label' => $fund->title, 'building_id' => $fund->building_id])->values();
            $lookups['financial_category_id'] = FinancialCategory::query()->where(fn ($query) => $query->whereIn('building_id', $scopeIds)->orWhereNull('building_id'))
                ->where('type', $resource === 'expenses' ? 'expense' : 'income')->where('is_active', true)->orderBy('title')->get()
                ->map(fn ($category): array => ['value' => $category->id, 'label' => $category->title, 'building_id' => $category->building_id])->values();
        }
        if ($resource === 'expenses') {
            $blocks = Block::query()->whereIn('building_id', $scopeIds)->orderBy('sort_order')->get()->map(function (Block $block) use ($user, $permissions): array {
                $abilities = [];
                foreach (['view', 'create', 'update', 'delete'] as $action) $abilities[$action] = $permissions->allows($user, "expenses.$action", $block);
                return ['value' => $block->id, 'label' => $block->title, 'building_id' => $block->building_id, 'abilities' => $abilities];
            })->values();
            $lookups['block_id'] = $blocks;
        }
        if ($resource === 'wallet-payouts') {
            $lookups['building_bank_account_id'] = BuildingBankAccount::query()->whereIn('building_id', $scopeIds)->where('is_verified', true)->where('is_active', true)->get()
                ->map(fn ($account): array => ['value' => $account->id, 'label' => $account->bank_name.' · '.$account->account_holder_name.' · '.$account->iban, 'building_id' => $account->building_id])->values();
        }

        return Inertia::render(in_array($resource, ['invoices', 'invoice-installments', 'payments'], true) ? 'management/finance/Invoices' : 'management/finance/Records', [
            'resourceKey' => $resource,
            'title' => config("management_crud.resources.$resource.title"),
            'resource' => $configuration,
            'workspace' => [
                'buildings' => $buildings, 'units' => $units, 'navigation' => $navigation,
                'groups' => ManagementPageCatalog::FINANCE_GROUPS, 'prefix' => $prefix, 'actionPermissions' => $actionPermissions,
                'lookups' => $lookups, 'blocks' => $blocks,
                'initial' => $request->only(['building_id', 'unit_id', 'unit_invoice_id', 'charge_period_id']),
            ],
            'csrfToken' => csrf_token(),
        ]);
    }
}
