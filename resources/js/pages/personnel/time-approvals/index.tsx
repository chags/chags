import { Head, router } from '@inertiajs/react';
import { CalendarDays, SlidersHorizontal, X } from 'lucide-react';
import { useRef, useState } from 'react';

type Entry = { type: string; time: string };
type PendingItem = {
    id: number;
    kind: 'adjustment' | 'time_entry';
    date: string;
    entries: Entry[];
    reason: string | null;
    source: string;
    status: 'pending';
};
type Employee = {
    id: number;
    name: string;
    department: string | null;
    pendingCount: number;
    items: PendingItem[];
};
type Props = {
    canApproveTime: boolean;
    employeesWithPending: Employee[];
};

const entryLabels: Record<string, string> = {
    clock_in: 'Entrada',
    break_start: 'Início do intervalo',
    break_end: 'Fim do intervalo',
    clock_out: 'Saída',
    overtime_start: 'Hora extra — Entrada',
    overtime_end: 'Hora extra — Saída',
};

export default function TimeApprovals({
    canApproveTime,
    employeesWithPending,
}: Props) {
    const dialog = useRef<HTMLDialogElement>(null);
    const [employee, setEmployee] = useState<Employee | null>(null);
    const [statuses, setStatuses] = useState<
        Record<string, 'pending' | 'approved' | 'cancelled'>
    >({});
    const [notes, setNotes] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState<string | null>(null);

    const openEmployee = (selected: Employee) => {
        setEmployee(selected);
        setStatuses(
            Object.fromEntries(
                selected.items.map((item) => [
                    `${item.kind}-${item.id}`,
                    item.status,
                ]),
            ),
        );
        setNotes({});
        dialog.current?.showModal();
    };

    const save = async (item: PendingItem) => {
        const key = `${item.kind}-${item.id}`;
        const status = statuses[key] ?? 'pending';
        const reviewNotes = notes[key]?.trim() ?? '';

        if (status === 'pending') {
            window.alert('Selecione Aprovada ou Cancelada.');
            return;
        }
        if (status === 'cancelled' && !reviewNotes) {
            window.alert('Informe o motivo do cancelamento.');
            return;
        }

        const endpoint =
            item.kind === 'adjustment'
                ? `/personnel/time-approvals/${item.id}`
                : `/personnel/time-entries/${item.id}`;
        const csrf = document.querySelector<HTMLMetaElement>(
            'meta[name="csrf-token"]',
        )?.content;
        setProcessing(key);

        try {
            const response = await fetch(endpoint, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf ?? '',
                },
                body: JSON.stringify({
                    decision: status === 'approved' ? 'approve' : 'reject',
                    notes: reviewNotes || null,
                }),
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(
                    data.message ?? 'Não foi possível atualizar o status.',
                );
            }
            dialog.current?.close();
            router.reload({ only: ['employeesWithPending'] });
        } catch (error) {
            window.alert(
                error instanceof Error
                    ? error.message
                    : 'Erro ao atualizar o status.',
            );
        } finally {
            setProcessing(null);
        }
    };

    return (
        <>
            <Head title="Aprovações de ponto" />
            <main className="mx-auto w-full max-w-7xl p-4 lg:p-8">
                <div className="mb-6">
                    <h1 className="text-3xl font-bold">Aprovações de ponto</h1>
                    <p className="mt-1 text-base-content/60">
                        Analise os apontamentos pendentes diretamente pela
                        tabela.
                    </p>
                </div>

                {canApproveTime && (
                    <section className="card border border-base-300 bg-base-100 shadow-sm">
                        <div className="card-body">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="card-title">
                                        Colaboradores com pendências
                                    </h2>
                                    <p className="text-sm text-base-content/60">
                                        Somente pessoas aguardando decisão.
                                    </p>
                                </div>
                                <span className="badge badge-warning">
                                    {employeesWithPending.length}{' '}
                                    colaborador(es)
                                </span>
                            </div>

                            {employeesWithPending.length === 0 ? (
                                <div className="mt-4 alert border border-success/30 bg-success/10">
                                    Nenhum colaborador possui apontamentos
                                    pendentes.
                                </div>
                            ) : (
                                <div className="mt-4 overflow-x-auto">
                                    <table className="table table-zebra">
                                        <thead>
                                            <tr>
                                                <th>Colaborador</th>
                                                <th>Departamento</th>
                                                <th>Pendências</th>
                                                <th className="text-right">
                                                    Ações
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {employeesWithPending.map(
                                                (item) => (
                                                    <tr key={item.id}>
                                                        <td className="font-semibold">
                                                            {item.name}
                                                        </td>
                                                        <td>
                                                            {item.department ??
                                                                'Sem departamento'}
                                                        </td>
                                                        <td>
                                                            <span className="badge badge-warning">
                                                                {
                                                                    item.pendingCount
                                                                }
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div className="flex justify-end gap-2">
                                                                <a
                                                                    href={`/personnel/time-approvals/employees/${item.id}/time-card`}
                                                                    className="btn btn-square btn-ghost btn-sm"
                                                                    title="Abrir cartão do mês atual"
                                                                    aria-label={`Abrir cartão de ${item.name}`}
                                                                >
                                                                    <CalendarDays className="size-4" />
                                                                </a>
                                                                <button
                                                                    type="button"
                                                                    className="btn btn-square btn-primary btn-sm"
                                                                    title="Analisar pendências"
                                                                    aria-label={`Analisar pendências de ${item.name}`}
                                                                    onClick={() =>
                                                                        openEmployee(
                                                                            item,
                                                                        )
                                                                    }
                                                                >
                                                                    <SlidersHorizontal className="size-4" />
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>
                )}
            </main>

            <dialog ref={dialog} className="modal">
                <div className="modal-box max-h-[calc(100vh-2rem)] max-w-3xl overflow-y-auto">
                    <button
                        type="button"
                        className="btn absolute top-3 right-3 btn-circle btn-ghost btn-sm"
                        onClick={() => dialog.current?.close()}
                        aria-label="Fechar"
                    >
                        <X className="size-5" />
                    </button>
                    <h2 className="text-2xl font-bold">
                        {employee?.name ?? 'Analisar apontamentos'}
                    </h2>
                    <p className="mt-1 text-sm text-base-content/60">
                        Escolha o status desejado para cada apontamento.
                    </p>
                    <div className="mt-5 space-y-4">
                        {employee?.items.map((item) => {
                            const key = `${item.kind}-${item.id}`;
                            return (
                                <article
                                    key={key}
                                    className="rounded-box border border-base-300 bg-base-200/50 p-4"
                                >
                                    <div className="flex flex-wrap justify-between gap-3">
                                        <div>
                                            <p className="font-semibold">
                                                {item.kind === 'adjustment'
                                                    ? 'Ajuste manual'
                                                    : 'Batida automática'}
                                            </p>
                                            <p className="text-sm text-base-content/60">
                                                {new Date(
                                                    `${item.date}T12:00:00`,
                                                ).toLocaleDateString('pt-BR')}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {item.entries.map((entry) => (
                                                <span
                                                    key={`${entry.type}-${entry.time}`}
                                                    className="badge h-auto px-3 py-2 badge-warning"
                                                >
                                                    {entryLabels[entry.type] ??
                                                        entry.type}{' '}
                                                    · {entry.time}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                    {item.reason && (
                                        <p className="mt-3 text-sm">
                                            {item.reason}
                                        </p>
                                    )}
                                    <div className="mt-4 grid gap-3 md:grid-cols-[220px_1fr_auto] md:items-end">
                                        <label className="fieldset">
                                            <span className="fieldset-legend">
                                                Status desejado
                                            </span>
                                            <select
                                                className="select w-full"
                                                value={
                                                    statuses[key] ?? 'pending'
                                                }
                                                onChange={(event) =>
                                                    setStatuses((current) => ({
                                                        ...current,
                                                        [key]: event.target
                                                            .value as
                                                            | 'pending'
                                                            | 'approved'
                                                            | 'cancelled',
                                                    }))
                                                }
                                            >
                                                <option value="pending">
                                                    Pendente
                                                </option>
                                                <option value="approved">
                                                    Aprovada
                                                </option>
                                                <option value="cancelled">
                                                    Cancelada
                                                </option>
                                            </select>
                                        </label>
                                        <label className="fieldset">
                                            <span className="fieldset-legend">
                                                Comentário
                                            </span>
                                            <input
                                                className="input w-full"
                                                value={notes[key] ?? ''}
                                                onChange={(event) =>
                                                    setNotes((current) => ({
                                                        ...current,
                                                        [key]: event.target
                                                            .value,
                                                    }))
                                                }
                                                placeholder="Obrigatório ao cancelar"
                                            />
                                        </label>
                                        <button
                                            type="button"
                                            className="btn btn-primary"
                                            disabled={processing === key}
                                            onClick={() => save(item)}
                                        >
                                            Salvar
                                        </button>
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                </div>
                <form method="dialog" className="modal-backdrop">
                    <button>Fechar</button>
                </form>
            </dialog>
        </>
    );
}
