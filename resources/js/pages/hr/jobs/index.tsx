import { Head, router } from '@inertiajs/react';
import { BriefcaseBusiness, ImageUp, Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, type ReactNode, useRef, useState } from 'react';

type Job = {
    id: number;
    title: string;
    company_id: number;
    department_id: number;
    position_id: number | null;
    hiring_manager_id: number | null;
    description: string;
    requirements: string | null;
    benefits: string | null;
    image_url: string | null;
    workplace_type: string;
    employment_type: string;
    city: string | null;
    state: string | null;
    status: string;
    closes_at: string | null;
    applications_count: number;
    department: { name: string };
    company: { unit_name: string };
    position: { title: string; level: string | null } | null;
};
type Option = {
    id: number;
    name?: string;
    unit_name?: string;
    company_id?: number;
};
type Props = {
    jobs: Job[];
    companies: Option[];
    departments: Option[];
    positions: Option[];
    managers: Option[];
    abilities: { create: boolean; delete: boolean };
};
const token = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';
const labels: Record<string, string> = {
    draft: 'Rascunho',
    published: 'Publicada',
    paused: 'Pausada',
    closed: 'Não aceita mais candidaturas',
};
const statusDescriptions: Record<string, string> = {
    draft: 'A vaga fica salva apenas para o RH e não aparece no site.',
    published: 'A vaga aparece no site e permite novas candidaturas.',
    paused: 'A vaga fica temporariamente indisponível para candidatos.',
    closed: 'A vaga continua visível para consulta e acompanhamento, mas bloqueia novas inscrições.',
};
const statusBadgeClasses: Record<string, string> = {
    draft: 'badge-neutral',
    published: 'badge-success',
    paused: 'badge-info',
    closed: 'badge-warning',
};
const statusSelectClasses: Record<string, string> = {
    draft: 'select-neutral',
    published: 'select-success',
    paused: 'select-info',
    closed: 'select-warning',
};
const statusMessageClasses: Record<string, string> = {
    draft: 'border-neutral/30 bg-neutral/10 text-base-content',
    published: 'border-success/30 bg-success/10 text-success',
    paused: 'border-info/30 bg-info/10 text-info',
    closed: 'border-warning/30 bg-warning/10 text-warning-content',
};

