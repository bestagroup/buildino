<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class UpdateUnitInvoiceRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'issue_date'=>['sometimes','date'],
            'due_date'=>['sometimes','date'],
            'period_start'=>['sometimes','nullable','date'],
            'period_end'=>['sometimes','nullable','date'],
            'discount_amount'=>['sometimes','integer','min:0'],
            'penalty_amount'=>['sometimes','integer','min:0'],
            'description'=>['sometimes','nullable','string','max:5000'],
            'items'=>['sometimes','array','min:1'],
            'items.*.charge_item_id'=>['nullable','integer','exists:charge_items,id'],
            'items.*.title'=>['required_with:items','string','max:255'],
            'items.*.description'=>['nullable','string','max:2000'],
            'items.*.quantity'=>['sometimes','integer','min:1'],
            'items.*.unit_amount'=>['required_with:items','integer','min:0'],
            'items.*.metadata'=>['nullable','array'],
        ];
    }
}
