<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProviderBankAccountRequest;
use App\Http\Resources\V1\ProviderBankAccountResource;
use App\Models\ProviderBankAccount;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ProviderBankAccountController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        return ProviderBankAccountResource::collection(
            ProviderBankAccount::query()
                ->where(
                    'user_id',
                    $request->user()->getKey()
                )
                ->latest('id')
                ->paginate(20)
        );
    }

    public function store(
        StoreProviderBankAccountRequest $request
    ) {
        $account = DB::transaction(function () use (
            $request
        ): ProviderBankAccount {
            $user = $request->user();
            $data = $request->validated();

            if (
                (bool) ($data['is_default'] ?? false)
            ) {
                ProviderBankAccount::query()
                    ->where(
                        'user_id',
                        $user->getKey()
                    )
                    ->update([
                        'is_default' => false,
                    ]);
            }

            return ProviderBankAccount::query()->create([
                'user_id' => $user->getKey(),
                'bank_name' =>
                    $data['bank_name'] ?? null,
                'account_holder_name' =>
                    $data['account_holder_name'],
                'iban' => $data['iban'],
                'account_number' =>
                    $data['account_number'] ?? null,
                'card_number' =>
                    $data['card_number'] ?? null,
                'is_default' =>
                    (bool) ($data['is_default'] ?? false),
                'is_verified' => false,
                'is_active' => true,
            ])->refresh();
        }, 3);

        return (new ProviderBankAccountResource(
            $account
        ))
            ->response()
            ->setStatusCode(201);
    }

    public function verify(
        Request $request,
        ProviderBankAccount $providerBankAccount,
        PermissionChecker $permissions
    ): ProviderBankAccountResource {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'provider-bank-accounts.verify',
                null
            ),
            403
        );

        if (! $providerBankAccount->is_verified) {
            $providerBankAccount->update([
                'is_verified' => true,
                'verified_by' =>
                    $request->user()->getKey(),
                'verified_at' => now(),
            ]);
        }

        return new ProviderBankAccountResource(
            $providerBankAccount->refresh()
        );
    }
}
