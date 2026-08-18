<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\InterviewSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InterviewScheduleController extends Controller
{
    public function respond(Request $request, InterviewSchedule $interview)
    {
        Gate::authorize('respond', $interview);
        $data = $request->validate(['response' => 'required|in:accepted,reschedule_requested', 'reason' => 'nullable|required_if:response,reschedule_requested|string|max:1000']);
        abort_if(in_array($interview->status, ['completed', 'cancelled'], true), 422, 'Esta entrevista não aceita mais respostas.');
        $interview->update(['candidate_response' => $data['response'], 'candidate_responded_at' => now(), 'reschedule_reason' => $data['reason'] ?? null]);

        return back()->with('success', $data['response'] === 'accepted' ? 'Presença confirmada.' : 'Pedido de reagendamento enviado ao RH.');
    }
}
