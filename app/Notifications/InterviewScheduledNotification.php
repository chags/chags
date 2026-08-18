<?php

namespace App\Notifications;

use App\Models\InterviewSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InterviewScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public int $scheduleId)
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $schedule = InterviewSchedule::with(['application.job', 'stage'])->findOrFail($this->scheduleId);
        $date = $schedule->starts_at->setTimezone($schedule->timezone)->format('d/m/Y \à\s H:i');

        return (new MailMessage)->subject("Entrevista agendada — {$schedule->application->job->title}")->greeting("Olá, {$notifiable->name}!")->line("Sua {$schedule->stage->public_name} foi agendada.")->line("Data e horário: {$date} ({$schedule->timezone})")->action('Ver e confirmar entrevista', route('candidate.applications.show', $schedule->application));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
