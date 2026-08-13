<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWalletPayoutRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'building_bank_account_id'=>[
                'required','integer','exists:building_bank_accounts,id'
            ],
            'amount'=>['required','integer','min:1'],
        ];
    }
}
