<?php

use App\Models\InAppMessage;
use App\Services\InAppMessageService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (InAppMessageService $service): void {
    InAppMessage::query()
        ->where('status', 'scheduled')
        ->where('scheduled_at', '<=', now())
        ->each(fn (InAppMessage $message) => $service->publish(
            $message,
            array_values($message->recipients()->pluck('user_id')->map(fn ($id): int => (int) $id)->all()),
        ));
})->everyMinute()->name('publish-in-app-messages')->withoutOverlapping();

Schedule::call(function (): void {
    InAppMessage::query()
        ->whereNotNull('sensitive_payload')
        ->where('expires_at', '<=', now())
        ->update(['sensitive_payload' => null]);
})->everyFiveMinutes()->name('purge-expired-message-secrets')->withoutOverlapping();
