<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModerator
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        if (! $request->user()?->isModerator()) {
            throw new AuthorizationException(
                'Moderator access is required.'
            );
        }

        return $next($request);
    }
}
