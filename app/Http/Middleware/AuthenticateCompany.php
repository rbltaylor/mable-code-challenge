<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (blank($apiKey)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $company = Company::where('api_key_hash', Company::digestApiKey($apiKey))->first();

        if ($company === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('company', $company);

        return $next($request);
    }
}
