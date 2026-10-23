<?php

namespace App\Http\Requests\Admin;

use App\Enums\Size;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReadyStockUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'stock' => ['required', 'integer', 'min:0'],
            'size' => ['sometimes', 'nullable', Rule::enum(Size::class)],
        ];
    }
}
