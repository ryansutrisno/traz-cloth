<?php

namespace App\Http\Requests\Admin;

use App\Enums\CampaignStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignUpdateRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'unique:campaigns,slug,'.$this->route('campaign')->id,
            ],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'cost_per_item' => ['nullable', 'numeric', 'min:0'],
            'quota' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::enum(CampaignStatus::class)],
            'ready_stock_enabled' => ['sometimes', 'boolean'],
            'sizes' => ['required', 'array', 'min:1'],
            'sizes.*.enabled' => ['sometimes', 'boolean'],
            'sizes.*.price_override' => ['nullable', 'numeric', 'min:0'],
            'sizes.*.stock' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
