<?php

namespace App\Http\Requests\TicketTier;

use App\Http\Requests\ApiFormRequest;
use App\Support\EventCatalog;
use Illuminate\Validation\Rule;

class UpdateTicketTierRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', Rule::in(EventCatalog::TIER_NAMES)],
            'custom_name' => ['required_if:name,custom', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0.01'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'sales_start' => ['nullable', 'date'],
            'sales_end' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $tier = $this->route('tier');
            $start = $this->has('sales_start')
                ? $this->input('sales_start')
                : ($tier?->sales_start ?? null);
            $end = $this->has('sales_end')
                ? $this->input('sales_end')
                : ($tier?->sales_end ?? null);

            if ($start && $end && strtotime((string) $end) < strtotime((string) $start)) {
                $validator->errors()->add('sales_end', 'The sales end must be a date after or equal to sales start.');
            }
        });
    }
}
