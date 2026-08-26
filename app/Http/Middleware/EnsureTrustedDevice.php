<?php

namespace App\Http\Middleware;

use App\Models\ApiDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrustedDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = ApiDevice::query()
            ->whereKey($request->header('X-Device-ID'))
            ->where('user_id', $request->user()->id)
            ->where('status', 'trusted')
            ->whereNull('revoked_at')
            ->first();

        abort_unless($device, 403, 'Dispositivo não confiável ou não vinculado.');
        $request->attributes->set('api_device', $device);

        return $next($request);
    }
}
