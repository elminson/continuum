<?php

namespace App\Http\Requests;

use App\Enums\Instrument;
use App\Support\Geo\BoundingBox;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class DetectionQueryRequest extends FormRequest
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
            'bbox' => ['required', 'string', 'regex:/^-?\d+(\.\d+)?(,-?\d+(\.\d+)?){3}$/'],
            'date' => ['required', 'date_format:Y-m-d'],
            'instrument' => ['sometimes', Rule::enum(Instrument::class)],
            'raw' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20000'],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                try {
                    BoundingBox::fromString((string) $this->input('bbox'));
                } catch (InvalidArgumentException $exception) {
                    $validator->errors()->add('bbox', $exception->getMessage());
                }
            },
        ];
    }

    public function box(): BoundingBox
    {
        return BoundingBox::fromString($this->validated('bbox'));
    }

    public function instrument(): ?Instrument
    {
        $value = $this->validated('instrument');

        return $value === null ? null : Instrument::from($value);
    }

    public function limit(): int
    {
        return (int) $this->validated('limit', 5000);
    }

    public function raw(): bool
    {
        return (bool) $this->validated('raw', false);
    }
}
