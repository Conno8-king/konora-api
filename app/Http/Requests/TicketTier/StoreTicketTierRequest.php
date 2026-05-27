<?php

namespace App\Http\Requests\TicketTier;

use App\Http\Requests\ApiFormRequest;
use App\Support\EventCatalog;
use Illuminate\Validation\Rule;

class StoreTicketTierRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', Rule::in(EventCatalog::TIER_NAMES)],
            'custom_name' => ['required_if:name,custom', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'capacity' => ['required', 'integer', 'min:1'],
            'sales_start' => ['nullable', 'date'],
            'sales_end' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $start = $this->input('sales_start');
            $end = $this->input('sales_end');
            if ($start && $end && strtotime((string) $end) < strtotime((string) $start)) {
                $validator->errors()->add('sales_end', 'The sales end must be a date after or equal to sales start.');
            }
        });
    }
}
