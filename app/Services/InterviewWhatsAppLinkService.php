<?php

namespace App\Services;

use App\Models\InterviewSchedule;

class InterviewWhatsAppLinkService
{
    public function make(InterviewSchedule $schedule): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $schedule->application->candidate->phone);
        if (! in_array(strlen($phone), [10, 11, 12, 13], true)) {
            return null;
        }
        if (! str_starts_with($phone, '55')) {
            $phone = '55'.$phone;
        }
        $date = $schedule->starts_at->setTimezone($schedule->timezone)->format('d/m/Y H:i');
        $message = "Olá, {$schedule->application->candidate->name}! Sua {$schedule->stage->public_name} para a vaga {$schedule->application->job->title} foi agendada para {$date}. Acesso/local: ".($schedule->meeting_url ?: $schedule->location).'. Confirme em: '.route('candidate.applications.show', $schedule->application);

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }
}
