import { router } from '@inertiajs/react';
import { CheckCircle2, Clock3, FileClock, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';

type EntryType = 'clock_in' | 'break_start' | 'break_end' | 'clock_out';
type AdjustmentType = EntryType | 'overtime_start' | 'overtime_end';
type Status = {
    nextType: EntryType | null;
    entries: Array<{
        type: EntryType;
        time: string;
        status: 'approved' | 'pending' | 'cancelled';
        reason: string | null;
    }>;
    pendingAdjustments: Array<{
        id: number;
        date: string;
        type: AdjustmentType;
        time: string;
    }>;
};
const labels: Record<AdjustmentType, string> = {
    clock_in: 'ENTRADA',
    break_start: 'INÍCIO DO INTERVALO',
    break_end: 'FINAL DO INTERVALO',
    clock_out: 'SAÍDA',
    overtime_start: 'INÍCIO DA HORA EXTRA',
    overtime_end: 'FINAL DA HORA EXTRA',
};
const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';
const statusLabels = {
    approved: 'Aprovada',
    pending: 'Pendente',
    cancelled: 'Cancelada',
};
const statusClasses = {
    approved: 'border-success/40 bg-success/15 text-success',
    pending: 'border-warning/40 bg-warning/15 text-warning',
    cancelled: 'border-error/40 bg-error/15 text-error',
};

export function TimePunchModal() {
    const dialog = useRef<HTMLDialogElement>(null);
    const [now, setNow] = useState(new Date());
    const [status, setStatus] = useState<Status | null>(null);
    const [loading, setLoading] = useState(false);
    const [success, setSuccess] = useState('');
    const [error, setError] = useState('');
    const [adjusting, setAdjusting] = useState(false);
    const [lastStatus, setLastStatus] = useState<
        'approved' | 'pending' | 'cancelled' | null
    >(null);

    useEffect(() => {
        const timer = window.setInterval(() => setNow(new Date()), 1000);

        return () => window.clearInterval(timer);
    }, []);

    const request = async (method: 'GET' | 'POST') => {
        const response = await fetch('/virtual-office/time-punch', {
            method,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message ?? 'Não foi possível registrar o ponto.',
            );
        }

        return data;
    };

    const open = async () => {
        setLoading(true);
        setSuccess('');
        setError('');
        setAdjusting(false);
        setLastStatus(null);
        dialog.current?.showModal();

        try {
            setStatus(await request('GET'));
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Erro ao consultar o ponto.',
            );
        } finally {
            setLoading(false);
        }
    };

    const refreshTimeCard = () => {
        if (window.location.pathname === '/virtual-office/time-card') {
            router.reload({ only: ['timeCard'] });
        }
    };

    const close = () => {
        dialog.current?.close();
        refreshTimeCard();
    };

    const punch = async () => {
        setLoading(true);
        setError('');

        try {
            const data = await request('POST');
            setStatus(data);
            setLastStatus(data.status);
            setSuccess(
                `${data.message} ${labels[data.registeredType as EntryType]} às ${data.registeredAt}.`,
            );
            refreshTimeCard();
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Erro ao registrar o ponto.',
            );
        } finally {
            setLoading(false);
        }
    };

    const requestAdjustment = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setLoading(true);
        setError('');
        const values = Object.fromEntries(new FormData(event.currentTarget));

        try {
            const response = await fetch('/virtual-office/time-adjustments', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    work_date: values.work_date,
                    requested_entries: [
                        { type: values.type, time: values.time },
                    ],
                    reason: values.reason,
                }),
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : data.message,
                );
            }

            setSuccess(data.message);
            setLastStatus('pending');
            setAdjusting(false);
            setStatus(await request('GET'));
            refreshTimeCard();
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Erro ao solicitar o ajuste.',
            );
        } finally {
            setLoading(false);
        }
    };

    const time = new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).format(now);
    const date = new Intl.DateTimeFormat('pt-BR', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(now);

    return (
        <>
            <button
                type="button"
                className="btn gap-2 btn-primary btn-sm sm:btn-md"
                onClick={open}
            >
                <Clock3 className="size-4" />
                <span>Ponto</span>
            </button>
            <dialog ref={dialog} className="modal">
                <div className="modal-box max-h-[calc(100vh-2rem)] overflow-y-auto p-0 sm:max-w-md">
                    <div className="relative bg-gradient-to-br from-primary to-secondary px-6 pt-6 pb-16 text-center text-primary-content">
                        <button
                            type="button"
                            className="btn absolute top-3 right-3 btn-circle btn-ghost btn-sm"
                            onClick={close}
                            aria-label="Fechar"
                        >
                            <X className="size-5" />
                        </button>
                        <p className="capitalize opacity-85">{date}</p>
                        <p className="mt-2 font-mono text-5xl font-bold tracking-tight">
                            {time}
                        </p>
                        <p className="mt-2 text-xs opacity-75">
                            Horário de Brasília
                        </p>
                    </div>
                    <div className="relative -mt-10 px-5 pb-6">
                        <div className="card border border-base-300 bg-base-100 shadow-xl">
                            <div className="card-body items-center text-center">
                                <div className="grid size-16 place-items-center rounded-full bg-primary/10 text-primary ring-8 ring-base-100">
                                    <Clock3 className="size-8" />
                                </div>
                                {adjusting ? (
                                    <form
                                        className="w-full space-y-3 text-left"
                                        onSubmit={requestAdjustment}
                                    >
                                        <div className="text-center">
                                            <p className="text-sm text-base-content/60">
                                                Solicitação sujeita à aprovação
                                            </p>
                                            <h2 className="text-xl font-bold">
                                                Ajuste de ponto
                                            </h2>
                                        </div>
                                        <label className="fieldset">
                                            <span className="fieldset-legend">
                                                Data
                                            </span>
                                            <input
                                                name="work_date"
                                                type="date"
                                                className="input w-full"
                                                max={new Date()
                                                    .toISOString()
                                                    .slice(0, 10)}
                                                required
                                            />
                                        </label>
                                        <label className="fieldset">
                                            <span className="fieldset-legend">
                                                Hora
                                            </span>
                                            <input
                                                name="time"
                                                type="time"
                                                className="input w-full"
                                                required
                                            />
                                        </label>
                                        <label className="fieldset">
                                            <span className="fieldset-legend">
                                                Tipo de batida
                                            </span>
                                            <select
                                                name="type"
                                                className="select w-full"
                                                required
                                                defaultValue=""
                                            >
                                                <option value="" disabled>
                                                    Selecione
                                                </option>
                                                {(
                                                    Object.keys(
                                                        labels,
                                                    ) as AdjustmentType[]
                                                ).map((type) => (
                                                    <option
                                                        key={type}
                                                        value={type}
                                                    >
                                                        {labels[type]}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                        <label className="fieldset">
                                            <span className="fieldset-legend">
                                                Justificativa
                                            </span>
                                            <select
                                                name="reason"
                                                className="select w-full"
                                                required
                                                defaultValue=""
                                            >
                                                <option value="" disabled>
                                                    Selecione uma justificativa
                                                </option>
                                                <option value="Esqueci de bater o ponto">
                                                    Esqueci de bater
                                                </option>
                                                <option value="Problema técnico">
                                                    Problema técnico
                                                </option>
                                                <option value="Falta de internet">
                                                    Falta de internet
                                                </option>
                                                <option value="Cheguei atrasado">
                                                    Cheguei atrasado
                                                </option>
                                                <option value="Outros problemas">
                                                    Outros problemas
                                                </option>
                                            </select>
                                        </label>
                                        {error && (
                                            <div
                                                role="alert"
                                                className="alert text-sm alert-error"
                                            >
                                                <span>{error}</span>
                                            </div>
                                        )}
                                        <div className="grid grid-cols-2 gap-2">
                                            <button
                                                type="button"
                                                className="btn"
                                                onClick={() => {
                                                    setAdjusting(false);
                                                    setError('');
                                                }}
                                            >
                                                Voltar
                                            </button>
                                            <button
                                                className="btn btn-warning"
                                                disabled={loading}
                                            >
                                                {loading && (
                                                    <span className="loading loading-sm loading-spinner" />
                                                )}
                                                Enviar ajuste
                                            </button>
                                        </div>
                                    </form>
                                ) : success ? (
                                    <div className="mt-2 w-full space-y-3">
                                        <div
                                            role="alert"
                                            className={`alert text-left text-sm ${lastStatus === 'cancelled' ? 'alert-error' : lastStatus === 'pending' ? 'alert-warning' : 'alert-success'}`}
                                        >
                                            <CheckCircle2 className="size-5" />
                                            <div>
                                                <p className="font-semibold">
                                                    {lastStatus
                                                        ? statusLabels[
                                                              lastStatus
                                                          ]
                                                        : 'Registrada'}
                                                </p>
                                                <span>{success}</span>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            className="btn w-full btn-primary"
                                            onClick={close}
                                        >
                                            Concluir e fechar
                                        </button>
                                    </div>
                                ) : (
                                    <>
                                        <p className="text-sm text-base-content/60">
                                            Próximo registro
                                        </p>
                                        <h2 className="text-xl font-bold text-primary">
                                            {status?.nextType
                                                ? labels[status.nextType]
                                                : 'JORNADA CONCLUÍDA'}
                                        </h2>
                                    </>
                                )}
                                {!adjusting && error && (
                                    <div
                                        role="alert"
                                        className="mt-2 alert text-left text-sm alert-error"
                                    >
                                        <span>{error}</span>
                                    </div>
                                )}
                                {!adjusting && status?.entries.length ? (
                                    <div className="mt-3 grid w-full gap-2">
                                        {status.entries.map((entry, index) => (
                                            <div
                                                key={`${entry.type}-${entry.time}-${index}`}
                                                className={`flex w-full items-center justify-between gap-4 rounded-box border px-4 py-3 text-left ${statusClasses[entry.status]}`}
                                            >
                                                <div>
                                                    <p className="text-xs font-bold tracking-wide uppercase">
                                                        {labels[entry.type]}
                                                    </p>
                                                    <p className="text-xs opacity-75">
                                                        {
                                                            statusLabels[
                                                                entry.status
                                                            ]
                                                        }
                                                    </p>
                                                </div>
                                                <strong className="font-mono text-xl">
                                                    {entry.time}
                                                </strong>
                                            </div>
                                        ))}
                                    </div>
                                ) : null}
                                {!success && (
                                    <>
                                        <button
                                            type="button"
                                            className={`btn mt-3 w-full btn-primary ${adjusting ? 'hidden' : ''}`}
                                            disabled={
                                                loading || !status?.nextType
                                            }
                                            onClick={punch}
                                        >
                                            {loading && (
                                                <span className="loading loading-sm loading-spinner" />
                                            )}
                                            {status?.nextType
                                                ? `Registrar ${labels[status.nextType].toLocaleLowerCase('pt-BR')}`
                                                : 'Ponto concluído hoje'}
                                        </button>
                                        <button
                                            type="button"
                                            className={`btn mt-1 w-full gap-2 btn-outline ${adjusting ? 'hidden' : ''}`}
                                            onClick={() => {
                                                setAdjusting(true);
                                                setSuccess('');
                                                setError('');
                                            }}
                                        >
                                            <FileClock className="size-4" />{' '}
                                            Ajuste de ponto
                                        </button>
                                    </>
                                )}
                                {!adjusting &&
                                status?.pendingAdjustments.length ? (
                                    <div className="mt-3 w-full rounded-box border border-warning/40 bg-warning/10 p-3 text-left">
                                        <div className="mb-2 flex items-center justify-between">
                                            <strong className="text-sm">
                                                Ajustes solicitados
                                            </strong>
                                            <span className="badge badge-sm badge-warning">
                                                Pendente
                                            </span>
                                        </div>
                                        {status.pendingAdjustments.map(
                                            (item) => (
                                                <p
                                                    key={`${item.id}-${item.type}`}
                                                    className="text-xs text-base-content/70"
                                                >
                                                    {new Date(
                                                        `${item.date}T00:00:00`,
                                                    ).toLocaleDateString(
                                                        'pt-BR',
                                                    )}{' '}
                                                    · {labels[item.type]} ·{' '}
                                                    {item.time}
                                                </p>
                                            ),
                                        )}
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </div>
                <form method="dialog" className="modal-backdrop">
                    <button>Fechar</button>
                </form>
            </dialog>
        </>
    );
}
