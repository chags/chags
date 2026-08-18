import { Head, router } from '@inertiajs/react';
import { Building2, Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useRef, useState } from 'react';

type Department = {
    id: number;
    company_id: number;
    parent_id: number | null;
    name: string;
    active: boolean;
    company: { unit_name: string };
    parent: { name: string } | null;
    children_count: number;
    positions_count: number;
};
type Props = {
    departments: Department[];
    companies: Array<{ id: number; unit_name: string }>;
    canManage: boolean;
};
const token = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';
export default function DepartmentsIndex({
    departments,
    companies,
    canManage,
}: Props) {
    const dialog = useRef<HTMLDialogElement>(null);
    const [editing, setEditing] = useState<Department | null>(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const open = (item: Department | null = null) => {
        setEditing(item);
        setError('');
        dialog.current?.showModal();
    };
    const submit = async (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        setBusy(true);
        const values = Object.fromEntries(new FormData(e.currentTarget));
        const body = {
            ...values,
            parent_id: values.parent_id || null,
            active: values.active === '1',
        };
        const response = await fetch(
            editing ? `/hr/departments/${editing.id}` : '/hr/departments',
            {
                method: editing ? 'PUT' : 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token(),
                },
                body: JSON.stringify(body),
            },
        );
        const data = await response.json();
        setBusy(false);
        if (!response.ok) {
            setError(
                data.message ??
                    Object.values(data.errors ?? {})
                        .flat()
                        .join(' '),
            );
            return;
        }
        dialog.current?.close();
        router.reload();
    };
    const remove = async (item: Department) => {
        if (!confirm(`Excluir o setor ${item.name}?`)) return;
        const response = await fetch(`/hr/departments/${item.id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token() },
        });
        const data = await response.json();
        if (!response.ok) setError(data.message);
        else router.reload();
    };
    return (
        <>
            <Head title="Setores" />
            <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 py-6 md:p-8">
                <section className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm font-semibold text-primary">
                            Recursos Humanos
                        </p>
                        <h1 className="text-3xl font-bold">Setores</h1>
                        <p className="text-base-content/60">
                            Organize os departamentos e suas hierarquias.
                        </p>
                    </div>
                    {canManage && (
                        <button
                            className="btn btn-primary"
                            onClick={() => open()}
                        >
                            <Plus className="size-4" />
                            Novo setor
                        </button>
                    )}
                </section>
                {error && <div className="alert alert-error">{error}</div>}
                <section className="card border border-base-300 bg-base-100 shadow-sm">
                    <div className="card-body overflow-x-auto">
                        <table className="table table-zebra">
                            <thead>
                                <tr>
                                    <th>Setor</th>
                                    <th>Unidade</th>
                                    <th>Setor superior</th>
                                    <th>Cargos</th>
                                    <th>Status</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {departments.map((item) => (
                                    <tr key={item.id}>
                                        <td className="font-semibold">
                                            {item.name}
                                        </td>
                                        <td>{item.company.unit_name}</td>
                                        <td>{item.parent?.name ?? '—'}</td>
                                        <td>{item.positions_count}</td>
                                        <td>
                                            <span
                                                className={`badge ${item.active ? 'badge-success' : 'badge-ghost'}`}
                                            >
                                                {item.active
                                                    ? 'Ativo'
                                                    : 'Inativo'}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="flex justify-end gap-1">
                                                {canManage && (
                                                    <>
                                                        <button
                                                            className="btn btn-square btn-ghost btn-sm"
                                                            onClick={() =>
                                                                open(item)
                                                            }
                                                        >
                                                            <Pencil className="size-4" />
                                                        </button>
                                                        <button
                                                            className="btn btn-square btn-ghost text-error btn-sm"
                                                            onClick={() =>
                                                                remove(item)
                                                            }
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {!departments.length && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="py-12 text-center"
                                        >
                                            <Building2 className="mx-auto mb-2 size-8 opacity-40" />
                                            Nenhum setor cadastrado.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
            <dialog ref={dialog} className="modal">
                <div className="modal-box">
                    <h2 className="text-xl font-bold">
                        {editing ? 'Editar setor' : 'Novo setor'}
                    </h2>
                    <form
                        key={editing?.id ?? 'new'}
                        onSubmit={submit}
                        className="mt-5 grid gap-4"
                    >
                        <label className="fieldset">
                            <span className="fieldset-legend">Nome</span>
                            <input
                                required
                                name="name"
                                className="input w-full"
                                defaultValue={editing?.name}
                            />
                        </label>
                        <label className="fieldset">
                            <span className="fieldset-legend">Unidade</span>
                            <select
                                required
                                name="company_id"
                                className="select w-full"
                                defaultValue={editing?.company_id ?? ''}
                            >
                                <option value="" disabled>
                                    Selecione
                                </option>
                                {companies.map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.unit_name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="fieldset">
                            <span className="fieldset-legend">
                                Setor superior
                            </span>
                            <select
                                name="parent_id"
                                className="select w-full"
                                defaultValue={editing?.parent_id ?? ''}
                            >
                                <option value="">Nenhum</option>
                                {departments
                                    .filter((x) => x.id !== editing?.id)
                                    .map((x) => (
                                        <option key={x.id} value={x.id}>
                                            {x.name}
                                        </option>
                                    ))}
                            </select>
                        </label>
                        <label className="flex cursor-pointer items-center gap-3">
                            <input
                                type="checkbox"
                                name="active"
                                value="1"
                                className="toggle toggle-success"
                                defaultChecked={editing?.active ?? true}
                            />
                            <span>Setor ativo</span>
                        </label>
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
                                {busy && (
                                    <span className="loading loading-xs loading-spinner" />
                                )}
                                Salvar
                            </button>
                        </div>
                    </form>
                </div>
                <form method="dialog" className="modal-backdrop">
                    <button>fechar</button>
                </form>
            </dialog>
        </>
    );
}
