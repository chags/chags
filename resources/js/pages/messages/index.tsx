import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCheck, Clipboard, MailOpen, Trash2 } from 'lucide-react';

type Message = {
    id: string;
    type: string;
    title: string;
    body: string | null;
    code: string | null;
    readAt: string | null;
    publishedAt: string | null;
    expiresAt: string | null;
};
type Props = {
    messages: {
        data: Message[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filter: string;
};
const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

export default function MessagesIndex({ messages, filter }: Props) {
    const patch = async (id: string, action: 'lida' | 'nao-lida') => {
        await fetch(`/mensagens/${id}/${action}`, {
            method: 'PATCH',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        });
        router.reload();
    };
    const markAll = async () => {
        await fetch('/mensagens/marcar-todas-lidas', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        });
        router.reload();
    };
    const remove = async (message: Message) => {
        if (!message.readAt) {
return;
}

        if (!confirm(`Excluir “${message.title}” da sua caixa de mensagens?`)) {
            return;
        }

        const response = await fetch(`/mensagens/${message.id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        });

        if (response.ok) {
router.reload();
}
    };

    return (
        <>
            <Head title="Mensagens" />
            <main className="app-page gap-6">
                <section className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p className="text-sm font-semibold text-primary">
                            Comunicação
                        </p>
                        <h1 className="text-3xl font-bold">Minhas mensagens</h1>
                        <p className="text-base-content/60">
                            Comunicados e códigos temporários enviados para
                            você.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={markAll}
                        className="btn btn-outline"
                    >
                        <CheckCheck className="size-4" /> Marcar todas como
                        lidas
                    </button>
                </section>
                <div role="tablist" className="tabs tabs-box w-fit">
                    <Link
                        role="tab"
                        href="/mensagens"
                        className={`tab ${filter !== 'unread' ? 'tab-active' : ''}`}
                    >
                        Todas
                    </Link>
                    <Link
                        role="tab"
                        href="/mensagens?filter=unread"
                        className={`tab ${filter === 'unread' ? 'tab-active' : ''}`}
                    >
                        Não lidas
                    </Link>
                </div>
                <section className="grid gap-3">
                    {messages.data.map((message) => (
                        <article
                            key={message.id}
                            className={`card border bg-base-100 shadow-sm ${message.readAt ? 'border-base-300' : 'border-primary/40'}`}
                        >
                            <div className="card-body gap-3">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            {!message.readAt && (
                                                <span className="status status-primary" />
                                            )}
                                            <h2 className="card-title text-lg">
                                                {message.title}
                                            </h2>
                                        </div>
                                        <p className="mt-1 text-xs text-base-content/55">
                                            {message.publishedAt
                                                ? new Date(
                                                      message.publishedAt,
                                                  ).toLocaleString('pt-BR')
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap justify-end gap-1">
                                        <button
                                            type="button"
                                            className="btn btn-ghost btn-sm"
                                            onClick={() =>
                                                patch(
                                                    message.id,
                                                    message.readAt
                                                        ? 'nao-lida'
                                                        : 'lida',
                                                )
                                            }
                                        >
                                            <MailOpen className="size-4" />{' '}
                                            {message.readAt
                                                ? 'Marcar não lida'
                                                : 'Marcar lida'}
                                        </button>
                                        {message.readAt && (
                                            <button
                                                type="button"
                                                className="btn btn-ghost text-error btn-sm"
                                                onClick={() => remove(message)}
                                            >
                                                <Trash2 className="size-4" />{' '}
                                                Excluir
                                            </button>
                                        )}
                                    </div>
                                </div>
                                {message.body && (
                                    <p className="whitespace-pre-wrap text-base-content/75">
                                        {message.body}
                                    </p>
                                )}
                                {message.code && (
                                    <div className="rounded-box border border-primary/25 bg-primary/5 p-4">
                                        <p className="text-sm font-medium">
                                            Código temporário
                                        </p>
                                        <div className="mt-2 flex flex-wrap items-center gap-3">
                                            <strong className="font-mono text-3xl tracking-[0.3em] text-primary">
                                                {message.code}
                                            </strong>
                                            <button
                                                type="button"
                                                className="btn btn-primary btn-sm"
                                                onClick={() =>
                                                    navigator.clipboard.writeText(
                                                        message.code ?? '',
                                                    )
                                                }
                                            >
                                                <Clipboard className="size-4" />{' '}
                                                Copiar
                                            </button>
                                        </div>
                                        {message.expiresAt && (
                                            <p className="mt-2 text-xs text-base-content/60">
                                                Válido até{' '}
                                                {new Date(
                                                    message.expiresAt,
                                                ).toLocaleString('pt-BR')}
                                                .
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>
                        </article>
                    ))}
                    {!messages.data.length && (
                        <div className="card border border-base-300 bg-base-100">
                            <div className="card-body items-center py-16 text-center">
                                <Bell className="size-10 opacity-35" />
                                <h2 className="font-semibold">
                                    Nenhuma mensagem
                                </h2>
                                <p className="text-sm text-base-content/60">
                                    Quando houver novidades, elas aparecerão
                                    aqui.
                                </p>
                            </div>
                        </div>
                    )}
                </section>
                {messages.links.length > 3 && (
                    <nav className="join justify-center">
                        {messages.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`btn join-item btn-sm ${link.active ? 'btn-active' : ''}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : (
                                <span
                                    key={index}
                                    className="btn btn-disabled join-item btn-sm"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </nav>
                )}
            </main>
        </>
    );
}
