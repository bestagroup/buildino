<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreChargePeriodRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'title'=>['required','string','max:255'],
            'period_start'=>['required','date'],
            'period_end'=>['required','date','after_or_equal:period_start'],
            'due_date'=>['required','date','after_or_equal:period_end'],
        ];
    }
}
