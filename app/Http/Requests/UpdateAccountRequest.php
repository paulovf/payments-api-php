<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'holder' => ['sometimes', 'required', 'string', 'max:120'],
            'balance_in_cents' => ['prohibited'],
        ];
    }
}