<?php
namespace App\Http\Requests;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'amount'=>['required','integer','min:1'],
            'method'=>['required',Rule::enum(PaymentMethod::class)],
            'description'=>['nullable','string','max:5000'],
        ];
    }
}
