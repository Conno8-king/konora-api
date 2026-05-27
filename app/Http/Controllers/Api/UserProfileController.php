<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class UserProfileController extends Controller
{
    public function show(): ProfileResource
    {
        return new ProfileResource(auth()->user());
    }

    public function update(UpdateProfileRequest $request): ProfileResource
    {
        $user = $request->user();
        $user->update([
            'name' => $request->string('name')->toString(),
            'phone' => $request->string('phone')->toString(),
        ]);

        return new ProfileResource($user->fresh());
    }

    public function updatePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->update([
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }
}
