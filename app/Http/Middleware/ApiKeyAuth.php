<?php

namespace App\Http\Middleware;

use App\Http\Responses\ProblemResponse;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    /**
     * @param  array<string>  $kinds  optional list of allowed key kinds (server / client)
     */
    public function handle(Request $request, Closure $next, ...$kinds): Response
    {
        $token = $this->extractBearer($request);

        // EventSource clients (SSE) can't set Authorization headers; they pass
        // the key via ?key= and rely on the calling endpoint to opt in.
        if ($token === null) {
            $token = $request->query('key');
        }

        if (! is_string($token) || $token === '') {
            return new ProblemResponse(
                status: 401,
                title: 'Authentication required',
                detail: 'Provide an Authorization: Bearer pn_srv_... / pn_clt_... header.',
            );
        }

        $apiKey = ApiKey::findByPlaintext($token);

        if ($apiKey === null) {
            return new ProblemResponse(
                status: 401,
                title: 'Authentication required',
                detail: 'The API key is invalid or has been revoked.',
            );
        }

        if ($kinds !== [] && ! in_array($apiKey->kind, $kinds, true)) {
            return new ProblemResponse(
                status: 403,
                title: 'Forbidden',
                detail: 'This endpoint requires a '.implode(' or ', $kinds).' key.',
            );
        }

        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('workspace', $apiKey->workspace);

        $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }

    private function extractBearer(Request $request): ?string
    {
        $header = $request->headers->get('Authorization', '');

        if (! is_string($header) || $header === '') {
            return null;
        }

        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token === '' ? null : $token;
    }
}
