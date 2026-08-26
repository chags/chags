import { Head, router } from '@inertiajs/react';
import { Archive, Edit3, Mail, Plus, Send, Trash2, Users } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';

type Message = {
    id: string;
    title: string;
    body: string;
    audience: 'user' | 'all';
    status: 'draft' | 'scheduled' | 'sent' | 'archived';
    scheduled_at: string | null;
    expires_at: string | null;
    published_at: string | null;
    recipients_count: number;
    read_count: number;
    creator: { name: string } | null;
};
type Props = {
    messages: { data: Message[] };
    users: Array<{ id: number; name: string; email: string }>;
    abilities: { manage: boolean; send: boolean; archive: boolean };
};
const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

export default function PersonnelMessagesIndex({
    messages,
    users,
    abilities,
}: Props) {
    const dialog = useRef<HTMLDialogElement>(null);
    const [editing, setEditing] = useState<Message | null>(null);
    const [audience, setAudience] = useState<'user' | 'all'>('user');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const open = (message: Message | null = null) => {
        setEditing(message);
        setAudience(message?.audience ?? 'user');
        setError('');
        dialog.current?.showModal();
    };
    const request = async (url: string, method: string, body?: object) => {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message ??
                    Object.values(data.errors ?? {})
                        .flat()
                        .join(' '),
            );
        }

        router.reload();
    };
    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setBusy(true);
        setError('');
        const values = Object.fromEntries(new FormData(event.currentTarget));

        try {
            await request(
                editing
                    ? `/personnel/mensagens/${editing.id}`
                    : '/personnel/mensagens',
                editing ? 'PUT' : 'POST',
                {
                    ...values,
                    audience,
                    user_id:
                        audience === 'user' ? Number(values.user_id) : null,
                    scheduled_at: values.scheduled_at || null,
                    expires_at: values.expires_at || null,
                    send_now: values.send_now === '1',
                },
            );
            dialog.current?.close();
        } catch (exception) {
            setError((exception as Error).message);
        } finally {
            setBusy(false);
        }
    };
    const action = async (
        message: Message,
        operation: 'enviar' | 'arquivar' | 'delete',
    ) => {
        if (
            operation === 'enviar' &&
            !confirm(`Enviar “${message.title}” agora?`)
        ) {
            return;
        }

        if (
            operation === 'delete' &&
            !confirm(`Excluir o rascunho “${message.title}”?`)
        ) {
            return;
        }

        try {
            await request(
                `/personnel/mensagens/${message.id}${operation === 'delete' ? '' : `/${operation}`}`,
                operation === 'delete' ? 'DELETE' : 'POST',
            );
        } catch (exception) {
            setError((exception as Error).message);
        }
    };
    const labels: Record<Message['status'], string> = {
        draft: 'Rascunho',
        scheduled: 'Agendada',
        sent: 'Enviada',
        archived: 'Arquivada',
    };

    return (
        <>
            <Head title="Mensagens do RH" />
            <main className="app-page gap-6">
                <section className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm font-semibold text-primary">
                            Setor Pessoal
                        </p>
                        <h1 className="text-3xl font-bold">Mensagens</h1>
                        <p className="text-base-content/60">
                            Envie comunicados individuais ou para toda a equipe.
                        </p>
                    </div>
                    {abilities.manage && (
                        <button
                            className="btn btn-primary"
                            onClick={() => open()}
                        >
                            <Plus className="size-4" /> Nova mensagem
                        </button>
                    )}
                </section>
                {error && <div className="alert alert-error">{error}</div>}
                <section className="card border border-base-300 bg-base-100 shadow-sm">
                    <div className="card-body overflow-x-auto">
                        <table className="table table-zebra">
                            <thead>
                                <tr>
                                    <th>Mensagem</th>
                                    <th>Público</th>
                                    <th>Status</th>
                                    <th>Leituras</th>
                                    <th>Criada por</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {messages.data.map((message) => (
                                    <tr key={message.id}>
                                        <td>
                                            <strong>{message.title}</strong>
                                            <p className="max-w-md truncate text-sm opacity-60">
                                                {message.body}
                                            </p>
                                        </td>
                                        <td>
                                            {message.audience === 'all'
                                                ? 'Todos'
                                                : 'Individual'}
                                        </td>
                                        <td>
                                            <span className="badge badge-outline">
                                                {labels[message.status]}
                                            </span>
                                        </td>
                                        <td>
                                            {message.read_count}/
                                            {message.recipients_count}
                                        </td>
                                        <td>
                                            {message.creator?.name ?? 'Sistema'}
                                        </td>
                                        <td>
                                            <div className="flex justify-end gap-1">
                                                {abilities.manage &&
                                                    [
                                                        'draft',
                                                        'scheduled',
                                                    ].includes(
                                                        message.status,
                                                    ) && (
                                                        <button
                                                            className="btn btn-square btn-ghost btn-sm"
                                                            onClick={() =>
                                                                open(message)
                                                            }
                                                            aria-label="Editar"
                                                        >
                                                            <Edit3 className="size-4" />
                                                        </button>
                                                    )}
                                                {abilities.send &&
                                                    [
                                                        'draft',
                                                        'scheduled',
                                                    ].includes(
                                                        message.status,
                                                    ) && (
                                                        <button
                                                            className="btn btn-square btn-ghost text-primary btn-sm"
                                                            onClick={() =>
                                                                action(
                                                                    message,
                                                                    'enviar',
                                                                )
                                                            }
                                                            aria-label="Enviar"
                                                        >
                                                            <Send className="size-4" />
                                                        </button>
                                                    )}
                                                {abilities.manage &&
                                                    message.status ===
                                                        'draft' && (
                                                        <button
                                                            className="btn btn-square btn-ghost text-error btn-sm"
                                                            onClick={() =>
                                                                action(
                                                                    message,
                                                                    'delete',
                                                                )
                                                            }
                                                            aria-label="Excluir"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    )}
                                                {abilities.archive &&
                                                    message.status ===
                                                        'sent' && (
                                                        <button
                                                            className="btn btn-square btn-ghost btn-sm"
                                                            onClick={() =>
                                                                action(
                                                                    message,
                                                                    'arquivar',
                                                                )
                                                            }
                                                            aria-label="Arquivar"
                                                        >
                                                            <Archive className="size-4" />
                                                        </button>
                                                    )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {!messages.data.length && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="py-14 text-center"
                                        >
                                            <Mail className="mx-auto mb-2 size-8 opacity-40" />
                                            Nenhuma mensagem cadastrada.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
            <dialog ref={dialog} className="modal">
                <div className="modal-box max-w-2xl">
                    <h2 className="text-xl font-bold">
                        {editing ? 'Editar mensagem' : 'Nova mensagem'}
                    </h2>
                    <form
                        key={editing?.id ?? 'new'}
                        onSubmit={submit}
                        className="mt-5 grid gap-4"
                    >
                        <label className="fieldset">
                            <span className="fieldset-legend">Título</span>
                            <input
                                name="title"
                                required
                                maxLength={150}
                                defaultValue={editing?.title}
                                className="input w-full"
                            />
                        </label>
                        <label className="fieldset">
                            <span className="fieldset-legend">Mensagem</span>
                            <textarea
                                name="body"
                                required
                                defaultValue={editing?.body}
                                className="textarea min-h-32 w-full"
                            />
                        </label>
                        <fieldset className="fieldset">
                            <legend className="fieldset-legend">
                                Destinatários
                            </legend>
                            <div className="flex gap-4">
                                <label className="label">
                                    <input
                                        type="radio"
                                        className="radio"
                                        checked={audience === 'user'}
                                        onChange={() => setAudience('user')}
                                    />{' '}
                                    Usuário
                                </label>
                                <label className="label">
                                    <input
                                        type="radio"
                                        className="radio"
                                        checked={audience === 'all'}
                                        onChange={() => setAudience('all')}
                                    />{' '}
                                    <Users className="size-4" /> Todos
                                </label>
                            </div>
                        </fieldset>
                        {audience === 'user' && (
                            <label className="fieldset">
                                <span className="fieldset-legend">Usuário</span>
                                <select
                                    name="user_id"
                                    required
                                    className="select w-full"
                                >
                                    <option value="">Selecione</option>
                                    {users.map((user) => (
                                        <option key={user.id} value={user.id}>
                                            {user.name} — {user.email}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="fieldset">
                                <span className="fieldset-legend">
                                    Agendar para
                                </span>
                                <input
                                    type="datetime-local"
                                    name="scheduled_at"
                                    className="input w-full"
                                />
                            </label>
                            <label className="fieldset">
                                <span className="fieldset-legend">
                                    Expira em
                                </span>
                                <input
                                    type="datetime-local"
                                    name="expires_at"
                                    className="input w-full"
                                />
                            </label>
                        </div>
                        {!editing && abilities.send && (
                            <label className="label justify-start gap-3">
                                <input
                                    type="checkbox"
                                    name="send_now"
                                    value="1"
                                    className="checkbox"
                                />{' '}
                                Enviar imediatamente
                            </label>
                        )}
                        {error && (
                            <div className="alert alert-error">{error}</div>
                        )}
                        <div className="modal-action">
                            <button
                                type="button"
                                className="btn"
                                onClick={() => dialog.current?.close()}
                            >
                                Cancelar
                            </button>
                            <button disabled={busy} className="btn btn-primary">
                                {busy ? 'Salvando...' : 'Salvar'}
                            </button>
                        </div>
                    </form>
                </div>
                <form method="dialog" className="modal-backdrop">
                    <button>Fechar</button>
                </form>
            </dialog>
        </>
    );
}
