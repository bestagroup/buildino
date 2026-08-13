<?php
namespace App\Http\Requests;
use App\Enums\FinancialTransactionType;
use App\Enums\LedgerEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreFinancialTransactionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'transaction_type'=>['required',Rule::enum(FinancialTransactionType::class)],
            'occurred_at'=>['nullable','date','before_or_equal:now'],
            'description'=>['nullable','string','max:5000'],
            'entries'=>['required','array','min:2'],
            'entries.*.financial_account_id'=>['required','integer','exists:financial_accounts,id'],
            'entries.*.entry_type'=>['required',Rule::enum(LedgerEntryType::class)],
            'entries.*.amount'=>['required','integer','min:1'],
            'entries.*.currency'=>['nullable','string','size:3'],
            'entries.*.metadata'=>['nullable','array'],
        ];
    }
}
