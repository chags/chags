import { Head, router } from '@inertiajs/react';
import { BadgeCheck, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { type FormEvent, useRef, useState } from 'react';

type Position = {
    id: number;
    company_id: number;
    department_id: number | null;
    title: string;
    level: string | null;
    code: string | null;
    description: string | null;
    active: boolean;
    company: { unit_name: string };
    department: { name: string } | null;
};

type Pagination = {
    data: Position[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    positions: Pagination;
    companies: Array<{ id: number; unit_name: string }>;
    departments: Array<{ id: number; company_id: number; name: string }>;
    filters: { search: string; status: string };
    canManage: boolean;
};

const levels: Record<string, string> = {
    intern: 'Estagiário',
    junior: 'Júnior',
    mid: 'Pleno',
    senior: 'Sênior',
    specialist: 'Especialista',
    lead: 'Líder',
    manager: 'Gerente',
};

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

export default function PositionsIndex({
    positions,
    companies,
    departments,
    filters,
    canManage,
}: Props) {
    const dialog = useRef<HTMLDialogElement>(null);
    const [editing, setEditing] = useState<Position | null>(null);
    const [companyId, setCompanyId] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || 'all');
    const [busy, setBusy] = useState(false);
    const [notice, setNotice] = useState<{
        type: 'success' | 'error';
        message: string;
    } | null>(null);

    const open = (position: Position | null = null) => {
        setEditing(position);
        setCompanyId(position?.company_id ?? null);
        setNotice(null);
        dialog.current?.showModal();
    };

    const applyFilters = (nextStatus = status) => {
        router.get(
            '/hr/positions',
            {
                search: search.trim() || undefined,
                status: nextStatus === 'all' ? undefined : nextStatus,
            },
            { preserveState: true, replace: true },
        );
    };

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setBusy(true);
        setNotice(null);
        const values = Object.fromEntries(new FormData(event.currentTarget));
        const body = {
            ...values,
            department_id: values.department_id || null,
            level: values.level || null,
            code: values.code || null,
            description: values.description || null,
            active: values.active === '1',
        };
        try {
            const response = await fetch(
                editing ? `/hr/positions/${editing.id}` : '/hr/positions',
                {
                    method: editing ? 'PUT' : 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify(body),
                },
            );
            const data = await response.json();
            if (!response.ok) {
                throw new Error(
                    Object.values(data.errors ?? {})
                        .flat()
                        .join(' ') || data.message,
                );
            }
            dialog.current?.close();
            setNotice({ type: 'success', message: data.message });
            router.reload();
        } catch (reason) {
            setNotice({
                type: 'error',
                message:
                    reason instanceof Error
                        ? reason.message
                        : 'Não foi possível salvar o cargo.',
            });
        } finally {
            setBusy(false);
        }
    };

    const remove = async (position: Position) => {
        if (!confirm(`Excluir o cargo ${position.title}?`)) return;
        setBusy(true);
        const response = await fetch(`/hr/positions/${position.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });
        const data = await response.json();
        setBusy(false);
        if (!response.ok) {
            setNotice({ type: 'error', message: data.message });
            return;
        }
        setNotice({ type: 'success', message: data.message });
        router.reload();
    };

    return (
        <>
            <Head title="Cargos" />
            <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 py-6 md:p-8">
                <section className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm font-semibold text-primary">
                            Recursos Humanos
                        </p>
                        <h1 className="text-3xl font-bold">Cargos</h1>
                        <p className="text-base-content/60">
                            Organize funções, senioridades e vínculos com os
                            setores.
                        </p>
                    </div>
                    {canManage && (
                        <button
                            className="btn btn-primary"
                            onClick={() => open()}
                        >
                            <Plus className="size-4" />
                            Novo cargo
                        </button>
                    )}
                </section>

                {notice && (
                    <div
                        className={`alert ${notice.type === 'success' ? 'alert-success' : 'alert-error'}`}
                    >
                        {notice.message}
                    </div>
                )}

                <section className="card border border-base-300 bg-base-100 shadow-sm">
                    <div className="card-body">
                        <form
                            className="grid gap-3 md:grid-cols-[1fr_12rem_auto]"
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilters();
                            }}
                        >
                            <label className="input flex w-full items-center gap-2">
                                <Search className="size-4 opacity-50" />
                                <input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Buscar cargo, código ou setor"
                                    className="grow"
                                />
                            </label>
                            <select
                                className="select w-full"
                                value={status}
                                onChange={(event) => {
                                    setStatus(event.target.value);
                                    applyFilters(event.target.value);
                                }}
                            >
                                <option value="all">Todos os status</option>
                                <option value="active">Ativos</option>
                                <option value="inactive">Inativos</option>
                            </select>
                            <button className="btn btn-primary">Buscar</button>
                        </form>

                        <div className="mt-3 overflow-x-auto">
                            <table className="table table-zebra">
                                <thead>
                                    <tr>
                                        <th>Cargo</th>
                                        <th>Código</th>
                                        <th>Unidade</th>
                                        <th>Setor</th>
                                        <th>Status</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {positions.data.map((position) => (
                                        <tr key={position.id}>
                                            <td>
                                                <span className="block font-semibold">
                                                    {position.title}
                                                </span>
                                                <small className="text-base-content/60">
                                                    {position.level
                                                        ? levels[position.level]
                                                        : 'Sem senioridade'}
                                                </small>
                                            </td>
                                            <td>{position.code ?? '—'}</td>
                                            <td>
                                                {position.company.unit_name}
                                            </td>
                                            <td>
                                                {position.department?.name ??
                                                    '—'}
                                            </td>
                                            <td>
                                                <span
                                                    className={`badge ${position.active ? 'badge-success' : 'badge-ghost'}`}
                                                >
                                                    {position.active
                                                        ? 'Ativo'
                                                        : 'Inativo'}
                                                </span>
                                            </td>
                                            <td>
                                                {canManage && (
                                                    <div className="flex justify-end gap-1">
                                                        <button
                                                            className="btn btn-square btn-ghost btn-sm"
                                                            title="Editar"
                                                            onClick={() =>
                                                                open(position)
                                                            }
                                                        >
                                                            <Pencil className="size-4" />
                                                        </button>
                                                        <button
                                                            className="btn btn-square btn-ghost text-error btn-sm"
                                                            title="Excluir"
                                                            disabled={busy}
                                                            onClick={() =>
                                                                remove(position)
                                                            }
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    {!positions.data.length && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="py-12 text-center text-base-content/55"
                                            >
                                                <BadgeCheck className="mx-auto mb-2 size-8 opacity-40" />
                                                Nenhum cargo encontrado.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {positions.total > 0 && (
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-base-300 pt-4">
                                <p className="text-sm text-base-content/60">
                                    Exibindo {positions.from}–{positions.to} de{' '}
                                    {positions.total}
                                </p>
                                <div className="join">
                                    <button
                                        className="btn join-item btn-sm"
                                        disabled={!positions.prev_page_url}
                                        onClick={() =>
                                            positions.prev_page_url &&
                                            router.get(
                                                positions.prev_page_url,
                                                {},
                                                { preserveState: true },
                                            )
                                        }
                                    >
                                        Anterior
                                    </button>
                                    <span className="btn pointer-events-none join-item btn-sm">
                                        Página {positions.current_page} de{' '}
                                        {positions.last_page}
                                    </span>
                                    <button
                                        className="btn join-item btn-sm"
                                        disabled={!positions.next_page_url}
                                        onClick={() =>
                                            positions.next_page_url &&
                                            router.get(
                                                positions.next_page_url,
                                                {},
                                                { preserveState: true },
                                            )
                                        }
                                    >
                                        Próxima
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </section>
            </main>

            <dialog ref={dialog} className="modal">
                <div className="modal-box max-w-2xl">
                    <h2 className="text-xl font-bold">
                        {editing ? 'Editar cargo' : 'Novo cargo'}
                    </h2>
                    <form
                        key={editing?.id ?? 'new'}
                        onSubmit={submit}
                        className="mt-5 grid gap-4"
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Título">
                                <input
                                    required
                                    name="title"
                                    className="input w-full"
                                    defaultValue={editing?.title}
                                />
                            </Field>
                            <Field label="Código interno">
                                <input
                                    name="code"
                                    className="input w-full uppercase"
                                    maxLength={50}
                                    defaultValue={editing?.code ?? ''}
                                />
                            </Field>
                            <Field label="Unidade">
                                <select
                                    required
                                    name="company_id"
                                    className="select w-full"
                                    defaultValue={editing?.company_id ?? ''}
                                    onChange={(event) =>
                                        setCompanyId(Number(event.target.value))
                                    }
                                >
                                    <option value="" disabled>
                                        Selecione
                                    </option>
                                    {companies.map((company) => (
                                        <option
                                            key={company.id}
                                            value={company.id}
                                        >
                                            {company.unit_name}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Setor">
                                <select
                                    name="department_id"
                                    className="select w-full"
                                    defaultValue={editing?.department_id ?? ''}
                                >
                                    <option value="">Nenhum</option>
                                    {departments
                                        .filter(
                                            (department) =>
                                                department.company_id ===
                                                companyId,
                                        )
                                        .map((department) => (
                                            <option
                                                key={department.id}
                                                value={department.id}
                                            >
                                                {department.name}
                                            </option>
                                        ))}
                                </select>
                            </Field>
                            <Field label="Senioridade">
                                <select
                                    name="level"
                                    className="select w-full"
                                    defaultValue={editing?.level ?? ''}
                                >
                                    <option value="">Não definida</option>
                                    {Object.entries(levels).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>
                            <label className="flex cursor-pointer items-center gap-3 self-end pb-2">
                                <input
                                    type="checkbox"
                                    name="active"
                                    value="1"
                                    className="toggle toggle-success"
                                    defaultChecked={editing?.active ?? true}
                                />
                                <span>Cargo ativo</span>
                            </label>
                        </div>
                        <Field label="Descrição">
                            <textarea
                                name="description"
                                className="textarea min-h-28 w-full"
                                maxLength={5000}
                                defaultValue={editing?.description ?? ''}
                            />
                        </Field>
                        {notice?.type === 'error' && (
                            <div className="alert alert-error">
                                {notice.message}
                            </div>
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
                                {busy && (
                                    <span className="loading loading-xs loading-spinner" />
                                )}
                                Salvar cargo
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

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <label className="fieldset">
            <span className="fieldset-legend">{label}</span>
            {children}
        </label>
    );
}
