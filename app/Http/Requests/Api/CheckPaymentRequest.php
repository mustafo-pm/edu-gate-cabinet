<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CheckPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'institution_id' => ['required', 'integer', 'exists:merchants,id'],
            'student_id_number' => ['required', 'string', 'max:50'],
            'amount' => ['nullable', 'integer', 'min:1'], // tiyin
        ];
    }
}
