<?php

namespace App\Http\Requests\Event;

use App\Http\Requests\ApiFormRequest;
use App\Support\EventCatalog;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends ApiFormRequest
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
        $routeEvent = $this->route('event');
        $eventId = is_object($routeEvent) && method_exists($routeEvent, 'getKey')
            ? $routeEvent->getKey()
            : $routeEvent;

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'category' => ['sometimes', Rule::in(EventCatalog::CATEGORIES)],
            'custom_category' => ['required_if:category,custom', 'nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i:s'],
            'end_time' => ['sometimes', 'date_format:H:i:s'],
            'venue_name' => ['sometimes', 'string', 'max:255'],
            'venue_address' => ['sometimes', 'string', 'max:255'],
            'banner' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'visibility' => ['sometimes', Rule::in(['public', 'private'])],
            'ticket_tiers' => ['sometimes', 'array'],
            'ticket_tiers.*.id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('ticket_tiers', 'id')->where('event_id', $eventId),
            ],
            'ticket_tiers.*.name' => ['required_with:ticket_tiers', Rule::in(EventCatalog::TIER_NAMES)],
            'ticket_tiers.*.custom_name' => ['nullable', 'string', 'max:255'],
            'ticket_tiers.*.description' => ['nullable', 'string'],
            'ticket_tiers.*.price' => ['required_with:ticket_tiers', 'numeric', 'min:0.01'],
            'ticket_tiers.*.capacity' => ['required_with:ticket_tiers', 'integer', 'min:1'],
            'ticket_tiers.*.sales_start' => ['nullable', 'date'],
            'ticket_tiers.*.sales_end' => ['nullable', 'date'],
            'delete_tier_ids' => ['sometimes', 'array'],
            'delete_tier_ids.*' => [
                'integer',
                Rule::exists('ticket_tiers', 'id')->where('event_id', $eventId),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $routeEvent = $this->route('event');
            if ($routeEvent && ($this->has('start_time') || $this->has('end_time'))) {
                $startRaw = $this->input('start_time', $routeEvent->start_time ?? null);
                $endRaw = $this->input('end_time', $routeEvent->end_time ?? null);
                if ($startRaw && $endRaw) {
                    $start = is_string($startRaw) ? $startRaw : (string) $startRaw;
                    $end = is_string($endRaw) ? $endRaw : (string) $endRaw;
                    if (strtotime($end) <= strtotime($start)) {
                        $validator->errors()->add('end_time', 'The end time must be after the start time.');
                    }
                }
            }

            $tiers = $this->input('ticket_tiers');
            if (! is_array($tiers)) {
                return;
            }
            foreach ($tiers as $index => $tier) {
                if (! is_array($tier)) {
                    continue;
                }
                if (($tier['name'] ?? '') === 'custom' && empty($tier['custom_name'])) {
                    $validator->errors()->add(
                        "ticket_tiers.{$index}.custom_name",
                        'The custom name field is required when name is custom.'
                    );
                }
                $start = $tier['sales_start'] ?? null;
                $end = $tier['sales_end'] ?? null;
                if ($start && $end && strtotime((string) $end) < strtotime((string) $start)) {
                    $validator->errors()->add(
                        "ticket_tiers.{$index}.sales_end",
                        'The sales end must be a date after or equal to sales start.'
                    );
                }
            }
        });
    }
}
