<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateFederatedSearch
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('meditrack.search_token', '');
        $provided = (string) $request->bearerToken();

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['success' => false, 'message' => 'Invalid federated search token.'], 401);
        }

        return $next($request);
    }
}
