<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConfirmPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'check_id' => ['required', 'string'],
            'partner_transaction_id' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'], // tiyin
            'gateway' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Idempotency-Key header is mandatory on this write. */
    public function passedValidation(): void
    {
        if (! $this->hasHeader('Idempotency-Key')) {
            throw new HttpResponseException(response()->json([
                'status' => 'error',
                'error' => ['code' => 'idempotency_key_required', 'message' => 'The Idempotency-Key header is required.'],
            ], 428));
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'error' => ['code' => 'validation_failed', 'message' => $validator->errors()->first(), 'fields' => $validator->errors()->toArray()],
        ], 422));
    }
}
