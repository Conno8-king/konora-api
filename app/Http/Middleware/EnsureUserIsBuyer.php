<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsBuyer
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'errors' => (object) [],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
