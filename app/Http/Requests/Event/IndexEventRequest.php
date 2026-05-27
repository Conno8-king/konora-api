<?php

namespace App\Http\Requests\Event;

use App\Http\Requests\ApiFormRequest;
use App\Support\EventCatalog;
use Illuminate\Validation\Rule;

class IndexEventRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', Rule::in(EventCatalog::CATEGORIES)],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'price_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'price_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
