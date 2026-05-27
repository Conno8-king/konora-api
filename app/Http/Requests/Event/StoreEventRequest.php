<?php

namespace App\Http\Requests\Event;

use App\Http\Requests\ApiFormRequest;
use App\Support\EventCatalog;
use Illuminate\Validation\Rule;

class StoreEventRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['start_time', 'end_time'] as $field) {
            $v = $this->input($field);
            if (is_string($v) && preg_match('/^\d{2}:\d{2}$/', $v)) {
                $this->merge([$field => $v.':00']);
            }
        }

        $raw = $this->input('ticket_tiers');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge(['ticket_tiers' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category' => ['required', Rule::in(EventCatalog::CATEGORIES)],
            'custom_category' => ['required_if:category,custom', 'nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s', 'after:start_time'],
            'venue_name' => ['required', 'string', 'max:255'],
            'venue_address' => ['required', 'string', 'max:255'],
            'banner' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'ticket_tiers' => ['required', 'array', 'min:1'],
            'ticket_tiers.*.name' => ['required', Rule::in(EventCatalog::TIER_NAMES)],
            'ticket_tiers.*.custom_name' => ['nullable', 'string', 'max:255'],
            'ticket_tiers.*.description' => ['nullable', 'string'],
            'ticket_tiers.*.price' => ['required', 'numeric', 'min:0.01'],
            'ticket_tiers.*.capacity' => ['required', 'integer', 'min:1'],
            'ticket_tiers.*.sales_start' => ['nullable', 'date'],
            'ticket_tiers.*.sales_end' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $tiers = $this->input('ticket_tiers', []);
            if (! is_array($tiers)) {
                return;
            }
            foreach ($tiers as $i => $tier) {
                if (! is_array($tier)) {
                    continue;
                }
                if (($tier['name'] ?? '') === 'custom' && empty($tier['custom_name'])) {
                    $validator->errors()->add(
                        "ticket_tiers.{$i}.custom_name",
                        'The custom name field is required when name is custom.'
                    );
                }
                $start = $tier['sales_start'] ?? null;
                $end = $tier['sales_end'] ?? null;
                if ($start && $end && strtotime((string) $end) < strtotime((string) $start)) {
                    $validator->errors()->add(
                        "ticket_tiers.{$i}.sales_end",
                        'The sales end must be a date after or equal to sales start.'
                    );
                }
            }
        });
    }
}
