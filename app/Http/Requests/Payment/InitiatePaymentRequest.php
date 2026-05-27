<?php

namespace App\Http\Requests\Payment;

use App\Http\Requests\ApiFormRequest;

class InitiatePaymentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'tier_id' => ['required', 'integer', 'exists:ticket_tiers,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'attendee_name' => ['required', 'string', 'max:255'],
            'attendee_email' => ['required', 'email', 'max:255'],
            'attendee_phone' => ['required', 'string', 'max:32'],
        ];
    }
}
