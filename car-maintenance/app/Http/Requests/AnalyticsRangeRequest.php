<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AnalyticsRangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAnalytics') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['7', '30', '90', 'custom'])],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->input('period') !== 'custom') {
                    return;
                }

                $from = CarbonImmutable::createFromFormat('!Y-m-d', $this->string('from')->toString());
                $to = CarbonImmutable::createFromFormat('!Y-m-d', $this->string('to')->toString());

                if ($from->diffInDays($to) > 365) {
                    $validator->errors()->add('to', 'The analytics range cannot exceed 365 days.');
                }
            },
        ];
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, period: string}
     */
    public function range(): array
    {
        $period = $this->string('period', '30')->toString();

        if ($period === 'custom') {
            return [
                'from' => CarbonImmutable::createFromFormat('!Y-m-d', $this->string('from')->toString())->startOfDay(),
                'to' => CarbonImmutable::createFromFormat('!Y-m-d', $this->string('to')->toString())->endOfDay(),
                'period' => $period,
            ];
        }

        $days = (int) $period;

        return [
            'from' => CarbonImmutable::now()->startOfDay()->subDays($days - 1),
            'to' => CarbonImmutable::now()->endOfDay(),
            'period' => $period,
        ];
    }
}
