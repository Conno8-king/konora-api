<?php

namespace App\Http\Requests\Ticket;

use App\Http\Requests\ApiFormRequest;

class ValidateTicketRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
        ];
    }
}
