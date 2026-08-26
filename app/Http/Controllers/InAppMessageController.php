<?php

namespace App\Http\Controllers;

use App\Models\InAppMessageAuditEvent;
use App\Models\InAppMessageRecipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class InAppMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $recipients = $this->query($request)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (InAppMessageRecipient $recipient): array => $this->resource($recipient));

        return Inertia::render('messages/index', [
            'messages' => $recipients,
            'filter' => $request->string('filter')->toString(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $query = $this->query($request);

        return response()->json([
            'unreadCount' => (clone $query)->whereNull('read_at')->count(),
            'messages' => $query->limit(5)->get()->map(fn (InAppMessageRecipient $recipient): array => $this->resource($recipient)),
        ]);
    }

    public function read(Request $request, InAppMessageRecipient $recipient): JsonResponse
    {
        abort_unless($recipient->user_id === $request->user()->id, 404);
        $recipient->update(['read_at' => now()]);

        return response()->json(['message' => 'Mensagem marcada como lida.']);
    }

    public function unread(Request $request, InAppMessageRecipient $recipient): JsonResponse
    {
        abort_unless($recipient->user_id === $request->user()->id, 404);
        $recipient->update(['read_at' => null]);

        return response()->json(['message' => 'Mensagem marcada como não lida.']);
    }

    public function destroy(Request $request, InAppMessageRecipient $recipient): JsonResponse
    {
        abort_unless($recipient->user_id === $request->user()->id, 404);
        abort_if(! $recipient->read_at, 422, 'Marque a mensagem como lida antes de excluí-la.');

        DB::transaction(function () use ($request, $recipient): void {
            $recipient->update(['dismissed_at' => now()]);

            InAppMessageAuditEvent::query()->create([
                'message_id' => $recipient->message_id,
                'recipient_id' => $recipient->id,
                'user_id' => $request->user()->id,
                'event' => 'recipient_deleted',
                'ip_address' => $request->ip(),
            ]);
        });

        Log::notice('Mensagem interna removida pelo destinatário.', [
            'message_id' => $recipient->message_id,
            'recipient_id' => $recipient->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Mensagem excluída da sua caixa.']);
    }

    public function readAll(Request $request): JsonResponse
    {
        InAppMessageRecipient::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->whereHas('message', fn (Builder $query) => $query
                ->whereIn('status', ['sent', 'archived'])
                ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Todas as mensagens foram marcadas como lidas.']);
    }

    /** @return Builder<InAppMessageRecipient> */
    private function query(Request $request): Builder
    {
        return InAppMessageRecipient::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('dismissed_at')
            ->whereHas('message', fn (Builder $query) => $query
                ->whereIn('status', ['sent', 'archived'])
                ->where(fn (Builder $query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
                ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->when($request->string('filter')->toString() === 'unread', fn (Builder $query) => $query->whereNull('read_at'))
            ->with('message')
            ->latest();
    }

    /** @return array<string, mixed> */
    private function resource(InAppMessageRecipient $recipient): array
    {
        $message = $recipient->message;
        $payload = $message->type === 'app_unlock_code' && (! $message->expires_at || $message->expires_at->isFuture())
            ? $message->sensitive_payload
            : null;

        return [
            'id' => $recipient->id,
            'type' => $message->type,
            'title' => $message->title,
            'body' => $message->body,
            'code' => $payload['code'] ?? null,
            'readAt' => $recipient->read_at?->toIso8601String(),
            'publishedAt' => $message->published_at?->toIso8601String(),
            'expiresAt' => $message->expires_at?->toIso8601String(),
        ];
    }
}
