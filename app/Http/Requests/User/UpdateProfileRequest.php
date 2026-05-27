<?php

namespace App\Http\Requests\User;

use App\Http\Requests\ApiFormRequest;

class UpdateProfileRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'digits:11'],
        ];
    }
}
