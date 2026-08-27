import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Clock3, PencilLine } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';

type TimeEntry = {
    id: number;
    type: string;
    time: string;
    status: 'pending' | 'approved' | 'cancelled';
    reason: string | null;
    source: string;
};
type PendingEntry = { requestId: number; type: string; time: string };
type Day = {
    date: string;
    weekday: number;
    expectedMinutes: number;
    schedule: {
        start: string;
        breakStart: string | null;
        breakEnd: string | null;
        end: string;
    } | null;
    entries: TimeEntry[];
    pendingEntries: PendingEntry[];
    workedMinutes: number;
    excusedMinutes: number;
    balanceMinutes: number;
    accumulatedBalanceMinutes: number;
    occurrence:
        | 'missing'
        | 'incomplete'
        | 'hour_bank_leave'
        | 'holiday'
        | 'medical_leave'
        | 'medical_pending'
        | 'absence_declaration'
        | 'absence_declaration_pending'
        | null;
    dayType:
        | 'workday'
        | 'day_off'
        | 'custom_schedule'
        | 'hour_bank_leave'
        | 'holiday';
    holiday: { name: string; partial: boolean } | null;
    absence: { status: string; type: string } | null;
    adjustmentStatus: 'pending' | 'approved' | 'cancelled' | null;
};

type Props = {
    timeCard: {
        month: string;
        workedMinutes: number;
        expectedMinutes: number;
        monthBalanceMinutes: number;
        currentBalanceMinutes: number;
        days: Day[];
    };
    canRequestAdjustment: boolean;
    employeeName?: string;
    managedView?: boolean;
};

const entryTypes = [
    { type: 'clock_in', label: 'Entrada' },
    { type: 'break_start', label: 'Início do intervalo' },
    { type: 'break_end', label: 'Fim do intervalo' },
    { type: 'clock_out', label: 'Saída' },
] as const;

const entryLabels: Record<string, string> = {
    clock_in: 'Entrada',
    break_start: 'Início do intervalo',
    break_end: 'Final do intervalo',
    clock_out: 'Saída',
    overtime_start: 'Início da hora extra',
    overtime_end: 'Final da hora extra',
};

const minutesToHours = (minutes: number) => {
    const sign = minutes < 0 ? '-' : minutes > 0 ? '+' : '';
    const absolute = Math.abs(minutes);

    return `${sign}${Math.floor(absolute / 60)}:${String(absolute % 60).padStart(2, '0')}`;
};

const dateValue = (date: string) => new Date(`${date}T00:00:00Z`);
const effectiveEntry = (day: Day, type: string) => {
    const entries = day.entries.filter((entry) => entry.type === type);

    return (
        entries.find((entry) => entry.status === 'approved') ??
        entries.find((entry) => entry.status === 'pending') ??
        entries.at(-1)
    );
};
const entryStatusClass = {
    approved: 'badge-success',
    pending: 'badge-warning',
    cancelled: 'badge-error',
};

