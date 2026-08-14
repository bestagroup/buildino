<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:190'],
            'channel' => ['required', Rule::in(['sms', 'email'])],
            'code' => ['required', 'digits_between:4,8'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
