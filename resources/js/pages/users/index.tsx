import { faCamera } from '@fortawesome/free-solid-svg-icons/faCamera';
import { faMagnifyingGlass } from '@fortawesome/free-solid-svg-icons/faMagnifyingGlass';
import { faPenToSquare } from '@fortawesome/free-solid-svg-icons/faPenToSquare';
import { faTrashCan } from '@fortawesome/free-solid-svg-icons/faTrashCan';
import { faUserPlus } from '@fortawesome/free-solid-svg-icons/faUserPlus';
import { faUserSecret } from '@fortawesome/free-solid-svg-icons/faUserSecret';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Head, router } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useRef, useState } from 'react';
import { destroy, index, store, update } from '@/routes/users';
import { store as storeAvatar } from '@/routes/users/avatar';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    cpf: string | null;
    phone: string | null;
    birth_date: string | null;
    gender: string | null;
    postal_code: string | null;
    address: string | null;
    address_number: string | null;
    address_complement: string | null;
    district: string | null;
    city: string | null;
    state: string | null;
    avatar: string | null;
    role: string | null;
    created_at: string | null;
    is_current_user: boolean;
};

type Props = {
    users: ManagedUser[];
    canManageSuperAdmins: boolean;
    roles: Array<{ name: string; label: string }>;
};

const roleBadgeClasses: Record<string, string> = {
    candidato:
        'border-sky-300 bg-sky-100 text-sky-800 dark:border-sky-700 dark:bg-sky-950 dark:text-sky-200',
    colaborador:
        'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
    gestor: 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200',
    'rh-analista':
        'border-violet-300 bg-violet-100 text-violet-800 dark:border-violet-700 dark:bg-violet-950 dark:text-violet-200',
    'rh-gestor':
        'border-fuchsia-300 bg-fuchsia-100 text-fuchsia-800 dark:border-fuchsia-700 dark:bg-fuchsia-950 dark:text-fuchsia-200',
    'dp-analista':
        'border-cyan-300 bg-cyan-100 text-cyan-800 dark:border-cyan-700 dark:bg-cyan-950 dark:text-cyan-200',
    'dp-gestor':
        'border-orange-300 bg-orange-100 text-orange-800 dark:border-orange-700 dark:bg-orange-950 dark:text-orange-200',
    administrador:
        'border-slate-300 bg-slate-100 text-slate-800 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200',
    'super-admin':
        'border-red-300 bg-red-100 text-red-800 dark:border-red-700 dark:bg-red-950 dark:text-red-200',
};

const csrfToken = () =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const digits = (value: string, limit: number) =>
    value.replace(/\D/g, '').slice(0, limit);

const maskCpf = (value: string) =>
    digits(value, 11)
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');

const maskCep = (value: string) =>
    digits(value, 8).replace(/(\d{5})(\d)/, '$1-$2');

const maskPhone = (value: string) => {
    const number = digits(value, 11);

    return number.length <= 10
        ? number
              .replace(/(\d{2})(\d)/, '($1) $2')
              .replace(/(\d{4})(\d)/, '$1-$2')
        : number
              .replace(/(\d{2})(\d)/, '($1) $2')
              .replace(/(\d{5})(\d)/, '$1-$2');
};

async function request(
    url: string,
    method: string,
    body?: Record<string, unknown> | FormData,
) {
    const isFormData = body instanceof FormData;
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: body ? (isFormData ? body : JSON.stringify(body)) : undefined,
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const errors = payload.errors
            ? Object.values(payload.errors).flat().join(' ')
            : null;

        throw new Error(
            errors ||
                payload.message ||
                'Não foi possível concluir a operação.',
        );
    }

    return payload;
}

