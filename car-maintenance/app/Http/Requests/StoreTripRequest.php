<?php

namespace App\Http\Requests;

use App\Models\Trip;
use App\Services\RouteProgress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Trip::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'route_points' => ['required', 'array', 'list', 'min:2', 'max:2000'],
            'route_points.*' => ['required', 'array:latitude,longitude'],
            'route_points.*.latitude' => ['required', 'numeric', 'between:-85,85'],
            'route_points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'checkpoints' => ['required', 'array', 'list', 'min:2', 'max:50'],
            'checkpoints.*' => ['required', 'array:name,point_index'],
            'checkpoints.*.name' => ['required', 'string', 'max:80'],
            'checkpoints.*.point_index' => ['required', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $points = $this->input('route_points');
            $indices = array_map('intval', array_column($this->input('checkpoints'), 'point_index'));
            $previous = -1;
            foreach ($indices as $index) {
                if ($index <= $previous || $index >= count($points)) {
                    $validator->errors()->add('checkpoints', 'Checkpoints must follow the route in strict order, without duplicates.');
                }
                $previous = $index;
            }
            if ($indices[0] !== 0 || end($indices) !== count($points) - 1) {
                $validator->errors()->add('checkpoints', 'The first and last route points must be required checkpoints.');
            }
            $geometry = new RouteProgress;
            $total = 0;
            foreach ($points as $index => $point) {
                if ($index === 0) {
                    continue;
                }
                $distance = $geometry->distance($points[$index - 1], $point);
                $total += $distance;
                if ($distance < 10 || $distance > 1000 || abs($points[$index - 1]['longitude'] - $point['longitude']) > 180) {
                    $validator->errors()->add('route_points', 'Place route points 10–1,000 metres apart and follow every road bend.');

                    break;
                }
            }
            if ($total < 100 || $total > 500000) {
                $validator->errors()->add('route_points', 'Routes must be between 100 metres and 500 kilometres.');
            }
            $distances = $geometry->distances($points);
            foreach (array_slice($indices, 1) as $i => $index) {
                if (isset($distances[$index], $distances[$indices[$i]]) && $distances[$index] - $distances[$indices[$i]] < 100) {
                    $validator->errors()->add('checkpoints', 'Required checkpoints must be at least 100 metres apart along the route.');
                }
            }
        }];
    }
}
