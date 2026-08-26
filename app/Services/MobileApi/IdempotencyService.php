<?php

namespace App\Services\MobileApi;

use App\Models\ApiIdempotencyKey;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class IdempotencyService
{
    /** @return array{body: array, status: int} */
    public function execute(User $user, string $route, string $method, string $key, array $requestData, Closure $operation): array
    {
        return DB::transaction(function () use ($user, $route, $method, $key, $requestData, $operation): array {
            $requestHash = hash('sha256', json_encode($requestData));
            $record = ApiIdempotencyKey::query()->lockForUpdate()->firstOrCreate(
                ['user_id' => $user->id, 'route' => $route, 'idempotency_key' => $key],
                ['method' => $method, 'request_hash' => $requestHash, 'expires_at' => now()->addDay()],
            );

            if (! hash_equals($record->request_hash, $requestHash)) {
                throw new ConflictHttpException('A chave de idempotência já foi usada com outra requisição.');
            }

            if ($record->response_status) {
                return ['body' => $record->response_body, 'status' => $record->response_status];
            }

            $result = $operation();
            $record->update(['response_body' => $result['body'], 'response_status' => $result['status']]);

            return $result;
        });
    }
}
