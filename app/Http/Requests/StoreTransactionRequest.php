<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_in_cents' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', 'in:credit,debit'],
            'description' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }
}