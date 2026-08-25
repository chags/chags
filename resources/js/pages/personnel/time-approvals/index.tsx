import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

type Entry = {
    type: string;
    time: string;
};

type Adjustment = {
    id: number;
    employee: {
        id: number;
        name: string;
        department: string | null;
    };
    workDate: string;
    entries: Entry[];
    reason: string;
    status: 'pending' | 'approved' | 'cancelled';
    reviewNotes: string | null;
    reviewer: string | null;
    reviewedAt: string | null;
    createdAt: string;
};

type Props = {
    adjustments: {
        data: Adjustment[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    employees: Array<{
        id: number;
        name: string;
        hourBankBalance: number;
    }>;
    exceptions: Array<{
        id: number;
        employee: string;
        workDate: string;
        type: 'custom_schedule' | 'hour_bank_leave';
        startTime: string | null;
        breakStartTime: string | null;
        breakEndTime: string | null;
        endTime: string | null;
        reason: string;
        status: string;
        creator: string;
    }>;
    medicalCertificates: Array<{
        id: number;
        employee: string;
        startsOn: string;
        endsOn: string;
        startsAt: string | null;
        endsAt: string | null;
        reason: string;
        status: 'pending' | 'approved' | 'cancelled';
        reviewer: string | null;
        reviewNotes: string | null;
        documentUrl: string;
    }>;
    canApproveTime: boolean;
    pendingTimeEntries: Array<{
        id: number;
        employee: string;
        recordedAt: string;
        type: string;
        source: string;
        reason: string;
    }>;
};

const entryLabels: Record<string, string> = {
    clock_in: 'Entrada',
    break_start: 'Início do intervalo',
    break_end: 'Fim do intervalo',
    clock_out: 'Saída',
    overtime_start: 'Hora extra — Entrada',
    overtime_end: 'Hora extra — Saída',
};

const statusLabels = {
    pending: 'Pendente',
    approved: 'Aprovada',
    cancelled: 'Cancelada',
};

const statusClasses = {
    pending: 'badge-warning',
    approved: 'badge-success',
    cancelled: 'badge-error',
};

const formatBalance = (minutes: number) =>
    `${minutes < 0 ? '-' : ''}${String(Math.floor(Math.abs(minutes) / 60)).padStart(2, '0')}:${String(Math.abs(minutes) % 60).padStart(2, '0')}`;

export default function TimeApprovals({
    adjustments,
    employees,
    exceptions,
    medicalCertificates,
    canApproveTime,
    pendingTimeEntries,
}: Props) {
    const [notes, setNotes] = useState<Record<number, string>>({});
    const [processingId, setProcessingId] = useState<number | null>(null);
    const [exceptionType, setExceptionType] = useState<
        'custom_schedule' | 'hour_bank_leave'
    >('custom_schedule');
    const [exceptionMessage, setExceptionMessage] = useState('');
    const [savingException, setSavingException] = useState(false);
    const [certificateNotes, setCertificateNotes] = useState<
        Record<number, string>
    >({});
    const [entryNotes, setEntryNotes] = useState<Record<number, string>>({});

    const decide = (adjustment: Adjustment, decision: 'approve' | 'reject') => {
        const reviewNotes = notes[adjustment.id]?.trim() ?? '';

        if (decision === 'reject' && !reviewNotes) {
            window.alert('Informe o motivo da rejeição.');

            return;
        }

        setProcessingId(adjustment.id);
        router.patch(
            `/personnel/time-approvals/${adjustment.id}`,
            { decision, notes: reviewNotes || null },
            {
                preserveScroll: true,
                onFinish: () => setProcessingId(null),
            },
        );
    };

    const storeException = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSavingException(true);
        setExceptionMessage('');
        const form = event.currentTarget;
        const values = Object.fromEntries(new FormData(form));
        const csrf = document.querySelector<HTMLMetaElement>(
            'meta[name="csrf-token"]',
        )?.content;

        try {
            const response = await fetch(
                '/personnel/work-schedule-exceptions',
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf ?? '',
                    },
                    body: JSON.stringify({
                        ...values,
                        type: exceptionType,
                        start_time: values.start_time || null,
                        break_start_time: values.break_start_time || null,
                        break_end_time: values.break_end_time || null,
                        end_time: values.end_time || null,
                    }),
                },
            );
            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message ??
                        Object.values(data.errors ?? {})
                            .flat()
                            .join(' '),
                );
            }

            setExceptionMessage(data.message);
            form.reset();
            setExceptionType('custom_schedule');
            router.reload({ only: ['employees', 'exceptions'] });
        } catch (error) {
            setExceptionMessage(
                error instanceof Error
                    ? error.message
                    : 'Não foi possível registrar a exceção.',
            );
        } finally {
            setSavingException(false);
        }
    };

    const reviewCertificate = async (
        id: number,
        decision: 'approve' | 'reject',
    ) => {
        const reviewNotes = certificateNotes[id]?.trim() ?? '';

        if (decision === 'reject' && !reviewNotes) {
            window.alert('Informe o motivo da rejeição.');

            return;
        }

        setProcessingId(id);

        try {
            const csrf = document.querySelector<HTMLMetaElement>(
                'meta[name="csrf-token"]',
            )?.content;
            const response = await fetch(
                `/personnel/medical-certificates/${id}`,
                {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf ?? '',
                    },
                    body: JSON.stringify({
                        decision,
                        notes: reviewNotes || null,
                    }),
                },
            );
            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message ?? 'Não foi possível analisar o atestado.',
                );
            }

            router.reload({ only: ['medicalCertificates'] });
        } catch (error) {
            window.alert(
                error instanceof Error
                    ? error.message
                    : 'Erro ao analisar atestado.',
            );
        } finally {
            setProcessingId(null);
        }
    };

    const reviewPendingEntry = async (
        id: number,
        decision: 'approve' | 'reject',
    ) => {
        const reviewNotes = entryNotes[id]?.trim() ?? '';

        if (decision === 'reject' && !reviewNotes) {
            window.alert('Informe o motivo da rejeição.');

            return;
        }

        setProcessingId(id);

        try {
            const csrf = document.querySelector<HTMLMetaElement>(
                'meta[name="csrf-token"]',
            )?.content;
            const response = await fetch(`/personnel/time-entries/${id}`, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf ?? '',
                },
                body: JSON.stringify({ decision, notes: reviewNotes || null }),
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message ?? 'Não foi possível analisar a batida.',
                );
            }

            router.reload({ only: ['pendingTimeEntries'] });
        } catch (error) {
            window.alert(
                error instanceof Error
                    ? error.message
                    : 'Erro ao analisar batida.',
            );
        } finally {
            setProcessingId(null);
        }
    };

    const cancelException = async (id: number) => {
        const reason = window
            .prompt('Informe o motivo do cancelamento:')
            ?.trim();

        if (!reason) {
            return;
        }

        const csrf = document.querySelector<HTMLMetaElement>(
            'meta[name="csrf-token"]',
        )?.content;
        const response = await fetch(
            `/personnel/work-schedule-exceptions/${id}`,
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf ?? '',
                },
                body: JSON.stringify({ reason }),
            },
        );
        const data = await response.json();

        if (!response.ok) {
            window.alert(
                data.message ?? 'Não foi possível cancelar a exceção.',
            );

            return;
        }

        router.reload({ only: ['employees', 'exceptions'] });
    };

    return (
        <>
            <Head title="Aprovações de ponto" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 lg:p-8">
                <div>
                    <h1 className="text-3xl font-bold">Aprovações de ponto</h1>
                    <p className="mt-1 text-base-content/60">
                        Analise ajustes manuais e mantenha o histórico das
                        decisões.
                    </p>
                </div>

                {canApproveTime && (
                    <section className="card border border-base-300 bg-base-100 shadow-sm">
                        <form className="card-body" onSubmit={storeException}>
                            <div>
                                <h2 className="card-title">
                                    Programar exceção individual
                                </h2>
                                <p className="text-sm text-base-content/60">
                                    Altere apenas o dia do colaborador ou
                                    registre uma folga usando o banco de horas.
                                </p>
                            </div>
                            {exceptionMessage && (
                                <div className="alert alert-info">
                                    {exceptionMessage}
                                </div>
                            )}
                            <div className="grid gap-4 md:grid-cols-3">
                                <label className="fieldset">
                                    <span className="fieldset-legend">
                                        Colaborador
                                    </span>
                                    <select
                                        name="user_id"
                                        className="select w-full"
                                        required
                                    >
                                        <option value="">Selecione</option>
                                        {employees.map((employee) => (
                                            <option
                                                key={employee.id}
                                                value={employee.id}
                                            >
                                                {employee.name} — saldo{' '}
                                                {formatBalance(
                                                    employee.hourBankBalance,
                                                )}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="fieldset">
                                    <span className="fieldset-legend">
                                        Tipo
                                    </span>
                                    <select
                                        className="select w-full"
                                        value={exceptionType}
                                        onChange={(event) =>
                                            setExceptionType(
                                                event.target.value as
                                                    | 'custom_schedule'
                                                    | 'hour_bank_leave',
                                            )
                                        }
                                    >
                                        <option value="custom_schedule">
                                            Horário excepcional
                                        </option>
                                        <option value="hour_bank_leave">
                                            Folga pelo banco de horas
                                        </option>
                                    </select>
                                </label>
                                <label className="fieldset">
                                    <span className="fieldset-legend">
                                        Data
                                    </span>
                                    <input
                                        name="work_date"
                                        type="date"
                                        className="input w-full"
                                        required
                                    />
                                </label>
                            </div>
                            {exceptionType === 'custom_schedule' && (
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    {[
                                        ['start_time', 'Entrada'],
                                        [
                                            'break_start_time',
                                            'Início do intervalo',
                                        ],
                                        ['break_end_time', 'Fim do intervalo'],
                                        ['end_time', 'Saída'],
                                    ].map(([name, label]) => (
                                        <label key={name} className="fieldset">
                                            <span className="fieldset-legend">
                                                {label}
                                            </span>
                                            <input
                                                name={name}
                                                type="time"
                                                className="input w-full"
                                                required={
                                                    name === 'start_time' ||
                                                    name === 'end_time'
                                                }
                                            />
                                        </label>
                                    ))}
                                </div>
                            )}
                            <label className="fieldset">
                                <span className="fieldset-legend">
                                    Justificativa
                                </span>
                                <textarea
                                    name="reason"
                                    className="textarea min-h-24 w-full"
                                    minLength={10}
                                    maxLength={1000}
                                    required
                                />
                            </label>
                            <div className="card-actions justify-end">
                                <button
                                    className="btn btn-primary"
                                    disabled={savingException}
                                >
                                    {savingException
                                        ? 'Salvando…'
                                        : 'Registrar exceção'}
                                </button>
                            </div>
                        </form>
                    </section>
                )}

                {canApproveTime && exceptions.length > 0 && (
                    <section className="card border border-base-300 bg-base-100 shadow-sm">
                        <div className="card-body">
                            <h2 className="card-title">Exceções recentes</h2>
                            <div className="overflow-x-auto">
                                <table className="table table-zebra">
                                    <thead>
                                        <tr>
                                            <th>Colaborador</th>
                                            <th>Data</th>
                                            <th>Tipo</th>
                                            <th>Horário</th>
                                            <th>Responsável</th>
                                            <th />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {exceptions.map((exception) => (
                                            <tr key={exception.id}>
                                                <td>{exception.employee}</td>
                                                <td>
                                                    {new Date(
                                                        `${exception.workDate}T12:00:00`,
                                                    ).toLocaleDateString(
                                                        'pt-BR',
                                                    )}
                                                </td>
                                                <td>
                                                    {exception.type ===
                                                    'hour_bank_leave'
                                                        ? 'Folga — Banco de horas'
                                                        : 'Horário excepcional'}
                                                </td>
                                                <td>
                                                    {exception.startTime &&
                                                    exception.endTime
                                                        ? `${exception.startTime}–${exception.endTime}`
                                                        : 'Sem batidas previstas'}
                                                </td>
                                                <td>{exception.creator}</td>
                                                <td>
                                                    {exception.status ===
                                                        'approved' && (
                                                        <button
                                                            type="button"
                                                            className="btn btn-outline btn-error btn-xs"
                                                            onClick={() =>
                                                                cancelException(
                                                                    exception.id,
                                                                )
                                                            }
                                                        >
                                                            Cancelar
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                )}

                {canApproveTime && pendingTimeEntries.length > 0 && (
                    <section className="space-y-4">
                        <div>
                            <h2 className="text-2xl font-bold">
                                Batidas pendentes
                            </h2>
                            <p className="text-sm text-base-content/60">
                                Batidas automáticas realizadas em folgas ou
                                feriados.
                            </p>
                        </div>
                        {pendingTimeEntries.map((entry) => (
                            <article
                                key={entry.id}
                                className="card border border-warning/40 bg-base-100 shadow-sm"
                            >
                                <div className="card-body gap-3">
                                    <div className="flex flex-wrap justify-between gap-3">
                                        <div>
                                            <h3 className="card-title">
                                                {entry.employee}
                                            </h3>
                                            <p className="text-sm text-base-content/60">
                                                {new Date(
                                                    entry.recordedAt,
                                                ).toLocaleString('pt-BR')}{' '}
                                                ·{' '}
                                                {entryLabels[entry.type] ??
                                                    entry.type}
                                            </p>
                                        </div>
                                        <span className="badge badge-warning">
                                            Pendente
                                        </span>
                                    </div>
                                    <p className="text-sm">
                                        Motivo:{' '}
                                        {entry.reason === 'holiday'
                                            ? 'Batida em feriado'
                                            : entry.reason === 'day_off'
                                              ? 'Batida em folga'
                                              : entry.reason}
                                    </p>
                                    <textarea
                                        className="textarea min-h-20 w-full"
                                        placeholder="Comentário obrigatório para rejeitar"
                                        value={entryNotes[entry.id] ?? ''}
                                        onChange={(event) =>
                                            setEntryNotes((current) => ({
                                                ...current,
                                                [entry.id]: event.target.value,
                                            }))
                                        }
                                    />
                                    <div className="card-actions justify-end">
                                        <button
                                            className="btn btn-outline btn-error"
                                            disabled={processingId === entry.id}
                                            onClick={() =>
                                                reviewPendingEntry(
                                                    entry.id,
                                                    'reject',
                                                )
                                            }
                                        >
                                            Rejeitar
                                        </button>
                                        <button
                                            className="btn btn-success"
                                            disabled={processingId === entry.id}
                                            onClick={() =>
                                                reviewPendingEntry(
                                                    entry.id,
                                                    'approve',
                                                )
                                            }
                                        >
                                            Aprovar
                                        </button>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </section>
                )}

                {medicalCertificates.length > 0 && (
                    <section className="space-y-4">
                        <div>
                            <h2 className="text-2xl font-bold">
                                Atestados médicos
                            </h2>
                            <p className="text-sm text-base-content/60">
                                Documentos privados disponíveis somente para
                                usuários autorizados.
                            </p>
                        </div>
                        {medicalCertificates.map((certificate) => (
                            <article
                                key={certificate.id}
                                className="card border border-base-300 bg-base-100 shadow-sm"
                            >
                                <div className="card-body gap-4">
                                    <div className="flex flex-wrap justify-between gap-3">
                                        <div>
                                            <h3 className="card-title">
                                                {certificate.employee}
                                            </h3>
                                            <p className="text-sm text-base-content/60">
                                                {new Date(
                                                    `${certificate.startsOn}T12:00:00`,
                                                ).toLocaleDateString('pt-BR')}
                                                {' até '}
                                                {new Date(
                                                    `${certificate.endsOn}T12:00:00`,
                                                ).toLocaleDateString('pt-BR')}
                                                {certificate.startsAt &&
                                                certificate.endsAt
                                                    ? ` · ${certificate.startsAt}–${certificate.endsAt}`
                                                    : ' · Dia inteiro'}
                                            </p>
                                        </div>
                                        <span
                                            className={`badge ${statusClasses[certificate.status]}`}
                                        >
                                            {statusLabels[certificate.status]}
                                        </span>
                                    </div>
                                    <p className="rounded-box bg-base-200 p-4">
                                        {certificate.reason}
                                    </p>
                                    <div>
                                        <a
                                            className="link link-primary"
                                            href={certificate.documentUrl}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Consultar documento protegido
                                        </a>
                                    </div>
                                    {certificate.status === 'pending' ? (
                                        <div className="space-y-3">
                                            <textarea
                                                className="textarea min-h-20 w-full"
                                                placeholder="Comentário obrigatório para rejeitar"
                                                maxLength={1000}
                                                value={
                                                    certificateNotes[
                                                        certificate.id
                                                    ] ?? ''
                                                }
                                                onChange={(event) =>
                                                    setCertificateNotes(
                                                        (current) => ({
                                                            ...current,
                                                            [certificate.id]:
                                                                event.target
                                                                    .value,
                                                        }),
                                                    )
                                                }
                                            />
                                            <div className="card-actions justify-end">
                                                <button
                                                    className="btn btn-outline btn-error"
                                                    disabled={
                                                        processingId ===
                                                        certificate.id
                                                    }
                                                    onClick={() =>
                                                        reviewCertificate(
                                                            certificate.id,
                                                            'reject',
                                                        )
                                                    }
                                                >
                                                    Rejeitar
                                                </button>
                                                <button
                                                    className="btn btn-success"
                                                    disabled={
                                                        processingId ===
                                                        certificate.id
                                                    }
                                                    onClick={() =>
                                                        reviewCertificate(
                                                            certificate.id,
                                                            'approve',
                                                        )
                                                    }
                                                >
                                                    Aprovar e abonar
                                                </button>
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="text-sm text-base-content/60">
                                            Analisado por{' '}
                                            {certificate.reviewer ??
                                                'usuário removido'}
                                            {certificate.reviewNotes &&
                                                ` — ${certificate.reviewNotes}`}
                                        </p>
                                    )}
                                </div>
                            </article>
                        ))}
                    </section>
                )}

                {canApproveTime && adjustments.data.length === 0 ? (
                    <div className="alert border border-base-300 bg-base-100">
                        Não há solicitações de ponto disponíveis para análise.
                    </div>
                ) : canApproveTime ? (
                    <div className="space-y-4">
                        {adjustments.data.map((adjustment) => (
                            <section
                                key={adjustment.id}
                                className="card border border-base-300 bg-base-100 shadow-sm"
                            >
                                <div className="card-body gap-4">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h2 className="card-title">
                                                {adjustment.employee.name}
                                            </h2>
                                            <p className="text-sm text-base-content/60">
                                                {adjustment.employee
                                                    .department ??
                                                    'Sem departamento'}{' '}
                                                ·{' '}
                                                {new Date(
                                                    `${adjustment.workDate}T12:00:00`,
                                                ).toLocaleDateString('pt-BR')}
                                            </p>
                                        </div>
                                        <span
                                            className={`badge ${statusClasses[adjustment.status]}`}
                                        >
                                            {statusLabels[adjustment.status]}
                                        </span>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        {adjustment.entries.map((entry) => (
                                            <span
                                                key={`${entry.type}-${entry.time}`}
                                                className="badge h-auto gap-2 badge-outline px-3 py-2"
                                            >
                                                {entryLabels[entry.type] ??
                                                    entry.type}
                                                <strong>{entry.time}</strong>
                                            </span>
                                        ))}
                                    </div>

                                    <div className="rounded-box bg-base-200 p-4">
                                        <p className="text-xs font-semibold tracking-wide text-base-content/55 uppercase">
                                            Justificativa
                                        </p>
                                        <p className="mt-1 whitespace-pre-wrap">
                                            {adjustment.reason}
                                        </p>
                                    </div>

                                    {adjustment.status === 'pending' ? (
                                        <div className="space-y-3">
                                            <label className="form-control">
                                                <span className="label-text mb-2 font-medium">
                                                    Comentário da decisão
                                                </span>
                                                <textarea
                                                    className="textarea-bordered textarea min-h-24 w-full"
                                                    value={
                                                        notes[adjustment.id] ??
                                                        ''
                                                    }
                                                    onChange={(event) =>
                                                        setNotes((current) => ({
                                                            ...current,
                                                            [adjustment.id]:
                                                                event.target
                                                                    .value,
                                                        }))
                                                    }
                                                    maxLength={1000}
                                                    placeholder="Obrigatório para rejeitar"
                                                />
                                            </label>
                                            <div className="card-actions justify-end">
                                                <button
                                                    type="button"
                                                    className="btn btn-outline btn-error"
                                                    disabled={
                                                        processingId ===
                                                        adjustment.id
                                                    }
                                                    onClick={() =>
                                                        decide(
                                                            adjustment,
                                                            'reject',
                                                        )
                                                    }
                                                >
                                                    Rejeitar
                                                </button>
                                                <button
                                                    type="button"
                                                    className="btn btn-success"
                                                    disabled={
                                                        processingId ===
                                                        adjustment.id
                                                    }
                                                    onClick={() =>
                                                        decide(
                                                            adjustment,
                                                            'approve',
                                                        )
                                                    }
                                                >
                                                    Aprovar
                                                </button>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="text-sm text-base-content/65">
                                            Analisada por{' '}
                                            {adjustment.reviewer ??
                                                'usuário removido'}
                                            {adjustment.reviewNotes &&
                                                ` — ${adjustment.reviewNotes}`}
                                        </div>
                                    )}
                                </div>
                            </section>
                        ))}
                    </div>
                ) : null}

                {adjustments.links.length > 3 && (
                    <nav
                        className="join flex justify-center"
                        aria-label="Paginação"
                    >
                        {adjustments.links.map((link) => (
                            <button
                                key={link.label}
                                type="button"
                                className={`btn join-item btn-sm ${link.active ? 'btn-active' : ''}`}
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </main>
        </>
    );
}
