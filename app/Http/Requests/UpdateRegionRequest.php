<?php

namespace App\Http\Requests;

use App\Models\Region;

class UpdateRegionRequest extends StoreRegionRequest
{
    public function authorize(): bool
    {
        $region = $this->route('region');

        return $region instanceof Region && $this->user()?->can('update', $region) === true;
    }
}
