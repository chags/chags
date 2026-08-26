<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\InAppMessageRequest;
use App\Models\InAppMessage;
use App\Models\User;
use App\Services\InAppMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InAppMessageController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('hr/messages/index', [
            'messages' => InAppMessage::query()
                ->where('type', 'administrative')
                ->with('creator:id,name')
                ->withCount(['recipients', 'recipients as read_count' => fn ($query) => $query->whereNotNull('read_at')])
                ->latest()
                ->paginate(20),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'abilities' => [
                'manage' => $request->user()->can('messages.manage'),
                'send' => $request->user()->can('messages.send'),
                'archive' => $request->user()->can('messages.archive'),
            ],
        ]);
    }

    public function store(InAppMessageRequest $request, InAppMessageService $service): JsonResponse
    {
        $data = $request->validated();
        $message = InAppMessage::query()->create([
            'type' => 'administrative',
            'status' => ($data['scheduled_at'] ?? null) ? 'scheduled' : 'draft',
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $data['audience'],
            'created_by' => $request->user()->id,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        if ($message->audience === 'user') {
            $message->recipients()->create(['user_id' => $data['user_id']]);
        }

        if (! $message->scheduled_at && $request->boolean('send_now')) {
            abort_unless($request->user()->can('messages.send'), 403);
            $service->publish($message, array_values($message->recipients()->pluck('user_id')->map(fn ($id): int => (int) $id)->all()));
        }

        return response()->json(['message' => 'Mensagem salva com sucesso.'], 201);
    }

    public function update(InAppMessageRequest $request, InAppMessage $message): JsonResponse
    {
        abort_unless($message->type === 'administrative' && in_array($message->status, ['draft', 'scheduled'], true), 422, 'Somente rascunhos ou agendamentos podem ser alterados.');
        $data = $request->validated();
        $message->update([
            'status' => ($data['scheduled_at'] ?? null) ? 'scheduled' : 'draft',
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $data['audience'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);
        $message->recipients()->delete();
        if ($message->audience === 'user') {
            $message->recipients()->create(['user_id' => $data['user_id']]);
        }

        return response()->json(['message' => 'Mensagem atualizada com sucesso.']);
    }

    public function destroy(Request $request, InAppMessage $message): JsonResponse
    {
        abort_unless($request->user()->can('messages.manage'), 403);
        abort_unless($message->type === 'administrative' && $message->status === 'draft', 422, 'Somente rascunhos podem ser excluídos.');
        $message->delete();

        return response()->json(['message' => 'Rascunho excluído.']);
    }

    public function send(Request $request, InAppMessage $message, InAppMessageService $service): JsonResponse
    {
        abort_unless($request->user()->can('messages.send'), 403);
        abort_unless($message->type === 'administrative' && in_array($message->status, ['draft', 'scheduled'], true), 422, 'A mensagem não pode ser enviada.');
        $service->publish($message, array_values($message->recipients()->pluck('user_id')->map(fn ($id): int => (int) $id)->all()));

        return response()->json(['message' => 'Mensagem enviada com sucesso.']);
    }

    public function archive(Request $request, InAppMessage $message): JsonResponse
    {
        abort_unless($request->user()->can('messages.archive'), 403);
        abort_unless($message->status === 'sent', 422, 'Somente mensagens enviadas podem ser arquivadas.');
        $message->update(['status' => 'archived']);

        return response()->json(['message' => 'Mensagem arquivada.']);
    }
}
