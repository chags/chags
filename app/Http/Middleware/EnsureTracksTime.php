<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTracksTime
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->tracks_time, 403, 'Seu usuário não está configurado para registrar ponto.');

        return $next($request);
    }
}
