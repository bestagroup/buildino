<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreUnitInvoiceRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'start_charge_period_id'=>['sometimes','required','integer','exists:charge_periods,id'],
            'period_installment_count'=>['required_with:start_charge_period_id','integer','between:1,5'],
            'issue_date'=>['required_without:start_charge_period_id','date'],
            'due_date'=>['required_without:start_charge_period_id','date','after_or_equal:issue_date'],
            'period_start'=>['nullable','date'],
            'period_end'=>['nullable','date','after_or_equal:period_start'],
            'discount_amount'=>['sometimes','integer','min:0'],
            'penalty_amount'=>['sometimes','integer','min:0'],
            'description'=>['nullable','string','max:5000'],
            'items'=>['required','array','min:1'],
            'items.*.charge_item_id'=>['nullable','integer','exists:charge_items,id'],
            'items.*.title'=>['required','string','max:255'],
            'items.*.description'=>['nullable','string','max:2000'],
            'items.*.quantity'=>['sometimes','integer','min:1'],
            'items.*.unit_amount'=>['required','integer','between:-9007199254740991,9007199254740991'],
            'items.*.metadata'=>['nullable','array'],
        ];
    }
}
