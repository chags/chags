import { Head, Link } from '@inertiajs/react';
import AbsenceDocumentModal from '@/components/absence-document-modal';
import {
    BriefcaseBusiness,
    CalendarDays,
    Clock3,
    TimerReset,
    UserRound,
} from 'lucide-react';

type DaySummary = {
    date: string;
    schedule: {
        start: string;
        breakStart: string | null;
        breakEnd: string | null;
        end: string;
    } | null;
    entries: Array<{ id: number; type: string; time: string }>;
    workedMinutes: number;
    occurrence: string | null;
} | null;

type Props = {
    employee: {
        name: string;
        employeeNumber: string | null;
        department: string | null;
        position: string | null;
    };
    today: DaySummary;
    month: {
        workedMinutes: number;
        expectedMinutes: number;
        balanceMinutes: number;
    };
    hourBankBalanceMinutes: number;
    vacation: {
        accrualStart: string;
        accrualEnd: string;
        availableDays: number;
        scheduledStart: string | null;
        scheduledEnd: string | null;
        status: string;
    } | null;
    pendingAdjustments: number;
    tracksTime: boolean;
    canSubmitAbsenceDocument: boolean;
};

const minutesToHours = (minutes: number) => {
    const sign = minutes < 0 ? '-' : '';
    const absolute = Math.abs(minutes);

    return `${sign}${Math.floor(absolute / 60)}h ${String(absolute % 60).padStart(2, '0')}min`;
};

const dateLabel = (date: string) =>
    new Intl.DateTimeFormat('pt-BR', { timeZone: 'UTC' }).format(
        new Date(`${date}T00:00:00Z`),
    );

export default function VirtualOfficeDashboard({
    employee,
    today,
    month,
    hourBankBalanceMinutes,
    vacation,
    pendingAdjustments,
    tracksTime,
    canSubmitAbsenceDocument,
}: Props) {
    const employmentSummary = [
        employee.position,
        employee.department,
        employee.employeeNumber ? `Matrícula ${employee.employeeNumber}` : null,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <>
            <Head title="Escritório Virtual" />
            <main className="app-page gap-6">
                <section className="rounded-box bg-primary px-6 py-8 text-primary-content shadow-lg md:px-9">
                    <div className="flex flex-wrap items-center justify-between gap-5">
                        <div>
                            <p className="text-sm font-semibold text-primary-content/70">
                                Escritório Virtual
                            </p>
                            <h1 className="mt-1 text-3xl font-bold">
                                Olá, {employee.name}
                            </h1>
                            <p className="mt-2 text-primary-content/75">
                                {employmentSummary ||
                                    'Painel pessoal do colaborador'}
                            </p>
                        </div>
                        <UserRound className="hidden size-24 opacity-20 md:block" />
                    </div>
                </section>

                {tracksTime ? (
                    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <Metric
                            icon={Clock3}
                            label="Trabalhado no mês"
                            value={minutesToHours(month.workedMinutes)}
                            description={`Previsto: ${minutesToHours(month.expectedMinutes)}`}
                        />
                        <Metric
                            icon={TimerReset}
                            label="Banco de horas"
                            value={minutesToHours(hourBankBalanceMinutes)}
                            description={`Movimento no mês: ${minutesToHours(month.balanceMinutes)}`}
                        />
                        <Metric
                            icon={CalendarDays}
                            label="Férias disponíveis"
                            value={`${vacation?.availableDays ?? 0} dias`}
                            description={
                                vacation
                                    ? `${dateLabel(vacation.accrualStart)} a ${dateLabel(vacation.accrualEnd)}`
                                    : 'Sem período cadastrado'
                            }
                        />
                        <Metric
                            icon={BriefcaseBusiness}
                            label="Ajustes pendentes"
                            value={String(pendingAdjustments)}
                            description="Solicitações em análise"
                        />
                    </section>
                ) : (
                    <div className="alert border border-info/25 bg-info/10 text-base-content">
                        <Clock3 className="size-5 text-info" />
                        <span>
                            Você não está configurado para registrar ponto. O
                            acesso ao seu painel pessoal permanece disponível.
                        </span>
                    </div>
                )}

                <section className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                    <div className="card border border-base-300 bg-base-100 shadow-sm">
                        <div className="card-body">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="card-title">
                                        Jornada de hoje
                                    </h2>
                                    <p className="text-sm text-base-content/60">
                                        Horários previstos e marcações
                                        realizadas.
                                    </p>
                                </div>
                                {tracksTime && (
                                    <div className="flex flex-wrap gap-2">
                                        {canSubmitAbsenceDocument && (
                                            <AbsenceDocumentModal />
                                        )}
                                        <Link
                                            href="/virtual-office/time-card"
                                            className="btn btn-primary"
                                        >
                                            Abrir cartão de ponto
                                        </Link>
                                    </div>
                                )}
                            </div>

                            {tracksTime ? (
                                <div className="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                    {[
                                        ['Entrada', 'clock_in'],
                                        ['Início do intervalo', 'break_start'],
                                        ['Fim do intervalo', 'break_end'],
                                        ['Saída', 'clock_out'],
                                    ].map(([label, type]) => (
                                        <div
                                            key={type}
                                            className="rounded-box bg-base-200 p-4"
                                        >
                                            <p className="text-xs text-base-content/55">
                                                {label}
                                            </p>
                                            <p className="mt-1 text-xl font-bold">
                                                {today?.entries.find(
                                                    (entry) =>
                                                        entry.type === type,
                                                )?.time ?? '--:--'}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="mt-3 text-base-content/60">
                                    O controle de jornada não se aplica ao seu
                                    usuário.
                                </p>
                            )}
                            {tracksTime && (
                                <div className="mt-2 flex flex-wrap gap-2 text-sm">
                                    <span className="badge badge-outline">
                                        Total:{' '}
                                        {minutesToHours(
                                            today?.workedMinutes ?? 0,
                                        )}
                                    </span>
                                    {today?.occurrence && (
                                        <span className="badge badge-warning">
                                            Registro incompleto
                                        </span>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="card border border-base-300 bg-base-100 shadow-sm">
                        <div className="card-body">
                            <h2 className="card-title">Próximas férias</h2>
                            {vacation?.scheduledStart ? (
                                <>
                                    <p className="mt-2 text-2xl font-bold text-primary">
                                        {dateLabel(vacation.scheduledStart)}
                                    </p>
                                    <p className="text-sm text-base-content/60">
                                        até{' '}
                                        {vacation.scheduledEnd
                                            ? dateLabel(vacation.scheduledEnd)
                                            : 'data não informada'}
                                    </p>
                                </>
                            ) : (
                                <p className="mt-2 text-base-content/60">
                                    Nenhum período de férias agendado.
                                </p>
                            )}
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
    description,
}: {
    icon: typeof Clock3;
    label: string;
    value: string;
    description: string;
}) {
    return (
        <div className="stat rounded-box border border-base-300 bg-base-100 shadow-sm">
            <div className="stat-figure text-primary">
                <Icon className="size-7" />
            </div>
            <div className="stat-title">{label}</div>
            <div className="stat-value text-2xl text-primary">{value}</div>
            <div className="stat-desc">{description}</div>
        </div>
    );
}