export default function UsersIndex({
    users,
    canManageSuperAdmins,
    roles,
}: Props) {
    const dialog = useRef<HTMLDialogElement>(null);
    const userForm = useRef<HTMLFormElement>(null);
    const cepTimer = useRef<number | undefined>(undefined);
    const [editing, setEditing] = useState<ManagedUser | null>(null);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(10);
    const [processing, setProcessing] = useState(false);
    const [consultingCep, setConsultingCep] = useState(false);
    const [notice, setNotice] = useState<{
        type: 'success' | 'error';
        message: string;
    } | null>(null);
    const normalizedSearch = search.trim().toLocaleLowerCase('pt-BR');
    const filteredUsers = users.filter((user) =>
        [
            user.name,
            user.email,
            user.cpf ?? '',
            user.phone ?? '',
            user.role ?? '',
        ]
            .join(' ')
            .toLocaleLowerCase('pt-BR')
            .includes(normalizedSearch),
    );
    const totalPages = Math.max(1, Math.ceil(filteredUsers.length / pageSize));
    const safePage = Math.min(page, totalPages);
    const paginatedUsers = filteredUsers.slice(
        (safePage - 1) * pageSize,
        safePage * pageSize,
    );

    const notify = (type: 'success' | 'error', message: string) => {
        setNotice({ type, message });
        window.setTimeout(() => setNotice(null), 5000);
    };

    const openUser = (user: ManagedUser | null = null) => {
        setEditing(user);
        dialog.current?.showModal();
    };

    const lookupCep = async (rawCep: string) => {
        const cep = digits(rawCep, 8);

        if (cep.length !== 8 || consultingCep) {
            return;
        }

        setConsultingCep(true);

        try {
            const result = await request(`/settings/profile/cep/${cep}`, 'GET');

            Object.entries(result.address as Record<string, string>).forEach(
                ([name, value]) => {
                    const field = userForm.current?.elements.namedItem(name);

                    if (field instanceof HTMLInputElement) {
                        field.value = value;
                    }
                },
            );
            notify('success', 'Endereço preenchido automaticamente.');
        } catch (error) {
            notify(
                'error',
                error instanceof Error
                    ? error.message
                    : 'Erro ao consultar CEP.',
            );
        } finally {
            setConsultingCep(false);
        }
    };

    const scheduleCepLookup = (value: string) => {
        window.clearTimeout(cepTimer.current);

        if (digits(value, 8).length === 8) {
            cepTimer.current = window.setTimeout(() => lookupCep(value), 500);
        }
    };

    const uploadAvatar = async (user: ManagedUser, file?: File) => {
        if (!file) {
            return;
        }

        const formData = new FormData();
        formData.append('avatar', file);
        setProcessing(true);

        try {
            const route = storeAvatar(user.id);
            const result = await request(route.url, route.method, formData);
            notify('success', result.message);
            router.reload({ only: ['users'] });
        } catch (error) {
            notify(
                'error',
                error instanceof Error ? error.message : 'Erro no upload.',
            );
        } finally {
            setProcessing(false);
        }
    };

    const saveUser = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcessing(true);
        const values = Object.fromEntries(new FormData(event.currentTarget));

        try {
            const route = editing ? update(editing.id) : store();
            const result = await request(route.url, route.method, values);
            dialog.current?.close();
            notify('success', result.message);
            router.reload({ only: ['users'] });
        } catch (error) {
            notify(
                'error',
                error instanceof Error ? error.message : 'Erro inesperado.',
            );
        } finally {
            setProcessing(false);
        }
    };

    const deleteUser = async (user: ManagedUser) => {
        if (
            !window.confirm(`Excluir permanentemente o usuário ${user.name}?`)
        ) {
            return;
        }

        setProcessing(true);

        try {
            const route = destroy(user.id);
            const result = await request(route.url, route.method);
            notify('success', result.message);
            router.reload({ only: ['users'] });
        } catch (error) {
            notify(
                'error',
                error instanceof Error ? error.message : 'Erro inesperado.',
            );
        } finally {
            setProcessing(false);
        }
    };

    return (
        <>
            <Head title="Usuários" />
            <main className="mx-auto w-full max-w-7xl p-4 py-6 md:p-8">
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="mb-3 badge badge-outline badge-primary">
                            Administração
                        </div>
                        <h1 className="text-3xl font-bold">Usuários</h1>
                        <p className="mt-2 text-base-content/60">
                            Gerencie acessos e papéis administrativos.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => openUser()}
                        className="btn btn-primary"
                    >
                        <FontAwesomeIcon icon={faUserPlus} />
                        Novo usuário
                    </button>
                </div>

                <section className="card border border-base-300 bg-base-100 shadow-sm">
                    <div className="card-body">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <label className="fieldset w-full sm:max-w-md">
                                <span className="fieldset-legend">
                                    Buscar usuários
                                </span>
                                <input
                                    type="search"
                                    className="input w-full"
                                    placeholder="Nome, e-mail, CPF ou papel"
                                    value={search}
                                    onChange={(event) => {
                                        setSearch(event.target.value);
                                        setPage(1);
                                    }}
                                />
                            </label>
                            <select
                                aria-label="Itens por página"
                                className="select w-full sm:w-32"
                                value={pageSize}
                                onChange={(event) => {
                                    setPageSize(Number(event.target.value));
                                    setPage(1);
                                }}
                            >
                                {[5, 10, 25, 50].map((size) => (
                                    <option key={size} value={size}>
                                        {size} por página
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="mt-4 overflow-x-auto">
                            <table className="table table-zebra">
                                <thead>
                                    <tr>
                                        <th>Usuário</th>
                                        <th>CPF</th>
                                        <th>Telefone</th>
                                        <th>Papel</th>
                                        <th>Cadastro</th>
                                        <th className="text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {paginatedUsers.map((user) => {
                                        const protectedUser =
                                            user.role === 'super-admin' &&
                                            !canManageSuperAdmins;

                                        return (
                                            <tr key={user.id}>
                                                <td>
                                                    <div className="flex items-center gap-3">
                                                        <div className="placeholder avatar">
                                                            <div className="size-11 rounded-full bg-primary/15 text-primary">
                                                                {user.avatar ? (
                                                                    <img
                                                                        src={
                                                                            user.avatar
                                                                        }
                                                                        alt={
                                                                            user.name
                                                                        }
                                                                    />
                                                                ) : (
                                                                    <span className="font-bold">
                                                                        {user.name
                                                                            .slice(
                                                                                0,
                                                                                2,
                                                                            )
                                                                            .toUpperCase()}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <p className="font-semibold">
                                                                {user.name}
                                                                {user.is_current_user && (
                                                                    <span className="ml-2 badge badge-xs badge-primary">
                                                                        Você
                                                                    </span>
                                                                )}
                                                            </p>
                                                            <p className="text-xs text-base-content/55">
                                                                {user.email}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    {user.cpf
                                                        ? maskCpf(user.cpf)
                                                        : '—'}
                                                </td>
                                                <td>
                                                    {user.phone
                                                        ? maskPhone(user.phone)
                                                        : '—'}
                                                </td>
                                                <td>
                                                    <span
                                                        className={`badge ${roleBadgeClasses[user.role ?? ''] ?? 'badge-ghost'}`}
                                                    >
                                                        {roles.find(
                                                            (role) =>
                                                                role.name ===
                                                                user.role,
                                                        )?.label ?? 'Sem papel'}
                                                    </span>
                                                </td>
                                                <td>
                                                    {user.created_at
                                                        ? new Intl.DateTimeFormat(
                                                              'pt-BR',
                                                          ).format(
                                                              new Date(
                                                                  user.created_at,
                                                              ),
                                                          )
                                                        : '—'}
                                                </td>
                                                <td>
                                                    <div className="flex justify-end gap-1">
                                                        {canManageSuperAdmins &&
                                                            !user.is_current_user && (
                                                                <button
                                                                    type="button"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                    onClick={() =>
                                                                        router.post(
                                                                            `/users/${user.id}/impersonate`,
                                                                        )
                                                                    }
                                                                    data-tip={`Entrar como ${user.name}`}
                                                                    aria-label={`Entrar como ${user.name}`}
                                                                    className="tooltip btn btn-square btn-ghost text-secondary btn-sm hover:bg-secondary/15"
                                                                >
                                                                    <FontAwesomeIcon
                                                                        icon={
                                                                            faUserSecret
                                                                        }
                                                                    />
                                                                </button>
                                                            )}
                                                        <label
                                                            data-tip="Alterar foto"
                                                            aria-label={`Alterar foto de ${user.name}`}
                                                            className={`tooltip btn btn-square btn-ghost text-info btn-sm hover:bg-info/15 ${protectedUser || processing ? 'btn-disabled' : ''}`}
                                                        >
                                                            <FontAwesomeIcon
                                                                icon={faCamera}
                                                            />
                                                            <input
                                                                type="file"
                                                                accept="image/jpeg,image/png,image/webp"
                                                                className="hidden"
                                                                disabled={
                                                                    protectedUser ||
                                                                    processing
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    uploadAvatar(
                                                                        user,
                                                                        event
                                                                            .target
                                                                            .files?.[0],
                                                                    )
                                                                }
                                                            />
                                                        </label>
                                                        <button
                                                            type="button"
                                                            disabled={
                                                                protectedUser
                                                            }
                                                            onClick={() =>
                                                                openUser(user)
                                                            }
                                                            data-tip="Editar usuário"
                                                            aria-label={`Editar ${user.name}`}
                                                            className="tooltip btn btn-square btn-ghost text-warning btn-sm hover:bg-warning/15"
                                                        >
                                                            <FontAwesomeIcon
                                                                icon={
                                                                    faPenToSquare
                                                                }
                                                            />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            disabled={
                                                                protectedUser ||
                                                                user.is_current_user ||
                                                                processing
                                                            }
                                                            onClick={() =>
                                                                deleteUser(user)
                                                            }
                                                            data-tip="Excluir usuário"
                                                            aria-label={`Excluir ${user.name}`}
                                                            className="tooltip btn btn-square btn-ghost text-error btn-sm hover:bg-error/15"
                                                        >
                                                            <FontAwesomeIcon
                                                                icon={
                                                                    faTrashCan
                                                                }
                                                            />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {filteredUsers.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="py-10 text-center text-base-content/55"
                                            >
                                                Nenhum usuário encontrado.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-3 flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <span className="text-base-content/60">
                                {filteredUsers.length} registro(s)
                            </span>
                            <div className="join">
                                <button
                                    type="button"
                                    className="btn join-item btn-sm"
                                    disabled={safePage === 1}
                                    onClick={() =>
                                        setPage((value) => value - 1)
                                    }
                                >
                                    Anterior
                                </button>
                                <span className="btn pointer-events-none join-item btn-sm">
                                    {safePage} de {totalPages}
                                </span>
                                <button
                                    type="button"
                                    className="btn join-item btn-sm"
                                    disabled={safePage === totalPages}
                                    onClick={() =>
                                        setPage((value) => value + 1)
                                    }
                                >
                                    Próxima
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <dialog ref={dialog} className="modal">
                <div className="modal-box max-w-4xl">
                    <h2 className="text-xl font-bold">
                        {editing ? 'Editar usuário' : 'Novo usuário'}
                    </h2>
                    <form
                        key={editing?.id ?? 'new'}
                        ref={userForm}
                        onSubmit={saveUser}
                        className="mt-5 grid gap-4 md:grid-cols-2"
                    >
                        <Field label="Nome completo">
                            <input
                                name="name"
                                className="input w-full"
                                required
                                defaultValue={editing?.name}
                            />
                        </Field>
                        <Field label="E-mail">
                            <input
                                name="email"
                                type="email"
                                className="input w-full"
                                required
                                defaultValue={editing?.email}
                            />
                        </Field>
                        <Field label="CPF">
                            <input
                                name="cpf"
                                className="input w-full"
                                maxLength={14}
                                inputMode="numeric"
                                defaultValue={maskCpf(editing?.cpf ?? '')}
                                onInput={(event) => {
                                    event.currentTarget.value = maskCpf(
                                        event.currentTarget.value,
                                    );
                                }}
                            />
                        </Field>
                        <Field label="Telefone">
                            <input
                                name="phone"
                                className="input w-full"
                                maxLength={15}
                                inputMode="tel"
                                defaultValue={maskPhone(editing?.phone ?? '')}
                                onInput={(event) => {
                                    event.currentTarget.value = maskPhone(
                                        event.currentTarget.value,
                                    );
                                }}
                            />
                        </Field>
                        <Field label="Data de nascimento">
                            <input
                                name="birth_date"
                                type="date"
                                className="input w-full"
                                defaultValue={editing?.birth_date ?? ''}
                            />
                        </Field>
                        <Field label="Gênero">
                            <select
                                name="gender"
                                className="select w-full"
                                defaultValue={editing?.gender ?? ''}
                            >
                                <option value="">Selecione</option>
                                <option value="female">Feminino</option>
                                <option value="male">Masculino</option>
                                <option value="non_binary">Não binário</option>
                                <option value="not_informed">
                                    Prefiro não informar
                                </option>
                            </select>
                        </Field>
                        <div className="divider md:col-span-2">Endereço</div>
                        <Field label="CEP">
                            <div className="join w-full">
                                <input
                                    name="postal_code"
                                    className="input join-item w-full"
                                    inputMode="numeric"
                                    maxLength={9}
                                    defaultValue={maskCep(
                                        editing?.postal_code ?? '',
                                    )}
                                    onInput={(event) => {
                                        event.currentTarget.value = maskCep(
                                            event.currentTarget.value,
                                        );
                                    }}
                                    onChange={(event) =>
                                        scheduleCepLookup(
                                            event.currentTarget.value,
                                        )
                                    }
                                />
                                <button
                                    type="button"
                                    className="btn join-item btn-outline"
                                    disabled={consultingCep}
                                    aria-label="Consultar CEP"
                                    onClick={() => {
                                        const field =
                                            userForm.current?.elements.namedItem(
                                                'postal_code',
                                            );

                                        if (field instanceof HTMLInputElement) {
                                            lookupCep(field.value);
                                        }
                                    }}
                                >
                                    {consultingCep ? (
                                        <span className="loading loading-sm loading-spinner" />
                                    ) : (
                                        <FontAwesomeIcon
                                            icon={faMagnifyingGlass}
                                        />
                                    )}
                                </button>
                            </div>
                        </Field>
                        <Field label="Logradouro">
                            <input
                                name="address"
                                className="input w-full"
                                defaultValue={editing?.address ?? ''}
                            />
                        </Field>
                        <Field label="Número">
                            <input
                                name="address_number"
                                className="input w-full"
                                defaultValue={editing?.address_number ?? ''}
                            />
                        </Field>
                        <Field label="Complemento">
                            <input
                                name="address_complement"
                                className="input w-full"
                                defaultValue={editing?.address_complement ?? ''}
                            />
                        </Field>
                        <Field label="Bairro">
                            <input
                                name="district"
                                className="input w-full"
                                defaultValue={editing?.district ?? ''}
                            />
                        </Field>
                        <Field label="Cidade">
                            <input
                                name="city"
                                className="input w-full"
                                defaultValue={editing?.city ?? ''}
                            />
                        </Field>
                        <Field label="UF">
                            <input
                                name="state"
                                className="input w-full uppercase"
                                maxLength={2}
                                defaultValue={editing?.state ?? ''}
                            />
                        </Field>
                        <div />
                        <div className="divider md:col-span-2">Acesso</div>
                        <Field label="Papel">
                            <select
                                name="role"
                                className="select w-full"
                                defaultValue={editing?.role ?? 'administrador'}
                            >
                                {roles
                                    .filter(
                                        (role) =>
                                            role.name !== 'super-admin' ||
                                            canManageSuperAdmins,
                                    )
                                    .map((role) => (
                                        <option
                                            key={role.name}
                                            value={role.name}
                                        >
                                            {role.label}
                                        </option>
                                    ))}
                            </select>
                        </Field>
                        <div />
                        <Field
                            label={editing ? 'Nova senha (opcional)' : 'Senha'}
                        >
                            <input
                                name="password"
                                type="password"
                                className="input w-full"
                                minLength={8}
                                required={!editing}
                                autoComplete="new-password"
                            />
                        </Field>
                        <Field label="Confirmar senha">
                            <input
                                name="password_confirmation"
                                type="password"
                                className="input w-full"
                                minLength={8}
                                required={!editing}
                                autoComplete="new-password"
                            />
                        </Field>
                        <div className="modal-action md:col-span-2">
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => dialog.current?.close()}
                            >
                                Cancelar
                            </button>
                            <button
                                disabled={processing}
                                className="btn btn-primary"
                            >
                                {processing && (
                                    <span className="loading loading-sm loading-spinner" />
                                )}
                                Salvar usuário
                            </button>
                        </div>
                    </form>
                </div>
                <form method="dialog" className="modal-backdrop">
                    <button>Fechar</button>
                </form>
            </dialog>

            {notice && (
                <div className="toast toast-end z-50">
                    <div
                        className={`alert ${notice.type === 'success' ? 'alert-success' : 'alert-error'}`}
                    >
                        <span>{notice.message}</span>
                    </div>
                </div>
            )}
        </>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="fieldset">
            <span className="fieldset-legend">{label}</span>
            {children}
        </label>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Usuários', href: index() }],
};
