<?php

namespace App\Http\Requests;

use App\Models\Region;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'west' => ['required', 'numeric', 'between:-180,180'],
            'south' => ['required', 'numeric', 'between:-90,90'],
            'east' => ['required', 'numeric', 'between:-180,180', 'gt:west'],
            'north' => ['required', 'numeric', 'between:-90,90', 'gt:south'],
            'color' => ['nullable', 'hex_color'],
        ];
    }

    /**
     * The shared demo account gets a few small areas, so judges cannot queue
     * a continent-sized backfill.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if ($user === null || ! $user->isDemo() || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $max = (int) config('continuum.demo.max_areas');

                $creating = ! $this->route('region') instanceof Region;

                if ($creating && Region::query()->where('user_id', $user->id)->count() >= $max) {
                    $validator->errors()->add('name', __('The demo account can keep :max areas. Delete one to add another.', ['max' => $max]));
                }

                $degrees = ((float) $this->input('east') - (float) $this->input('west')) * ((float) $this->input('north') - (float) $this->input('south'));

                if ($degrees > (float) config('continuum.demo.max_area_degrees')) {
                    $validator->errors()->add('east', __('Demo areas are limited to about 10° × 10°. Draw a smaller box.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'east.gt' => __('East must be greater than west (areas crossing the antimeridian are not supported).'),
            'north.gt' => __('North must be greater than south.'),
        ];
    }
}
