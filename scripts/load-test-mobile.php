<?php

declare(strict_types=1);

use App\Models\ApiDevice;
use App\Models\User;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$total = max(1, (int) ($argv[1] ?? 1000));
$concurrency = max(1, min($total, (int) ($argv[2] ?? 50)));
$baseUrl = rtrim($argv[3] ?? 'http://nginx/api/v1', '/');
$identityCount = max(1, min($total, (int) ($argv[4] ?? 1)));
$scenario = $argv[5] ?? 'status';
if (! in_array($scenario, ['status', 'startup', 'punch'], true)) {
    throw new InvalidArgumentException('O cenário deve ser status, startup ou punch.');
}
$marker = 'load-test-'.Illuminate\Support\Str::uuid();

try {
    $identities = [];
    $password = Illuminate\Support\Facades\Hash::make(Illuminate\Support\Str::random(40));
    for ($identity = 0; $identity < $identityCount; $identity++) {
        $identityMarker = $marker.'-'.$identity;
        $user = User::query()->create([
            'name' => 'Teste local de carga '.$identity,
            'email' => $identityMarker.'@example.test',
            'workos_id' => $identityMarker,
            'avatar' => '',
            'password' => $password,
            'tracks_time' => true,
            'first_app_access_completed_at' => now(),
        ]);
        $device = ApiDevice::query()->create([
            'user_id' => $user->id,
            'installation_id' => (string) Illuminate\Support\Str::uuid(),
            'public_key' => 'load-test',
            'key_fingerprint' => hash('sha256', $identityMarker),
            'platform' => 'android',
            'app_version' => 'load-test',
            'app_build' => '1',
            'package_name' => 'com.chags.loadtest',
            'attestation_provider' => 'fake',
            'attestation_status' => 'verified',
            'risk_level' => 'low',
            'status' => 'trusted',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $identities[] = [
            'token' => auth('api')->claims(['app_unlocked' => true])->fromUser($user),
            'device_id' => $device->id,
        ];
    }

    $latencies = [];
    $statuses = [];
    $curlErrors = [];
    $errors = 0;
    $startedAt = hrtime(true);

    for ($offset = 0; $offset < $total; $offset += $concurrency) {
        $batchSize = min($concurrency, $total - $offset);
        $multi = curl_multi_init();
        $handles = [];

        for ($index = 0; $index < $batchSize; $index++) {
            $requestNumber = $offset + $index;
            $identityIndex = $scenario === 'startup'
                ? intdiv($requestNumber, 2) % $identityCount
                : $requestNumber % $identityCount;
            $identity = $identities[$identityIndex];
            $path = $scenario === 'startup' && $requestNumber % 2 === 0
                ? '/me'
                : ($scenario === 'punch' ? '/time-punch' : '/time-punch/status');
            $handle = curl_init($baseUrl.$path);
            $requestHeaders = [
                'Accept: application/json',
                'Authorization: Bearer '.$identity['token'],
                'X-Device-ID: '.$identity['device_id'],
            ];
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
            ];
            if ($scenario === 'punch') {
                $requestHeaders[] = 'Content-Type: application/json';
                $requestHeaders[] = 'Idempotency-Key: load-punch-'.$marker.'-'.$requestNumber;
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = json_encode(['expected_type' => 'clock_in']);
            }
            $options[CURLOPT_HTTPHEADER] = $requestHeaders;
            curl_setopt_array($handle, $options);
            curl_multi_add_handle($multi, $handle);
            $handles[] = $handle;
        }

        do {
            $result = curl_multi_exec($multi, $running);
            if ($result !== CURLM_OK) {
                throw new RuntimeException('Falha interna no curl_multi: '.curl_multi_strerror($result));
            }
            if ($running) {
                $selected = curl_multi_select($multi, 1.0);
                if ($selected === -1) {
                    usleep(1_000);
                }
            }
        } while ($running);

        foreach ($handles as $handle) {
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $curlErrorNumber = curl_errno($handle);
            if ($curlErrorNumber !== 0) {
                $curlErrorKey = $curlErrorNumber.': '.curl_error($handle);
                $curlErrors[$curlErrorKey] = ($curlErrors[$curlErrorKey] ?? 0) + 1;
            }
            $latencies[] = curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000;
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
            $expectedStatus = $scenario === 'punch' ? 201 : 200;
            if ($status !== $expectedStatus || $curlErrorNumber !== 0) {
                $errors++;
            }
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);
    }

    sort($latencies);
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;
    $percentile = static function (array $values, float $percentile): float {
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;

        return $values[max(0, min(count($values) - 1, $index))];
    };

    echo json_encode([
        'scenario' => $scenario,
        'endpoint' => $scenario === 'startup'
            ? '/api/v1/me + /api/v1/time-punch/status'
            : ($scenario === 'punch' ? 'POST /api/v1/time-punch' : '/api/v1/time-punch/status'),
        'requests' => $total,
        'concurrency' => $concurrency,
        'identities' => $identityCount,
        'duration_seconds' => round($elapsedSeconds, 3),
        'requests_per_second' => round($total / $elapsedSeconds, 2),
        'latency_ms' => [
            'min' => round(min($latencies), 2),
            'p50' => round($percentile($latencies, 50), 2),
            'p95' => round($percentile($latencies, 95), 2),
            'p99' => round($percentile($latencies, 99), 2),
            'max' => round(max($latencies), 2),
        ],
        'http_statuses' => $statuses,
        'curl_errors' => $curlErrors,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} finally {
    User::query()->where('email', 'like', $marker.'-%@example.test')->delete();
}
