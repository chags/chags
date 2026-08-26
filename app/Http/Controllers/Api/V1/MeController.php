<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'whatsapp_phone' => $this->maskPhone($user->whatsapp_phone),
            'tracks_time' => $user->tracks_time,
            'first_access_completed' => (bool) $user->first_app_access_completed_at,
        ]]);
    }

    private function maskPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        return substr($phone, 0, 5).'*****'.substr($phone, -4);
    }
}
