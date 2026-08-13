<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class UpdateChargePeriodRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'title'=>['sometimes','required','string','max:255'],
            'period_start'=>['sometimes','date'],
            'period_end'=>['sometimes','date'],
            'due_date'=>['sometimes','date'],
        ];
    }
}
