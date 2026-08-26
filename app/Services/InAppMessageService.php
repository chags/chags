<?php

namespace App\Services;

use App\Models\InAppMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InAppMessageService
{
    /** @param list<int> $userIds */
    public function publish(InAppMessage $message, array $userIds = []): InAppMessage
    {
        return DB::transaction(function () use ($message, $userIds): InAppMessage {
            $ids = $message->audience === 'all'
                ? User::query()->pluck('id')->all()
                : $userIds;

            foreach (array_unique($ids) as $userId) {
                $message->recipients()->firstOrCreate(['user_id' => $userId]);
            }

            $message->update([
                'status' => 'sent',
                'published_at' => $message->published_at ?? now(),
            ]);

            return $message->refresh();
        });
    }
}
