<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreUnitInvoiceRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'issue_date'=>['required','date'],
            'due_date'=>['required','date','after_or_equal:issue_date'],
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
            'items.*.unit_amount'=>['required','integer','min:0'],
            'items.*.metadata'=>['nullable','array'],
        ];
    }
}
