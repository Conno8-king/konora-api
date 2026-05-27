<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;

class ResetPasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
