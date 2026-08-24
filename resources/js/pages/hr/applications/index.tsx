import { Head, router } from '@inertiajs/react';
import {
    Download,
    Eye,
    FileUser,
    Pencil,
    Search,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { type FormEvent, useRef, useState } from 'react';

type Application = {
    id: number;
    candidate: {
        name: string;
        email: string;
        phone: string | null;
        city: string | null;
        state: string | null;
    };
    job: {
        company_id: number;
        title: string;
        company: string;
        unit: string;
        department: string;
    };
    current_stage_id: number | null;
    current_stage: string | null;
    status: string;
    rejection_message: string | null;
    rejection_internal_notes: string | null;
    source: string | null;
    resume_original_name: string | null;
    resume_size: number | null;
    privacy_consent_at: string | null;
    applied_at: string | null;
    curriculum: {
        extraction_status: 'pending' | 'processing' | 'completed' | 'failed';
        evaluation_status: 'pending' | 'processing' | 'completed' | 'failed';
        score: number | null;
        opinion: string | null;
        recommendation: string | null;
        extracted_data: Record<string, unknown> | null;
        strengths: string[];
        concerns: string[];
        matched_requirements: string[];
        missing_requirements: string[];
        extraction_attempts: number;
        evaluation_attempts: number;
        extraction_error: string | null;
        evaluation_error: string | null;
        extracted_at: string | null;
        evaluated_at: string | null;
        last_attempted_at: string | null;
    } | null;
    disc_assessment: {
        status: 'in_progress' | 'completed';
        current_position: number;
        dominant_profile: string | null;
        label: string | null;
        scores: Record<'D' | 'I' | 'S' | 'C', number | null>;
        started_at: string | null;
        completed_at: string | null;
    } | null;
};

type Stage = { id: number; company_id: number; name: string };

type PaginatedApplications = {
    data: Application[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    applications: PaginatedApplications;
    stages: Stage[];
    filters: { search: string; status: string };
    abilities: { update: boolean; delete: boolean; screen: boolean };
};

const statusLabels: Record<string, string> = {
    active: 'Ativa',
    rejected: 'Reprovada',
    withdrawn: 'Desistência',
    hired: 'Contratada',
};

const statusClasses: Record<string, string> = {
    active: 'badge-info',
    rejected: 'badge-error',
    withdrawn: 'badge-warning',
    hired: 'badge-success',
};

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

const formatDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('pt-BR', {
              dateStyle: 'short',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';

const formatPhone = (value: string | null) => {
    if (!value) return '—';
    const digits = value.replace(/\D/g, '');
    return digits.length === 11
        ? digits.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3')
        : value;
};

export default function ApplicationsIndex({
    applications,
    stages,
    filters,
    abilities,
}: Props) {
    const dialog = useRef<HTMLDialogElement>(null);
    const [selected, setSelected] = useState<Application | null>(null);
    const [editing, setEditing] = useState(false);
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || 'all');
    const [busy, setBusy] = useState(false);
    const [notice, setNotice] = useState<{
        type: 'success' | 'error';
        message: string;
    } | null>(null);

    const applyFilters = (nextStatus = status) => {
        router.get(
            '/hr/applications',
            {
                search: search.trim() || undefined,
                status: nextStatus === 'all' ? undefined : nextStatus,
            },
            { preserveState: true, replace: true },
        );
    };

    const open = (application: Application, edit = false) => {
        setSelected(application);
        setEditing(edit);
        setNotice(null);
        dialog.current?.showModal();
    };

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!selected) return;
        setBusy(true);
        setNotice(null);
        const body = Object.fromEntries(new FormData(event.currentTarget));
        try {
            const response = await fetch(`/hr/applications/${selected.id}`, {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(body),
            });
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
                        : 'Não foi possível atualizar a candidatura.',
            });
        } finally {
            setBusy(false);
        }
    };

    const remove = async (application: Application) => {
        if (!confirm(`Excluir a candidatura de ${application.candidate.name}?`))
            return;
        setBusy(true);
        const response = await fetch(`/hr/applications/${application.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });
        const data = await response.json();
        setBusy(false);
        if (!response.ok) {
            setNotice({
                type: 'error',
                message: data.message ?? 'Não foi possível excluir.',
            });
            return;
        }
        setNotice({ type: 'success', message: data.message });
        router.reload();
    };

    const screenResume = async (application: Application) => {
        setBusy(true);
        setNotice(null);

        try {
            const response = await fetch(
                `/hr/applications/${application.id}/screen`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                },
            );
            const data = await response.json();
            setNotice({
                type: response.ok ? 'success' : 'error',
                message: data.message,
            });
            router.reload({ only: ['applications'] });
        } catch {
            setNotice({
                type: 'error',
                message: 'Não foi possível iniciar a triagem do currículo.',
            });
        } finally {
            setBusy(false);
        }
    };

    const extractResume = async (application: Application) => {
        setBusy(true);
        setNotice(null);
        try {
            const response = await fetch(
                `/hr/applications/${application.id}/extract`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                },
            );
            const data = await response.json();
            setNotice({
                type: response.ok ? 'success' : 'error',
                message: data.message,
            });
            router.reload({ only: ['applications'] });
        } catch {
            setNotice({
                type: 'error',
                message: 'Não foi possível extrair os dados do currículo.',
            });
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Head title="Candidaturas" />
            <main className="app-page gap-6">
                <section>
                    <p className="text-sm font-semibold text-primary">
                        Recursos Humanos
                    </p>
                    <h1 className="text-3xl font-bold">Candidaturas</h1>
                    <p className="text-base-content/60">
                        Faça a triagem, altere etapas e consulte currículos.
                    </p>
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
                            className="grid gap-3 md:grid-cols-[1fr_13rem_auto]"
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
                                    placeholder="Buscar candidato, vaga ou setor"
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
                                {Object.entries(statusLabels).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            <button className="btn btn-primary" type="submit">
                                Buscar
                            </button>
                        </form>

                        <div className="mt-3 overflow-x-auto">
                            <table className="table table-zebra">
                                <thead>
                                    <tr>
                                        <th>Candidato</th>
                                        <th>Vaga</th>
                                        <th>Etapa</th>
                                        <th>Status</th>
                                        <th>Triagem IA</th>
                                        <th>Inscrição</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {applications.data.map((application) => (
                                        <tr key={application.id}>
                                            <td>
                                                <span className="block font-semibold">
                                                    {application.candidate.name}
                                                </span>
                                                <small className="text-base-content/60">
                                                    {
                                                        application.candidate
                                                            .email
                                                    }
                                                </small>
                                            </td>
                                            <td>
                                                <span className="block font-medium">
                                                    {application.job.title}
                                                </span>
                                                <small className="text-base-content/60">
                                                    {application.job.department}
                                                </small>
                                            </td>
                                            <td>
                                                {application.current_stage ??
                                                    'Triagem inicial'}
                                            </td>
                                            <td>
                                                <span
                                                    className={`badge badge-sm ${statusClasses[application.status] ?? 'badge-outline'}`}
                                                >
                                                    {statusLabels[
                                                        application.status
                                                    ] ?? application.status}
                                                </span>
                                            </td>
                                            <td>
                                                {application.curriculum
                                                    ?.evaluation_status ===
                                                'completed' ? (
                                                    <span className="badge badge-sm badge-success">
                                                        {
                                                            application
                                                                .curriculum
                                                                .score
                                                        }
                                                        /100
                                                    </span>
                                                ) : application.curriculum
                                                      ?.extraction_status ===
                                                  'completed' ? (
                                                    <span className="badge badge-sm badge-info">
                                                        Dados extraídos
                                                    </span>
                                                ) : application.curriculum
                                                      ?.extraction_status ===
                                                  'failed' ? (
                                                    <span className="badge badge-sm badge-error">
                                                        Extração falhou
                                                    </span>
                                                ) : (
                                                    <span className="badge badge-sm badge-warning">
                                                        Pendente
                                                    </span>
                                                )}
                                            </td>
                                            <td>
                                                {formatDate(
                                                    application.applied_at,
                                                )}
                                            </td>
                                            <td>
                                                <div className="flex justify-end gap-1">
                                                    <button
                                                        className="btn btn-square btn-ghost btn-sm"
                                                        title="Ver detalhes"
                                                        onClick={() =>
                                                            open(application)
                                                        }
                                                    >
                                                        <Eye className="size-4" />
                                                    </button>
                                                    {abilities.update && (
                                                        <button
                                                            className="btn btn-square btn-ghost text-info btn-sm"
                                                            title="Atualizar"
                                                            onClick={() =>
                                                                open(
                                                                    application,
                                                                    true,
                                                                )
                                                            }
                                                        >
                                                            <Pencil className="size-4" />
                                                        </button>
                                                    )}
                                                    {abilities.screen && (
                                                        <button
                                                            className="btn btn-square btn-ghost text-secondary btn-sm"
                                                            title="Executar triagem por IA"
                                                            disabled={
                                                                busy ||
                                                                application
                                                                    .curriculum
                                                                    ?.extraction_status !==
                                                                    'completed'
                                                            }
                                                            onClick={() =>
                                                                screenResume(
                                                                    application,
                                                                )
                                                            }
                                                        >
                                                            <Sparkles className="size-4" />
                                                        </button>
                                                    )}
                                                    {abilities.delete && (
                                                        <button
                                                            className="btn btn-square btn-ghost text-error btn-sm"
                                                            title="Excluir"
                                                            disabled={busy}
                                                            onClick={() =>
                                                                remove(
                                                                    application,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {!applications.data.length && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="py-12 text-center text-base-content/55"
                                            >
                                                <FileUser className="mx-auto mb-2 size-8 opacity-40" />
                                                Nenhuma candidatura encontrada.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {applications.total > 0 && (
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-base-300 pt-4">
                                <p className="text-sm text-base-content/60">
                                    Exibindo {applications.from}–
                                    {applications.to} de {applications.total}
                                </p>
                                <div className="join">
                                    <button
                                        type="button"
                                        className="btn join-item btn-sm"
                                        disabled={!applications.prev_page_url}
                                        onClick={() =>
                                            applications.prev_page_url &&
                                            router.get(
                                                applications.prev_page_url,
                                                {},
                                                { preserveState: true },
                                            )
                                        }
                                    >
                                        Anterior
                                    </button>
                                    <span className="btn pointer-events-none join-item btn-sm">
                                        Página {applications.current_page} de{' '}
                                        {applications.last_page}
                                    </span>
                                    <button
                                        type="button"
                                        className="btn join-item btn-sm"
                                        disabled={!applications.next_page_url}
                                        onClick={() =>
                                            applications.next_page_url &&
                                            router.get(
                                                applications.next_page_url,
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
                    {selected && (
                        <>
                            <h2 className="text-xl font-bold">
                                {selected.candidate.name}
                            </h2>
                            <p className="text-sm text-base-content/60">
                                {selected.job.title} · {selected.job.department}
                            </p>

                            <div className="mt-5 grid gap-3 rounded-box bg-base-200 p-4 text-sm sm:grid-cols-2">
                                <Detail
                                    label="E-mail"
                                    value={selected.candidate.email}
                                />
                                <Detail
                                    label="Telefone"
                                    value={formatPhone(
                                        selected.candidate.phone,
                                    )}
                                />
                                <Detail
                                    label="Localidade"
                                    value={
                                        [
                                            selected.candidate.city,
                                            selected.candidate.state,
                                        ]
                                            .filter(Boolean)
                                            .join(' - ') || '—'
                                    }
                                />
                                <Detail
                                    label="Unidade"
                                    value={`${selected.job.company} · ${selected.job.unit}`}
                                />
                                <Detail
                                    label="Inscrição"
                                    value={formatDate(selected.applied_at)}
                                />
                                <Detail
                                    label="Consentimento LGPD"
                                    value={formatDate(
                                        selected.privacy_consent_at,
                                    )}
                                />
                            </div>

                            {selected.resume_original_name && (
                                <a
                                    href={`/hr/applications/${selected.id}/resume`}
                                    className="btn mt-4 btn-outline btn-sm"
                                >
                                    <Download className="size-4" />
                                    Baixar currículo
                                </a>
                            )}

                            <div className="mt-4 rounded-box border border-base-300 p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h3 className="font-bold">
                                        Fase 1 — Extração dos dados
                                    </h3>
                                    {selected.curriculum?.extraction_status ===
                                    'completed' ? (
                                        <span className="badge badge-success">
                                            Dados armazenados
                                        </span>
                                    ) : selected.curriculum
                                          ?.extraction_status === 'failed' ? (
                                        <span className="badge badge-error">
                                            Extração falhou
                                        </span>
                                    ) : (
                                        <span className="badge badge-warning">
                                            Pendente
                                        </span>
                                    )}
                                </div>
                                {selected.curriculum?.extraction_error && (
                                    <p className="mt-3 text-sm text-error">
                                        {selected.curriculum.extraction_error}
                                    </p>
                                )}
                                <p className="mt-2 text-xs text-base-content/55">
                                    Tentativas:{' '}
                                    {selected.curriculum?.extraction_attempts ??
                                        0}{' '}
                                    · Extraído em:{' '}
                                    {formatDate(
                                        selected.curriculum?.extracted_at ??
                                            null,
                                    )}
                                </p>
                                {abilities.screen && (
                                    <button
                                        type="button"
                                        className="btn mt-3 btn-outline btn-sm"
                                        disabled={busy}
                                        onClick={() => extractResume(selected)}
                                    >
                                        {selected.curriculum
                                            ?.extraction_status === 'completed'
                                            ? 'Reextrair dados'
                                            : 'Extrair dados'}
                                    </button>
                                )}
                            </div>

                            <div className="mt-4 rounded-box border border-base-300 p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h3 className="font-bold">
                                        Fase 2 — Avaliação pela IA
                                    </h3>
                                    {selected.curriculum?.evaluation_status ===
                                    'completed' ? (
                                        <span className="badge badge-success">
                                            Nota {selected.curriculum.score}/100
                                        </span>
                                    ) : selected.curriculum
                                          ?.evaluation_status === 'failed' ? (
                                        <span className="badge badge-error">
                                            Avaliação falhou
                                        </span>
                                    ) : (
                                        <span className="badge badge-warning">
                                            Aguardando avaliação
                                        </span>
                                    )}
                                </div>
                                {selected.curriculum?.opinion && (
                                    <p className="mt-3 text-sm">
                                        {selected.curriculum.opinion}
                                    </p>
                                )}
                                {selected.curriculum?.evaluation_error && (
                                    <p className="mt-3 text-sm text-error">
                                        {selected.curriculum.evaluation_error}
                                    </p>
                                )}
                                <p className="mt-2 text-xs text-base-content/55">
                                    Tentativas:{' '}
                                    {selected.curriculum?.evaluation_attempts ??
                                        0}{' '}
                                    · Avaliado em:{' '}
                                    {formatDate(
                                        selected.curriculum?.evaluated_at ??
                                            null,
                                    )}
                                </p>
                                {abilities.screen && (
                                    <button
                                        type="button"
                                        className="btn mt-3 btn-secondary btn-sm"
                                        disabled={
                                            busy ||
                                            selected.curriculum
                                                ?.extraction_status !==
                                                'completed'
                                        }
                                        onClick={() => screenResume(selected)}
                                    >
                                        <Sparkles className="size-4" />
                                        {selected.curriculum
                                            ?.evaluation_status === 'completed'
                                            ? 'Refazer avaliação'
                                            : 'Avaliar com IA'}
                                    </button>
                                )}
                            </div>

                            <div className="mt-4 rounded-box border border-base-300 p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h3 className="font-bold">
                                        Fase 3 — Teste comportamental DISC
                                    </h3>
                                    {selected.disc_assessment?.status ===
                                    'completed' ? (
                                        <span className="badge badge-success">
                                            {selected.disc_assessment.label ??
                                                `Perfil ${selected.disc_assessment.dominant_profile}`}
                                        </span>
                                    ) : selected.disc_assessment?.status ===
                                      'in_progress' ? (
                                        <span className="badge badge-warning">
                                            Em andamento —{' '}
                                            {selected.disc_assessment
                                                .current_position ?? 0}
                                            /20
                                        </span>
                                    ) : selected.current_stage
                                          ?.toLocaleLowerCase('pt-BR')
                                          .includes('disc') ? (
                                        <span className="badge badge-warning">
                                            Aguardando o candidato
                                        </span>
                                    ) : (
                                        <span className="badge badge-ghost">
                                            Ainda não liberado
                                        </span>
                                    )}
                                </div>
                                <p className="mt-2 text-sm text-base-content/65">
                                    O teste é realizado uma única vez pelo
                                    candidato no portal institucional.
                                </p>
                                {selected.disc_assessment?.status ===
                                    'completed' && (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {Object.entries(
                                            selected.disc_assessment.scores,
                                        ).map(([dimension, score]) => (
                                            <span
                                                key={dimension}
                                                className="badge badge-outline"
                                            >
                                                {dimension}: {score ?? 0}
                                            </span>
                                        ))}
                                    </div>
                                )}
                                <p className="mt-2 text-xs text-base-content/55">
                                    Iniciado em:{' '}
                                    {formatDate(
                                        selected.disc_assessment?.started_at ??
                                            null,
                                    )}{' '}
                                    · Concluído em:{' '}
                                    {formatDate(
                                        selected.disc_assessment
                                            ?.completed_at ?? null,
                                    )}
                                </p>
                            </div>

                            {editing ? (
                                <form
                                    onSubmit={submit}
                                    className="mt-5 grid gap-4"
                                >
                                    <label className="fieldset">
                                        <span className="fieldset-legend">
                                            Status
                                        </span>
                                        <select
                                            name="status"
                                            className="select w-full"
                                            defaultValue={selected.status}
                                            required
                                        >
                                            {Object.entries(statusLabels).map(
                                                ([value, label]) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </label>
                                    <label className="fieldset">
                                        <span className="fieldset-legend">
                                            Etapa do processo
                                        </span>
                                        <select
                                            name="current_stage_id"
                                            className="select w-full"
                                            defaultValue={
                                                selected.current_stage_id ?? ''
                                            }
                                        >
                                            <option value="">
                                                Triagem inicial
                                            </option>
                                            {stages
                                                .filter(
                                                    (stage) =>
                                                        stage.company_id ===
                                                        selected.job.company_id,
                                                )
                                                .map((stage) => (
                                                    <option
                                                        key={stage.id}
                                                        value={stage.id}
                                                    >
                                                        {stage.name}
                                                    </option>
                                                ))}
                                        </select>
                                    </label>
                                    <label className="fieldset">
                                        <span className="fieldset-legend">
                                            Observação da mudança
                                        </span>
                                        <textarea
                                            name="notes"
                                            className="textarea min-h-24 w-full"
                                            maxLength={2000}
                                        />
                                    </label>
                                    <label className="fieldset">
                                        <span className="fieldset-legend">
                                            Mensagem ao candidato (obrigatória
                                            ao reprovar)
                                        </span>
                                        <textarea
                                            name="rejection_message"
                                            className="textarea min-h-24 w-full"
                                            maxLength={2000}
                                            defaultValue={
                                                selected.rejection_message ?? ''
                                            }
                                            placeholder="Ex.: Agradecemos sua participação. Neste momento, seguiremos com outros perfis..."
                                        />
                                        <span className="label text-xs text-base-content/55">
                                            Esta mensagem será exibida no portal
                                            do candidato.
                                        </span>
                                    </label>
                                    <label className="fieldset">
                                        <span className="fieldset-legend">
                                            Observação interna da reprovação
                                        </span>
                                        <textarea
                                            name="rejection_internal_notes"
                                            className="textarea min-h-24 w-full"
                                            maxLength={5000}
                                            defaultValue={
                                                selected.rejection_internal_notes ??
                                                ''
                                            }
                                            placeholder="Informação confidencial para a equipe de RH."
                                        />
                                        <span className="label text-xs text-warning">
                                            Uso interno: este conteúdo não será
                                            mostrado ao candidato.
                                        </span>
                                    </label>
                                    <div className="modal-action">
                                        <button
                                            type="button"
                                            className="btn btn-ghost"
                                            onClick={() =>
                                                dialog.current?.close()
                                            }
                                        >
                                            Cancelar
                                        </button>
                                        <button
                                            className="btn btn-primary"
                                            disabled={busy}
                                        >
                                            {busy && (
                                                <span className="loading loading-sm loading-spinner" />
                                            )}
                                            Salvar candidatura
                                        </button>
                                    </div>
                                </form>
                            ) : (
                                <div className="modal-action">
                                    <button
                                        className="btn"
                                        onClick={() => dialog.current?.close()}
                                    >
                                        Fechar
                                    </button>
                                </div>
                            )}
                        </>
                    )}
                </div>
                <form method="dialog" className="modal-backdrop">
                    <button>Fechar</button>
                </form>
            </dialog>
        </>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <span className="block text-xs font-semibold text-base-content/55">
                {label}
            </span>
            <span>{value}</span>
        </div>
    );
}
