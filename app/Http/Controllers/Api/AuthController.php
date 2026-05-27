<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * @unauthenticated
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'phone' => $request->string('phone')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
            'role' => $request->string('role')->toString(),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'User registered successfully.', Response::HTTP_CREATED);
    }

    /**
     * @unauthenticated
     */
    public function login(LoginRequest $request)
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'Login successful.');
    }

    public function logout()
    {
        auth()->user()?->currentAccessToken()?->delete();

        return $this->successResponse(null, 'Logout successful.');
    }

    /**
     * @unauthenticated
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $status = Password::sendResetLink([
            'email' => $request->string('email')->toString(),
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->errorResponse(
                __($status),
                ['email' => [__($status)]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return $this->successResponse(null, __($status));
    }

    /**
     * @unauthenticated
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->errorResponse(
                __($status),
                ['token' => [__($status)]],
                Response::HTTP_BAD_REQUEST
            );
        }

        return $this->successResponse(null, __($status));
    }
}
