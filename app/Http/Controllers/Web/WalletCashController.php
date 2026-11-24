<?php

namespace App\Http\Controllers\Web;

use App\Enums\FileScanStatus;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Unit;
use App\Models\WalletCashDocument;
use App\Services\Security\BuildingPermissionScopeService;
use App\Services\Wallet\WalletCashDocumentService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class WalletCashController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['building_id' => ['nullable', 'integer', 'min:1']]);
        $ids = app(BuildingPermissionScopeService::class)->buildingIds($request->user(), 'wallet-cash.manage');
        $buildings = Building::when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('title')->get()
            ->filter(fn ($b) => app(PermissionChecker::class)->allows($request->user(), 'wallet-cash.manage', $b))->values();
        $building = isset($data['building_id']) ? $buildings->firstWhere('id', $data['building_id']) : ($buildings->count() === 1 ? $buildings->first() : null);
        abort_if(isset($data['building_id']) && ! $building, 403);
        $units = $building ? Unit::with('floor')->whereHas('floor.block', fn ($q) => $q->where('building_id', $building->id))->orderBy('unit_number')->get() : collect();
        $documents = $building ? WalletCashDocument::where('building_id', $building->id)->with(['unit', 'createdBy'])->latest('id')->paginate(25)->withQueryString() : null;
        $wallet = $building?->wallets()->where('currency', $building->currency ?: 'IRR')->first();

        return view('management.wallet-cash', compact('buildings', 'building', 'units', 'documents', 'wallet') + ['user' => $request->user()]);
    }

    public function store(Request $request, Building $building, WalletCashDocumentService $service)
    {
        abort_unless(app(PermissionChecker::class)->allows($request->user(), 'wallet-cash.manage', $building), 403);
        $data = $request->validate([
            'uuid' => ['required', 'uuid'], 'unit_id' => ['nullable', 'integer', 'min:1'],
            'direction' => ['required', 'in:receipt,payment'], 'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'document_number' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);
        $service->post($building, $request->user(), $data, $request->file('image'));

        return redirect()->route('management.wallet-cash.index', ['building_id' => $building->id])->with('status', 'سند ثبت شد و موجودی کیف پول به‌روزرسانی شد.');
    }

    public function image(Request $request, WalletCashDocument $document)
    {
        abort_unless(app(PermissionChecker::class)->allows($request->user(), 'wallet-cash.manage', $document->building), 403);
        $file = $document->file;
        abort_unless($file && $file->scan_status === FileScanStatus::Clean, 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
