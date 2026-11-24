<?php

namespace App\Services\Wallet;

use App\Enums\WalletTransferType;
use App\Models\Building;
use App\Models\File;
use App\Models\Unit;
use App\Models\User;
use App\Models\WalletCashDocument;
use App\Services\Files\FileStorageService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class WalletCashDocumentService
{
    public function __construct(private readonly WalletService $wallets, private readonly FileStorageService $files, private readonly PermissionChecker $permissions) {}

    public function post(Building $building, User $actor, array $data, ?UploadedFile $image = null): WalletCashDocument
    {
        abort_unless($this->permissions->allows($actor, 'wallet-cash.manage', $building), 403);
        if (! in_array($data['direction'], ['receipt', 'payment'], true) || (int) $data['amount'] <= 0) {
            throw ValidationException::withMessages(['amount' => 'نوع سند و مبلغ باید معتبر باشند.']);
        }
        $file = null;
        try {
            return DB::transaction(function () use ($building, $actor, $data, $image, &$file) {
                // Serialize document numbers within a building, including requests for different units.
                Building::whereKey($building->id)->lockForUpdate()->firstOrFail();
                $unit = empty($data['unit_id']) ? null : Unit::whereHas('floor.block', fn ($q) => $q->where('building_id', $building->id))->lockForUpdate()->findOrFail($data['unit_id']);
                $existing = WalletCashDocument::where('uuid', $data['uuid'])->orWhere(fn ($q) => $q->where('building_id', $building->id)->where('direction', $data['direction'])->where('document_number', $data['document_number']))->first();
                if ($existing) {
                    if ((int) $existing->building_id !== (int) $building->id || (int) $existing->unit_id !== (int) $unit?->id || $existing->direction !== $data['direction'] || $existing->amount !== (int) $data['amount'] || $existing->document_number !== $data['document_number']) {
                        throw ValidationException::withMessages(['document_number' => 'این شناسه یا شماره سند قبلاً با اطلاعات دیگری ثبت شده است.']);
                    }

                    return $existing;
                }
                $wallet = $this->wallets->walletFor($unit ?? $building, $building->currency ?: 'IRR');
                $document = WalletCashDocument::create([
                    'uuid' => $data['uuid'], 'building_id' => $building->id, 'unit_id' => $unit?->id,
                    'wallet_id' => $wallet->id, 'direction' => $data['direction'], 'amount' => $data['amount'],
                    'document_number' => $data['document_number'], 'description' => $data['description'] ?? null, 'created_by' => $actor->id,
                ]);
                $description = ($data['direction'] === 'receipt' ? 'دریافت: ' : 'پرداخت: ').$data['document_number'];
                if ($data['direction'] === 'payment') {
                    $this->wallets->lockFunds($wallet, (int) $data['amount']);
                    $transfer = $this->wallets->debitLocked($wallet, (int) $data['amount'], WalletTransferType::Adjustment, 'cash-document:'.$document->id, $document, $actor, $description);
                } else {
                    $transfer = $this->wallets->credit($wallet, (int) $data['amount'], WalletTransferType::Adjustment, 'cash-document:'.$document->id, $document, $actor, $description);
                }
                if ($image) {
                    $file = $this->files->store($image, $actor, $building, ['category' => 'receipt', 'purpose' => 'wallet_cash_document:'.$document->id]);
                }
                $document->update(['wallet_transfer_id' => $transfer->id, 'file_id' => $file?->id]);

                return $document;
            });
        } catch (\Throwable $exception) {
            if ($file && ! File::whereKey($file->id)->exists()) {
                Storage::disk($file->disk)->delete($file->path);
            }
            throw $exception;
        }
    }
}
