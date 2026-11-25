<?php

namespace App\Modules\Localization\Http\Resources;

use App\Modules\Localization\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Country
 */
class CountryResource extends JsonResource
{
    /**
     * @return array{id: int, iso2: string, name: string, flag: string, phone_code: string|null, currency_code: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iso2' => $this->iso2,
            'name' => $this->name,
            'flag' => $this->flag,
            'phone_code' => $this->phone_code,
            'currency_code' => $this->currency_code,
        ];
    }
}
