<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(auth('api')->payload()->get('app_unlocked') === true, 403, 'O aplicativo ainda não foi liberado.');

        return $next($request);
    }
}
