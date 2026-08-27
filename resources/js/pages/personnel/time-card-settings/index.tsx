import { Head, router } from '@inertiajs/react';
import { CalendarClock, CalendarPlus, Pencil, Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';

type Day = {
    day_index: number;
    label: string;
    is_workday: boolean;
    start_time: string | null;
    break_start_time: string | null;
    break_end_time: string | null;
    end_time: string | null;
    expected_minutes: number;
};
type Group = {
    id: number;
    name: string;
    description: string | null;
    schedule_type: string;
    weekly_minutes: number;
    entry_tolerance_minutes: number;
    daily_tolerance_minutes: number;
    operational_window_minutes: number;
    daily_overtime_limit_minutes: number;
    requires_overtime_approval: boolean;
    cycle_start_date: string | null;
    active: boolean;
    days: Day[];
    assignments_count: number;
};
type Props = {
    groups: Group[];
    metrics: {
        activeGroups: number;
        totalGroups: number;
    };
    companies: Array<{
        id: number;
        unit_name: string;
        city: string;
        state: string;
    }>;
};

const week = [
    'Segunda',
    'Terça',
    'Quarta',
    'Quinta',
    'Sexta',
    'Sábado',
    'Domingo',
];
const token = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';
const normalizeTime = (value: string | null) =>
    value ? value.slice(0, 5) : null;
const normalizeDays = (days: Day[]): Day[] =>
    days.map((day) => ({
        ...day,
        start_time: normalizeTime(day.start_time),
        break_start_time: normalizeTime(day.break_start_time),
        break_end_time: normalizeTime(day.break_end_time),
        end_time: normalizeTime(day.end_time),
    }));
const defaultDays = (type: string): Day[] => {
    if (type === '12x36') {
        return [
            {
                day_index: 1,
                label: 'Trabalho',
                is_workday: true,
                start_time: '07:00',
                break_start_time: '12:00',
                break_end_time: '13:00',
                end_time: '19:00',
                expected_minutes: 660,
            },
            {
                day_index: 2,
                label: 'Descanso',
                is_workday: false,
                start_time: null,
                break_start_time: null,
                break_end_time: null,
                end_time: null,
                expected_minutes: 0,
            },
        ];
    }

    return week.map((label, index) => ({
        day_index: index + 1,
        label,
        is_workday: index < (type === '6x1' ? 6 : 5),
        start_time: '08:00',
        break_start_time: '12:00',
        break_end_time: '13:00',
        end_time: '17:00',
        expected_minutes: index < (type === '6x1' ? 6 : 5) ? 480 : 0,
    }));
};

export default function TimeCardSettings({
    groups,
    metrics,
    companies,
}: Props) {
    const dialog = useRef<HTMLDialogElement>(null);
    const holidayDialog = useRef<HTMLDialogElement>(null);
    const [editing, setEditing] = useState<Group | null>(null);
    const [type, setType] = useState('5x2');
    const [days, setDays] = useState<Day[]>(defaultDays('5x2'));
    const [partialHoliday, setPartialHoliday] = useState(false);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const open = (group: Group | null = null) => {
        setEditing(group);
        setType(group?.schedule_type ?? '5x2');
        setDays(
            group?.days
                ? normalizeDays(group.days)
                : defaultDays(group?.schedule_type ?? '5x2'),
        );
        setMessage('');
        dialog.current?.showModal();
    };
    const request = async (url: string, method: string, body: unknown) => {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token(),
            },
            body: JSON.stringify(body),
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

        return data;
    };
    const save = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setBusy(true);
        setMessage('');
        const values = Object.fromEntries(new FormData(event.currentTarget));

        try {
            const data = await request(
                editing
                    ? `/personnel/time-card-settings/groups/${editing.id}`
                    : '/personnel/time-card-settings/groups',
                editing ? 'PUT' : 'POST',
                {
                    ...values,
                    schedule_type: type,
                    weekly_minutes: Number(values.weekly_minutes),
                    entry_tolerance_minutes: Number(
                        values.entry_tolerance_minutes,
                    ),
                    daily_tolerance_minutes: Number(
                        values.daily_tolerance_minutes,
                    ),
                    operational_window_minutes: Number(
                        values.operational_window_minutes,
                    ),
                    daily_overtime_limit_minutes: Number(
                        values.daily_overtime_limit_minutes,
                    ),
                    requires_overtime_approval:
                        values.requires_overtime_approval === '1',
                    active: values.active === '1',
                    cycle_start_date: values.cycle_start_date || null,
                    days,
                },
            );
            dialog.current?.close();
            toast.success(data.message, { duration: 18_000 });
            router.reload();
        } catch (error) {
            setMessage(
                error instanceof Error ? error.message : 'Erro ao salvar.',
            );
        } finally {
            setBusy(false);
        }
    };
    const remove = async (group: Group) => {
        if (!window.confirm(`Deseja realmente excluir o grupo “${group.name}”?`)) {
            return;
        }

        setBusy(true);
        try {
            const data = await request(
                `/personnel/time-card-settings/groups/${group.id}`,
                'DELETE',
                {},
            );
            toast.success(data.message, { duration: 18_000 });
            router.reload({ only: ['groups', 'metrics'] });
        } catch (error) {
            toast.error(
                error instanceof Error ? error.message : 'Erro ao excluir.',
                { duration: 18_000 },
            );
        } finally {
            setBusy(false);
        }
    };
    const saveHoliday = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setBusy(true);
        setMessage('');
        const form = event.currentTarget;
        const values = Object.fromEntries(new FormData(form));

        try {
            const data = await request('/personnel/holidays', 'POST', {
                ...values,
                company_id: values.company_id || null,
                state: values.state || null,
                city: values.city || null,
                starts_at: values.starts_at || null,
                ends_at: values.ends_at || null,
            });
            form.reset();
            setPartialHoliday(false);
            holidayDialog.current?.close();
            toast.success(data.message, { duration: 18_000 });
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Erro ao cadastrar feriado.',
                { duration: 18_000 },
            );
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Head title="Configuração do Cartão de Ponto" />
            <main className="app-page gap-6">
                <section className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-semibold text-primary">
                            Setor Pessoal
                        </p>
                        <h1 className="mt-1 text-3xl font-bold">
                            Configuração do Cartão de Ponto
                        </h1>
                        <p className="mt-2 text-base-content/60">
                            Cadastre e mantenha os horários disponíveis para
                            vinculação no cadastro de usuários.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            className="btn btn-outline"
                            onClick={() => {
                                setPartialHoliday(false);
                                holidayDialog.current?.showModal();
                            }}
                        >
                            <CalendarPlus className="size-4" />
                            Cadastrar feriado
                        </button>
                        <button className="btn btn-primary" onClick={() => open()}>
                            <Plus className="size-4" />
                            Novo grupo
                        </button>
                    </div>
                </section>
                <section className="grid gap-4 md:grid-cols-2">
                    <Metric
                        label="Grupos ativos"
                        value={metrics.activeGroups}
                        icon={CalendarClock}
                    />
                    <Metric
                        label="Total de grupos"
                        value={metrics.totalGroups}
                        icon={CalendarClock}
                    />
                </section>
                <section>
                    <div className="card border border-base-300 bg-base-100 shadow-sm">
                        <div className="card-body">
                            <h2 className="card-title">Grupos de jornada</h2>
                            <div className="overflow-x-auto">
                                <table className="table table-zebra">
                                    <thead>
                                        <tr>
                                            <th>Grupo</th>
                                            <th>Escala</th>
                                            <th>Horários</th>
                                            <th>Carga</th>
                                            <th>Status</th>
                                            <th>Usuários</th>
                                            <th className="text-right">
                                                Ações
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {groups.map((group) => (
                                            <tr key={group.id}>
                                                <td>
                                                    <strong>
                                                        {group.name}
                                                    </strong>
                                                    <div className="text-xs text-base-content/55">
                                                        {group.description}
                                                    </div>
                                                </td>
                                                <td>
                                                    <span className="badge badge-outline badge-primary">
                                                        {group.schedule_type}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div className="flex min-w-72 flex-wrap gap-1">
                                                        {group.days
                                                            .filter(
                                                                (day) =>
                                                                    day.is_workday,
                                                            )
                                                            .map((day) => (
                                                                <span
                                                                    key={
                                                                        day.day_index
                                                                    }
                                                                    className="badge h-auto py-1 badge-ghost"
                                                                >
                                                                    {day.label.slice(
                                                                        0,
                                                                        3,
                                                                    )}{' '}
                                                                    ·{' '}
                                                                    {normalizeTime(
                                                                        day.start_time,
                                                                    )}
                                                                    –
                                                                    {normalizeTime(
                                                                        day.break_start_time,
                                                                    )}{' '}
                                                                    /{' '}
                                                                    {normalizeTime(
                                                                        day.break_end_time,
                                                                    )}
                                                                    –
                                                                    {normalizeTime(
                                                                        day.end_time,
                                                                    )}
                                                                </span>
                                                            ))}
                                                    </div>
                                                </td>
                                                <td>
                                                    {Math.floor(
                                                        group.weekly_minutes /
                                                            60,
                                                    )}
                                                    h semanais
                                                </td>
                                                <td>
                                                    <span
                                                        className={`badge ${group.active ? 'badge-success' : 'badge-ghost'}`}
                                                    >
                                                        {group.active
                                                            ? 'Ativo'
                                                            : 'Inativo'}
                                                    </span>
                                                </td>
                                                <td>
                                                    {group.assignments_count}
                                                </td>
                                                <td>
                                                    <div className="flex justify-end gap-1">
                                                        <button
                                                            className="btn btn-square btn-ghost btn-sm"
                                                            title="Editar jornada"
                                                            aria-label={`Editar ${group.name}`}
                                                            onClick={() =>
                                                                open(group)
                                                            }
                                                        >
                                                            <Pencil className="size-4" />
                                                        </button>
                                                        <button
                                                            className="btn btn-square btn-ghost btn-sm text-error"
                                                            title={
                                                                group.assignments_count >
                                                                0
                                                                    ? 'Há usuários vinculados a este grupo'
                                                                    : 'Excluir jornada'
                                                            }
                                                            aria-label={`Excluir ${group.name}`}
                                                            disabled={
                                                                busy ||
                                                                group.assignments_count >
                                                                    0
                                                            }
                                                            onClick={() =>
                                                                remove(group)
                                                            }
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
            <dialog
                ref={holidayDialog}
                className="modal"
                onClose={() => setPartialHoliday(false)}
            >
                <div className="modal-box max-w-2xl">
                    <form onSubmit={saveHoliday} className="space-y-5">
                        <div>
                            <h2 className="text-xl font-bold">
                                Cadastrar feriado
                            </h2>
                            <p className="mt-1 text-sm text-base-content/60">
                                Informe a data e a abrangência do feriado.
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="Nome">
                                <input name="name" className="input w-full" required />
                            </Field>
                            <Field label="Data">
                                <input name="holiday_date" type="date" className="input w-full" required />
                            </Field>
                            <Field label="Abrangência">
                                <select name="scope" className="select w-full" required>
                                    <option value="company">Empresa</option>
                                    <option value="national">Nacional</option>
                                    <option value="state">Estadual</option>
                                    <option value="municipal">Municipal</option>
                                </select>
                            </Field>
                            <Field label="Unidade">
                                <select name="company_id" className="select w-full">
                                    <option value="">Todas as unidades aplicáveis</option>
                                    {companies.map((company) => (
                                        <option key={company.id} value={company.id}>
                                            {company.unit_name} — {company.city}/{company.state}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="UF (opcional)">
                                <input name="state" maxLength={2} className="input w-full uppercase" />
                            </Field>
                            <Field label="Cidade (opcional)">
                                <input name="city" className="input w-full" />
                            </Field>
                            <label className="label cursor-pointer justify-start gap-3 sm:col-span-2">
                                <input
                                    type="checkbox"
                                    className="checkbox checkbox-primary"
                                    checked={partialHoliday}
                                    onChange={(event) =>
                                        setPartialHoliday(event.target.checked)
                                    }
                                />
                                <span>
                                    <strong>Feriado parcial</strong>
                                    <span className="block text-xs text-base-content/55">
                                        Desmarcado, a data inteira será considerada feriado.
                                    </span>
                                </span>
                            </label>
                            {partialHoliday && (
                                <>
                                    <Field label="Feriado começa às">
                                        <input name="starts_at" type="time" className="input w-full" required />
                                    </Field>
                                    <Field label="Feriado termina às">
                                        <input name="ends_at" type="time" className="input w-full" required />
                                    </Field>
                                    <p className="text-xs text-base-content/55 sm:col-span-2">
                                        Para Quarta-feira de Cinzas com expediente a partir das 12h, informe 00:00 até 12:00.
                                    </p>
                                </>
                            )}
                        </div>
                        <div className="modal-action">
                            <button type="button" className="btn btn-ghost" onClick={() => holidayDialog.current?.close()}>
                                Cancelar
                            </button>
                            <button className="btn btn-primary" disabled={busy}>
                                {busy ? 'Salvando…' : 'Salvar feriado'}
                            </button>
                        </div>
                    </form>
                </div>
                <form method="dialog" className="modal-backdrop">
                    <button>Fechar</button>
                </form>
            </dialog>
            <dialog ref={dialog} className="modal">
                <div className="modal-box max-w-5xl">
                    <form onSubmit={save} className="space-y-5">
                        <h2 className="text-xl font-bold">
                            {editing ? 'Editar grupo' : 'Novo grupo de jornada'}
                        </h2>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Field label="Nome">
                                <input
                                    name="name"
                                    className="input w-full"
                                    required
                                    defaultValue={editing?.name}
                                />
                            </Field>
                            <Field label="Tipo de escala">
                                <select
                                    name="schedule_type"
                                    className="select w-full"
                                    value={type}
                                    onChange={(e) => {
                                        setType(e.target.value);
                                        setDays(defaultDays(e.target.value));
                                    }}
                                >
                                    <option value="5x2">5x2 Comercial</option>
                                    <option value="6x1">6x1</option>
                                    <option value="12x36">12x36</option>
                                    <option value="custom">
                                        Personalizada
                                    </option>
                                </select>
                            </Field>
                            <Field label="Carga semanal (min)">
                                <input
                                    name="weekly_minutes"
                                    type="number"
                                    className="input w-full"
                                    defaultValue={
                                        editing?.weekly_minutes ?? 2640
                                    }
                                />
                            </Field>
                            <Field label="Tolerância por marcação">
                                <input
                                    name="entry_tolerance_minutes"
                                    type="number"
                                    className="input w-full"
                                    defaultValue={
                                        editing?.entry_tolerance_minutes ?? 5
                                    }
                                />
                            </Field>
                            <Field label="Tolerância diária">
                                <input
                                    name="daily_tolerance_minutes"
                                    type="number"
                                    className="input w-full"
                                    defaultValue={
                                        editing?.daily_tolerance_minutes ?? 10
                                    }
                                />
                            </Field>
                            <Field label="Janela operacional">
                                <input
                                    name="operational_window_minutes"
                                    type="number"
                                    className="input w-full"
                                    defaultValue={
                                        editing?.operational_window_minutes ??
                                        10
                                    }
                                />
                            </Field>
                            <Field label="Limite extra diário (min)">
                                <input
                                    name="daily_overtime_limit_minutes"
                                    type="number"
                                    className="input w-full"
                                    defaultValue={
                                        editing?.daily_overtime_limit_minutes ??
                                        120
                                    }
                                />
                            </Field>
                            {type === '12x36' && (
                                <Field label="Início do ciclo">
                                    <input
                                        name="cycle_start_date"
                                        type="date"
                                        className="input w-full"
                                        required
                                        defaultValue={editing?.cycle_start_date?.slice(
                                            0,
                                            10,
                                        )}
                                    />
                                </Field>
                            )}
                            <Field label="Descrição">
                                <input
                                    name="description"
                                    className="input w-full"
                                    defaultValue={editing?.description ?? ''}
                                />
                            </Field>
                        </div>
                        <div>
                            <h3 className="font-bold">Dias da escala</h3>
                            <div className="mt-2 overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Dia</th>
                                            <th>Trabalha</th>
                                            <th>Entrada</th>
                                            <th>Intervalo</th>
                                            <th>Retorno</th>
                                            <th>Saída</th>
                                            <th>Minutos</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {days.map((day, index) => (
                                            <tr key={day.day_index}>
                                                <td>{day.label}</td>
                                                <td>
                                                    <input
                                                        type="checkbox"
                                                        className="checkbox checkbox-sm"
                                                        checked={day.is_workday}
                                                        onChange={(e) =>
                                                            setDays((current) =>
                                                                current.map(
                                                                    (d, i) =>
                                                                        i ===
                                                                        index
                                                                            ? {
                                                                                  ...d,
                                                                                  is_workday:
                                                                                      e
                                                                                          .target
                                                                                          .checked,
                                                                                  expected_minutes:
                                                                                      e
                                                                                          .target
                                                                                          .checked
                                                                                          ? d.expected_minutes ||
                                                                                            480
                                                                                          : 0,
                                                                              }
                                                                            : d,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                </td>
                                                {(
                                                    [
                                                        'start_time',
                                                        'break_start_time',
                                                        'break_end_time',
                                                        'end_time',
                                                    ] as const
                                                ).map((key) => (
                                                    <td key={key}>
                                                        <input
                                                            type="time"
                                                            className="input w-28 input-sm"
                                                            disabled={
                                                                !day.is_workday
                                                            }
                                                            value={
                                                                day[key] ?? ''
                                                            }
                                                            onChange={(e) =>
                                                                setDays(
                                                                    (current) =>
                                                                        current.map(
                                                                            (
                                                                                d,
                                                                                i,
                                                                            ) =>
                                                                                i ===
                                                                                index
                                                                                    ? {
                                                                                          ...d,
                                                                                          [key]:
                                                                                              e
                                                                                                  .target
                                                                                                  .value ||
                                                                                              null,
                                                                                      }
                                                                                    : d,
                                                                        ),
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                ))}
                                                <td>
                                                    <input
                                                        type="number"
                                                        className="input w-24 input-sm"
                                                        disabled={
                                                            !day.is_workday
                                                        }
                                                        value={
                                                            day.expected_minutes
                                                        }
                                                        onChange={(e) =>
                                                            setDays((current) =>
                                                                current.map(
                                                                    (d, i) =>
                                                                        i ===
                                                                        index
                                                                            ? {
                                                                                  ...d,
                                                                                  expected_minutes:
                                                                                      Number(
                                                                                          e
                                                                                              .target
                                                                                              .value,
                                                                                      ),
                                                                              }
                                                                            : d,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-5">
                            <label className="label cursor-pointer gap-2">
                                <input
                                    name="requires_overtime_approval"
                                    type="checkbox"
                                    value="1"
                                    className="checkbox"
                                    defaultChecked={
                                        editing?.requires_overtime_approval ??
                                        true
                                    }
                                />
                                Exigir aprovação de hora extra
                            </label>
                            <label className="label cursor-pointer gap-2">
                                <input
                                    name="active"
                                    type="checkbox"
                                    value="1"
                                    className="checkbox"
                                    defaultChecked={editing?.active ?? true}
                                />
                                Grupo ativo
                            </label>
                        </div>
                        {message && (
                            <div className="alert alert-error">{message}</div>
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
                                Salvar grupo
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
function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="fieldset">
            <span className="fieldset-legend">{label}</span>
            {children}
        </label>
    );
}
function Metric({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: number;
    icon: typeof CalendarClock;
}) {
    return (
        <div className="stat rounded-box border border-base-300 bg-base-100 shadow-sm">
            <div className="stat-figure text-primary">
                <Icon className="size-7" />
            </div>
            <div className="stat-title">{label}</div>
            <div className="stat-value text-primary">{value}</div>
        </div>
    );
}
