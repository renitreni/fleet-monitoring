<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreTripLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('trip'));

        return true;
    }

    public function rules(): array
    {
        return [
            'tracking_token' => ['required', 'uuid'],
            'latitude' => ['required', 'numeric', 'between:-85,85'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100000'],
            'speed' => ['nullable', 'numeric', 'min:0', 'max:400'],
            'recorded_at' => ['required', 'date'],
        ];
    }
}
