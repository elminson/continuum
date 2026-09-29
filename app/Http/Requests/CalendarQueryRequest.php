<?php

namespace App\Http\Requests;

use App\Enums\CalendarPeriod;
use App\Enums\HarmonizationMethod;
use App\Enums\ReferenceSensor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', Rule::enum(CalendarPeriod::class)],
            'method' => ['sometimes', Rule::enum(HarmonizationMethod::class)],
            'reference' => ['sometimes', Rule::enum(ReferenceSensor::class)],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * Explicit period, or a sensible default: daily for short records,
     * monthly once the record spans more than four months.
     */
    public function period(?int $recordDays = null): CalendarPeriod
    {
        $value = $this->validated('period');

        if ($value !== null) {
            return CalendarPeriod::from($value);
        }

        return $recordDays !== null && $recordDays <= 120 ? CalendarPeriod::Day : CalendarPeriod::Month;
    }

    public function harmonizationMethod(): HarmonizationMethod
    {
        return HarmonizationMethod::from($this->validated('method', HarmonizationMethod::Ratio->value));
    }

    public function reference(): ReferenceSensor
    {
        return ReferenceSensor::from($this->validated('reference', ReferenceSensor::Modis->value));
    }

    public function from(): ?string
    {
        return $this->validated('from');
    }

    public function to(): ?string
    {
        return $this->validated('to');
    }
}
