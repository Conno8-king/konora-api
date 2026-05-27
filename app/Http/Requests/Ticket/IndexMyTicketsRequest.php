<?php

namespace App\Http\Requests\Ticket;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexMyTicketsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', Rule::in(['upcoming', 'past', 'cancelled'])],
        ];
    }
}