export default function JobsIndex({
    jobs,
    companies,
    departments,
    positions,
    managers,
    abilities,
}: Props) {
    const dialog = useRef<HTMLDialogElement>(null);
    const [editing, setEditing] = useState<Job | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [selectedStatus, setSelectedStatus] = useState('draft');
    const open = (job: Job | null = null) => {
        setEditing(job);
        setSelectedStatus(job?.status ?? 'draft');
        setError('');
        dialog.current?.showModal();
    };
    const submit = async (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        setBusy(true);
        setError('');
        const body = Object.fromEntries(new FormData(e.currentTarget));
        const url = editing ? `/hr/jobs/${editing.id}` : '/hr/jobs';
        try {
            const response = await fetch(url, {
                method: editing ? 'PUT' : 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token(),
                },
                body: JSON.stringify(body),
            });
            const data = await response.json();
            if (!response.ok)
                throw new Error(
                    data.message ??
                        Object.values(data.errors ?? {})
                            .flat()
                            .join(' '),
                );
            dialog.current?.close();
            router.reload();
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Erro ao salvar vaga.',
            );
        } finally {
            setBusy(false);
        }
    };
    const remove = async (job: Job) => {
        if (!confirm(`Excluir a vaga ${job.title}?`)) return;
        const response = await fetch(`/hr/jobs/${job.id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token() },
        });
        if (response.ok) router.reload();
        else setError((await response.json()).message ?? 'Erro ao excluir.');
    };
    const uploadImage = async (job: Job, file?: File) => {
        if (!file) return;
        setBusy(true);
        setError('');
        const form = new FormData();
        form.append('image', file);
        const response = await fetch(`/hr/jobs/${job.id}/image`, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token() },
            body: form,
        });
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
        router.reload();
    };
    return (
        <>
            <Head title="Vagas" />
            <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 py-6 md:p-8">
                <section className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm font-semibold text-primary">
                            Recursos Humanos
                        </p>
                        <h1 className="text-3xl font-bold">Vagas</h1>
                        <p className="text-base-content/60">
                            Cadastre e acompanhe oportunidades.
                        </p>
                    </div>
                    {abilities.create && (
                        <button
                            className="btn btn-primary"
                            onClick={() => open()}
                        >
                            <Plus className="size-4" />
                            Nova vaga
                        </button>
                    )}
                </section>
                {error && <div className="alert alert-error">{error}</div>}
                <section className="card border border-base-300 bg-base-100 shadow-sm">
                    <div className="card-body overflow-x-auto">
                        <table className="table table-zebra">
                            <thead>
                                <tr>
                                    <th>Vaga</th>
                                    <th>Unidade</th>
                                    <th>Setor</th>
                                    <th>Status</th>
                                    <th>Candidaturas</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {jobs.map((job) => (
                                    <tr key={job.id}>
                                        <td>
                                            <div className="flex items-center gap-3">
                                                {job.image_url ? (
                                                    <img
                                                        src={job.image_url}
                                                        alt=""
                                                        className="size-12 rounded-lg object-cover"
                                                    />
                                                ) : (
                                                    <span className="grid size-12 place-items-center rounded-lg bg-base-200">
                                                        <BriefcaseBusiness className="size-5 opacity-40" />
                                                    </span>
                                                )}
                                                <span>
                                                    <span className="block font-semibold">
                                                        {job.title}
                                                    </span>
                                                    <small className="block text-base-content/55">
                                                        {job.position?.title ??
                                                            'Cargo não vinculado'}
                                                    </small>
                                                </span>
                                            </div>
                                        </td>
                                        <td>{job.company.unit_name}</td>
                                        <td>{job.department.name}</td>
                                        <td>
                                            <span
                                                className={`badge ${statusBadgeClasses[job.status] ?? 'badge-outline'}`}
                                            >
                                                {labels[job.status]}
                                            </span>
                                        </td>
                                        <td>{job.applications_count}</td>
                                        <td>
                                            <div className="flex justify-end gap-1">
                                                <label
                                                    data-tip="Enviar imagem"
                                                    className={`tooltip btn btn-square btn-ghost text-info btn-sm ${busy ? 'btn-disabled' : ''}`}
                                                >
                                                    <ImageUp className="size-4" />
                                                    <input
                                                        type="file"
                                                        accept="image/jpeg,image/png,image/webp"
                                                        className="hidden"
                                                        disabled={busy}
                                                        onChange={(event) =>
                                                            uploadImage(
                                                                job,
                                                                event.target
                                                                    .files?.[0],
                                                            )
                                                        }
                                                    />
                                                </label>
                                                <button
                                                    className="btn btn-square btn-ghost btn-sm"
                                                    onClick={() => open(job)}
                                                >
                                                    <Pencil className="size-4" />
                                                </button>
                                                {abilities.delete && (
                                                    <button
                                                        className="btn btn-square btn-ghost text-error btn-sm"
                                                        onClick={() =>
                                                            remove(job)
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {!jobs.length && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="py-12 text-center"
                                        >
                                            <BriefcaseBusiness className="mx-auto mb-2 size-8 opacity-40" />
                                            Nenhuma vaga cadastrada.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
            <dialog ref={dialog} className="modal">
                <div className="modal-box max-w-4xl">
                    <h2 className="text-xl font-bold">
                        {editing ? 'Editar vaga' : 'Nova vaga'}
                    </h2>
                    <form
                        key={editing?.id ?? 'new'}
                        onSubmit={submit}
                        className="mt-5 grid gap-4 md:grid-cols-2"
                    >
                        <Field label="Título">
                            <input
                                required
                                name="title"
                                className="input w-full"
                                defaultValue={editing?.title}
                            />
                        </Field>
                        <Field label="Unidade">
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
                        </Field>
                        <Field label="Setor">
                            <select
                                required
                                name="department_id"
                                className="select w-full"
                                defaultValue={editing?.department_id ?? ''}
                            >
                                <option value="" disabled>
                                    Selecione
                                </option>
                                {departments.map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Cargo">
                            <select
                                name="position_id"
                                className="select w-full"
                                defaultValue={editing?.position_id ?? ''}
                            >
                                <option value="">Não vinculado</option>
                                {positions.map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Gestor responsável">
                            <select
                                name="hiring_manager_id"
                                className="select w-full"
                                defaultValue={editing?.hiring_manager_id ?? ''}
                            >
                                <option value="">Não definido</option>
                                {managers.map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Status da vaga" wide>
                            <select
                                name="status"
                                className={`select w-full ${statusSelectClasses[selectedStatus] ?? ''}`}
                                value={selectedStatus}
                                onChange={(event) =>
                                    setSelectedStatus(event.target.value)
                                }
                            >
                                {Object.entries(labels).map(([v, l]) => (
                                    <option key={v} value={v}>
                                        {l}
                                    </option>
                                ))}
                            </select>
                            <span
                                className={`mt-2 block rounded-box border p-3 text-sm ${statusMessageClasses[selectedStatus] ?? 'border-base-300 bg-base-200 text-base-content/65'}`}
                            >
                                {statusDescriptions[selectedStatus]}
                            </span>
                        </Field>
                        <Field label="Modelo">
                            <select
                                name="workplace_type"
                                className="select w-full"
                                defaultValue={
                                    editing?.workplace_type ?? 'onsite'
                                }
                            >
                                <option value="onsite">Presencial</option>
                                <option value="hybrid">Híbrido</option>
                                <option value="remote">Remoto</option>
                            </select>
                        </Field>
                        <Field label="Contratação">
                            <select
                                name="employment_type"
                                className="select w-full"
                                defaultValue={editing?.employment_type ?? 'clt'}
                            >
                                <option value="clt">CLT</option>
                                <option value="pj">PJ</option>
                                <option value="internship">Estágio</option>
                                <option value="temporary">Temporário</option>
                                <option value="apprentice">Aprendiz</option>
                            </select>
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
                                maxLength={2}
                                className="input w-full uppercase"
                                defaultValue={editing?.state ?? ''}
                            />
                        </Field>
                        <Field label="Encerramento">
                            <input
                                name="closes_at"
                                type="date"
                                className="input w-full"
                                defaultValue={
                                    editing?.closes_at?.slice(0, 10) ?? ''
                                }
                            />
                        </Field>
                        <div />
                        <Field label="Descrição" wide>
                            <textarea
                                required
                                name="description"
                                className="textarea min-h-28 w-full"
                                defaultValue={editing?.description}
                            />
                        </Field>
                        <Field label="Requisitos" wide>
                            <textarea
                                name="requirements"
                                className="textarea min-h-24 w-full"
                                defaultValue={editing?.requirements ?? ''}
                            />
                        </Field>
                        <Field label="Benefícios" wide>
                            <textarea
                                name="benefits"
                                className="textarea min-h-24 w-full"
                                placeholder="Ex.: plano de saúde, plano odontológico, vale-alimentação..."
                                defaultValue={editing?.benefits ?? ''}
                            />
                        </Field>
                        {error && (
                            <div className="alert alert-error md:col-span-2">
                                {error}
                            </div>
                        )}
                        <div className="modal-action md:col-span-2">
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
function Field({
    label,
    wide = false,
    children,
}: {
    label: string;
    wide?: boolean;
    children: ReactNode;
}) {
    return (
        <label className={`fieldset ${wide ? 'md:col-span-2' : ''}`}>
            <span className="fieldset-legend">{label}</span>
            {children}
        </label>
    );
}
