<?php

namespace App\Services\VirtualOffice;

use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TimePunchService
{
    private const TYPES = ['clock_in', 'break_start', 'break_end', 'clock_out'];

    public function __construct(private readonly TimePunchDecisionService $decisions) {}

    public function status(User $user): array
    {
        $localNow = CarbonImmutable::now(config('app.business_timezone'));
        $entries = $user->timeEntries()
            ->whereBetween('recorded_at', [$localNow->startOfDay()->utc(), $localNow->endOfDay()->utc()])
            ->orderBy('recorded_at')
            ->get();
        $registeredTypes = $entries->whereNotIn('status', ['cancelled'])->pluck('type');

        return [
            'serverTime' => $localNow->toIso8601String(),
            'timezone' => config('app.business_timezone'),
            'nextType' => collect(self::TYPES)->first(fn (string $type) => ! $registeredTypes->contains($type)),
            'entries' => $entries->map(fn (TimeEntry $entry) => [
                'id' => $entry->id,
                'type' => $entry->type,
                'time' => $this->localTime($entry->recorded_at)->format('H:i'),
                'status' => $entry->status,
                'reason' => $entry->reason,
            ])->values()->all(),
            'pendingAdjustments' => $user->timeAdjustmentRequests()
                ->where('status', 'pending')->latest()->get()
                ->flatMap(fn ($adjustment) => collect($adjustment->requested_entries)->map(fn (array $entry) => [
                    'id' => $adjustment->id,
                    'date' => $adjustment->work_date->format('Y-m-d'),
                    'type' => $entry['type'],
                    'time' => $entry['time'],
                ]))->values()->all(),
        ];
    }

    public function punch(User $user, Request $request, string $source, ?string $expectedType = null): array
    {
        $user = User::query()->lockForUpdate()->findOrFail($user->id);
        $status = $this->status($user);
        if ($status['nextType'] === null) {
            throw new UnprocessableEntityHttpException('Todos os registros de hoje já foram realizados.');
        }
        if ($expectedType !== null && $status['nextType'] !== $expectedType) {
            throw new ConflictHttpException('O estado da jornada mudou. Atualize as batidas antes de tentar novamente.');
        }

        $recordedAt = CarbonImmutable::now();
        $entry = TimeEntry::query()->create([
            'user_id' => $user->id,
            'recorded_at' => $recordedAt,
            'work_date' => $recordedAt->setTimezone(config('app.business_timezone'))->toDateString(),
            'type' => $status['nextType'],
            'source' => $source,
            ...$this->decisions->decide($user, $status['nextType'], $recordedAt, $source),
            'ip_address' => $request->ip(),
            'created_by' => $user->id,
        ]);

        return [
            'message' => 'Ponto registrado com sucesso!',
            'registeredType' => $entry->type,
            'registeredAt' => $this->localTime($entry->recorded_at)->format('H:i:s'),
            'status' => $entry->status,
            'reason' => $entry->reason,
            ...$this->status($user),
        ];
    }

    private function localTime(CarbonInterface $dateTime): CarbonInterface
    {
        return $dateTime->setTimezone(config('app.business_timezone'));
    }
}