export default function TimeCardIndex({
    timeCard,
    canRequestAdjustment,
    employeeName,
    managedView = false,
}: Props) {
    const dialog = useRef<HTMLDialogElement>(null);
    const [selected, setSelected] = useState<Day | null>(null);
    const [reason, setReason] = useState('');
    const [times, setTimes] = useState<Record<string, string>>({});
    const [error, setError] = useState('');
    const [processing, setProcessing] = useState(false);

    const changeMonth = (offset: number) => {
        const [year, month] = timeCard.month.split('-').map(Number);
        const target = new Date(Date.UTC(year, month - 1 + offset, 1));
        const value = `${target.getUTCFullYear()}-${String(target.getUTCMonth() + 1).padStart(2, '0')}`;

        router.get(
            managedView
                ? window.location.pathname
                : '/virtual-office/time-card',
            { month: value },
            { preserveState: true },
        );
    };

    const openAdjustment = (day: Day) => {
        setSelected(day);
        setReason('');
        setError('');
        setTimes(
            Object.fromEntries(
                entryTypes.map(({ type }) => [
                    type,
                    effectiveEntry(day, type)?.status === 'approved'
                        ? (effectiveEntry(day, type)?.time ?? '')
                        : '',
                ]),
            ),
        );
        dialog.current?.showModal();
    };

    const submitAdjustment = (event: FormEvent) => {
        event.preventDefault();

        if (!selected) {
            return;
        }

        setProcessing(true);
        setError('');
        router.post(
            '/virtual-office/time-adjustments',
            {
                work_date: selected.date,
                requested_entries: entryTypes.map(({ type }) => ({
                    type,
                    time: times[type],
                })),
                reason,
            },
            {
                preserveScroll: true,
                onSuccess: () => dialog.current?.close(),
                onError: (errors) =>
                    setError(Object.values(errors).flat().join(' ')),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const monthLabel = new Intl.DateTimeFormat('pt-BR', {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${timeCard.month}-01T00:00:00Z`));

    return (
        <>
            <Head
                title={
                    employeeName
                        ? `Cartão de Ponto — ${employeeName}`
                        : 'Cartão de Ponto'
                }
            />
            <main className="app-page gap-6">
                <section className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-semibold text-primary">
                            Escritório Virtual
                        </p>
                        <h1 className="mt-1 text-3xl font-bold">
                            {employeeName
                                ? `Cartão de Ponto — ${employeeName}`
                                : 'Cartão de Ponto'}
                        </h1>
                        <p className="mt-2 text-base-content/60">
                            {managedView
                                ? 'Consulte a jornada e os apontamentos deste colaborador.'
                                : 'Consulte sua jornada e solicite correções quando necessário.'}
                        </p>
                    </div>
                    <div className="join">
                        <button
                            type="button"
                            className="btn join-item btn-outline"
                            aria-label="Mês anterior"
                            onClick={() => changeMonth(-1)}
                        >
                            <ChevronLeft className="size-4" />
                        </button>
                        <span className="btn pointer-events-none join-item min-w-44 capitalize">
                            {monthLabel}
                        </span>
                        <button
                            type="button"
                            className="btn join-item btn-outline"
                            aria-label="Próximo mês"
                            onClick={() => changeMonth(1)}
                        >
                            <ChevronRight className="size-4" />
                        </button>
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Summary
                        label="Horas trabalhadas"
                        value={minutesToHours(timeCard.workedMinutes)}
                    />
                    <Summary
                        label="Horas previstas"
                        value={minutesToHours(timeCard.expectedMinutes)}
                    />
                    <Summary
                        label="Saldo do mês"
                        value={minutesToHours(timeCard.monthBalanceMinutes)}
                    />
                    <Summary
                        label="Banco acumulado"
                        value={minutesToHours(timeCard.currentBalanceMinutes)}
                    />
                </section>

                <section className="card border border-base-300 bg-base-100 shadow-sm">
                    <div className="card-body p-0 sm:p-4">
                        <div className="overflow-x-auto">
                            <table className="table table-zebra">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Jornada</th>
                                        <th>Marcações</th>
                                        <th>Trabalhado</th>
                                        <th>Saldo</th>
                                        <th>Situação</th>
                                        <th aria-label="Ações" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {timeCard.days.map((day) => (
                                        <tr key={day.date}>
                                            <td>
                                                <p className="font-semibold">
                                                    {new Intl.DateTimeFormat(
                                                        'pt-BR',
                                                        {
                                                            day: '2-digit',
                                                            month: '2-digit',
                                                            timeZone: 'UTC',
                                                        },
                                                    ).format(
                                                        dateValue(day.date),
                                                    )}
                                                </p>
                                                <p className="text-xs text-base-content/55 capitalize">
                                                    {new Intl.DateTimeFormat(
                                                        'pt-BR',
                                                        {
                                                            weekday: 'short',
                                                            timeZone: 'UTC',
                                                        },
                                                    ).format(
                                                        dateValue(day.date),
                                                    )}
                                                </p>
                                            </td>
                                            <td className="text-sm whitespace-nowrap">
                                                {day.schedule
                                                    ? `${day.schedule.start}–${day.schedule.end}`
                                                    : 'Folga'}
                                            </td>
                                            <td>
                                                <div className="flex min-w-48 flex-wrap gap-1">
                                                    {entryTypes.map(
                                                        ({ type }) => {
                                                            const entry =
                                                                effectiveEntry(
                                                                    day,
                                                                    type,
                                                                );

                                                            return (
                                                                <span
                                                                    key={type}
                                                                    title={
                                                                        entry?.reason ??
                                                                        entryLabels[
                                                                            type
                                                                        ]
                                                                    }
                                                                    className={`badge badge-sm ${entry ? entryStatusClass[entry.status] : 'badge-ghost'}`}
                                                                >
                                                                    {entry?.time ??
                                                                        '--:--'}
                                                                </span>
                                                            );
                                                        },
                                                    )}
                                                </div>
                                                {day.pendingEntries.length >
                                                    0 && (
                                                    <div className="mt-2 space-y-1">
                                                        {day.pendingEntries.map(
                                                            (entry) => (
                                                                <div
                                                                    key={`${entry.requestId}-${entry.type}`}
                                                                    className="flex items-center gap-2 text-xs"
                                                                >
                                                                    <span className="badge badge-xs badge-warning">
                                                                        Pendente
                                                                    </span>
                                                                    <span className="font-medium">
                                                                        {entryLabels[
                                                                            entry
                                                                                .type
                                                                        ] ??
                                                                            entry.type}
                                                                    </span>
                                                                    <span className="font-mono">
                                                                        {
                                                                            entry.time
                                                                        }
                                                                    </span>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                                {day.entries.some((entry) =>
                                                    entry.type.startsWith(
                                                        'overtime_',
                                                    ),
                                                ) && (
                                                    <div className="mt-2 space-y-1">
                                                        {day.entries
                                                            .filter((entry) =>
                                                                entry.type.startsWith(
                                                                    'overtime_',
                                                                ),
                                                            )
                                                            .map((entry) => (
                                                                <div
                                                                    key={
                                                                        entry.id
                                                                    }
                                                                    className="flex items-center gap-2 text-xs"
                                                                >
                                                                    <span
                                                                        className={`badge badge-xs ${entryStatusClass[entry.status]}`}
                                                                    >
                                                                        Hora
                                                                        extra
                                                                    </span>
                                                                    <span>
                                                                        {
                                                                            entryLabels[
                                                                                entry
                                                                                    .type
                                                                            ]
                                                                        }{' '}
                                                                        {
                                                                            entry.time
                                                                        }
                                                                    </span>
                                                                </div>
                                                            ))}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="font-mono">
                                                {minutesToHours(
                                                    day.workedMinutes,
                                                )}
                                            </td>
                                            <td
                                                className={`font-mono font-semibold ${day.balanceMinutes < 0 ? 'text-error' : day.balanceMinutes > 0 ? 'text-success' : ''}`}
                                            >
                                                {minutesToHours(
                                                    day.balanceMinutes,
                                                )}
                                            </td>
                                            <td>
                                                {day.occurrence ===
                                                'medical_leave' ? (
                                                    <span className="badge badge-sm badge-success">
                                                        Ausência abonada —
                                                        Atestado
                                                    </span>
                                                ) : day.occurrence ===
                                                  'absence_declaration' ? (
                                                    <span className="badge badge-sm badge-success">
                                                        Ausência abonada — Declaração
                                                    </span>
                                                ) : day.occurrence ===
                                                  'absence_declaration_pending' ? (
                                                    <span className="badge badge-sm badge-warning">
                                                        Declaração em análise
                                                    </span>
                                                ) : day.occurrence ===
                                                  'medical_pending' ? (
                                                    <span className="badge badge-sm badge-warning">
                                                        Atestado em análise
                                                    </span>
                                                ) : day.occurrence ===
                                                  'holiday' ? (
                                                    <span className="badge badge-sm badge-info">
                                                        Feriado
                                                        {day.holiday
                                                            ? ` — ${day.holiday.name}`
                                                            : ''}
                                                    </span>
                                                ) : day.occurrence ===
                                                  'hour_bank_leave' ? (
                                                    <span className="badge badge-sm badge-success">
                                                        Folga — Banco de horas
                                                    </span>
                                                ) : day.adjustmentStatus ===
                                                  'pending' ? (
                                                    <span className="badge badge-sm badge-warning">
                                                        Ajuste pendente
                                                    </span>
                                                ) : day.adjustmentStatus ===
                                                  'approved' ? (
                                                    <span className="badge badge-sm badge-success">
                                                        Ajustado
                                                    </span>
                                                ) : day.occurrence ===
                                                  'missing' ? (
                                                    <span className="badge badge-sm badge-error">
                                                        Sem registro
                                                    </span>
                                                ) : day.occurrence ===
                                                  'incomplete' ? (
                                                    <span className="badge badge-sm badge-warning">
                                                        Incompleto
                                                    </span>
                                                ) : day.schedule ? (
                                                    <span className="badge badge-sm badge-success">
                                                        Regular
                                                    </span>
                                                ) : (
                                                    <span className="text-base-content/40">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td>
                                                {canRequestAdjustment &&
                                                    day.schedule &&
                                                    day.adjustmentStatus !==
                                                        'pending' && (
                                                        <button
                                                            type="button"
                                                            className="btn btn-ghost btn-sm"
                                                            title="Solicitar ajuste"
                                                            onClick={() =>
                                                                openAdjustment(
                                                                    day,
                                                                )
                                                            }
                                                        >
                                                            <PencilLine className="size-4" />
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
            </main>

            <dialog
                ref={dialog}
                className="modal"
                onClose={() => setSelected(null)}
            >
                <div className="modal-box max-w-2xl">
                    <form onSubmit={submitAdjustment} className="space-y-5">
                        <div>
                            <h2 className="text-xl font-bold">
                                Solicitar ajuste de ponto
                            </h2>
                            <p className="mt-1 text-sm text-base-content/60">
                                Informe as marcações corretas para{' '}
                                {selected?.date
                                    ? new Intl.DateTimeFormat('pt-BR', {
                                          timeZone: 'UTC',
                                      }).format(dateValue(selected.date))
                                    : ''}
                                .
                            </p>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {entryTypes.map(({ type, label }) => (
                                <label key={type} className="form-control">
                                    <span className="label-text mb-1">
                                        {label}
                                    </span>
                                    <input
                                        type="time"
                                        className="input-bordered input w-full"
                                        required
                                        value={times[type] ?? ''}
                                        onChange={(event) =>
                                            setTimes((current) => ({
                                                ...current,
                                                [type]: event.target.value,
                                            }))
                                        }
                                    />
                                </label>
                            ))}
                        </div>
                        <label className="form-control">
                            <span className="label-text mb-1">
                                Justificativa
                            </span>
                            <textarea
                                className="textarea-bordered textarea min-h-28"
                                minLength={10}
                                maxLength={1000}
                                required
                                value={reason}
                                onChange={(event) =>
                                    setReason(event.target.value)
                                }
                            />
                        </label>
                        {error && (
                            <div className="alert text-sm alert-error">
                                {error}
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
                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={processing}
                            >
                                {processing && (
                                    <span className="loading loading-sm loading-spinner" />
                                )}
                                Enviar solicitação
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

function Summary({ label, value }: { label: string; value: string }) {
    return (
        <div className="stat rounded-box border border-base-300 bg-base-100 shadow-sm">
            <div className="stat-figure text-primary">
                <Clock3 className="size-6" />
            </div>
            <div className="stat-title">{label}</div>
            <div className="stat-value text-2xl">{value}</div>
        </div>
    );
}
