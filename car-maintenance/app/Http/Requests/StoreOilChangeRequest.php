<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOilChangeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'last_changed_at' => ['required', 'date', 'before_or_equal:today'],
            'last_changed_mileage' => ['required', 'integer', 'min:0'],
            'interval_months' => ['required', 'integer', 'min:1', 'max:24'],
            'interval_mileage' => ['required', 'integer', 'min:100', 'max:50000'],
        ];
    }
}
