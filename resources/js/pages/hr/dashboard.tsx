import { Head, Link } from '@inertiajs/react';
import {
    BadgeCheck,
    BriefcaseBusiness,
    Building2,
    ClipboardList,
    FileCheck2,
    FileUser,
    UserRoundSearch,
    Users,
} from 'lucide-react';

type Props = {
    metrics: {
        openJobs: number;
        activeApplications: number;
        activeEmployees: number;
        departments: number;
    };
    recentJobs: Array<{
        id: number;
        title: string;
        department: string | null;
        status: string;
        applications_count: number;
        published_at: string | null;
    }>;
};

const statusLabels: Record<string, string> = {
    draft: 'Rascunho',
    published: 'Publicada',
    paused: 'Pausada',
    closed: 'Encerrada',
};

const managementModules = [
    {
        label: 'Setores',
        description: 'Estrutura e departamentos da empresa',
        icon: Building2,
        href: '/hr/departments',
    },
    {
        label: 'Cargos',
        description: 'Funções profissionais e senioridades',
        icon: BadgeCheck,
        href: '/hr/positions',
    },
    {
        label: 'Vagas',
        description: 'Criação, publicação e encerramento',
        icon: BriefcaseBusiness,
        href: '/hr/jobs',
    },
    {
        label: 'Candidaturas',
        description: 'Triagem e etapas do processo seletivo',
        icon: UserRoundSearch,
        href: '/hr/applications',
    },
    {
        label: 'Avaliações',
        description: 'Pareceres de RH e dos gestores',
        icon: ClipboardList,
        href: '/hr/evaluations',
    },
    {
        label: 'Admissões',
        description: 'Aprovação e entrada de colaboradores',
        icon: FileCheck2,
    },
];

export default function HrDashboard({ metrics, recentJobs }: Props) {
    return (
        <>
            <Head title="Recursos Humanos" />
            <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 py-6 md:p-8">
                <section>
                    <p className="text-sm font-semibold text-primary">
                        Gestão de pessoas
                    </p>
                    <h1 className="mt-1 text-3xl font-bold">
                        Recursos Humanos
                    </h1>
                    <p className="mt-2 text-base-content/60">
                        Acompanhe vagas, candidaturas, colaboradores e a
                        estrutura organizacional.
                    </p>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        icon={BriefcaseBusiness}
                        label="Vagas abertas"
                        value={metrics.openJobs}
                        tone="text-primary"
                    />
                    <MetricCard
                        icon={FileUser}
                        label="Candidaturas ativas"
                        value={metrics.activeApplications}
                        tone="text-secondary"
                    />
                    <MetricCard
                        icon={Users}
                        label="Colaboradores ativos"
                        value={metrics.activeEmployees}
                        tone="text-success"
                    />
                    <MetricCard
                        icon={Building2}
                        label="Setores ativos"
                        value={metrics.departments}
                        tone="text-info"
                    />
                </section>

                <section className="card border border-base-300 bg-base-100 shadow-sm">
                    <div className="card-body">
                        <div>
                            <h2 className="card-title">Controles do RH</h2>
                            <p className="mt-1 text-sm text-base-content/60">
                                Acesse os cadastros e fluxos do departamento.
                            </p>
                        </div>
                        <div className="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {managementModules.map((module) =>
                                module.href ? (
                                    <Link
                                        key={module.label}
                                        href={module.href}
                                        className="btn h-auto min-h-14 justify-start gap-2 border-primary/30 bg-primary/5 px-3 py-2 text-left text-base-content shadow-md shadow-primary/20 disabled:text-base-content disabled:opacity-100 dark:bg-primary/10 dark:shadow-primary/15"
                                    >
                                        <module.icon className="size-5 shrink-0 text-primary drop-shadow-[0_0_6px_currentColor]" />
                                        <span>
                                            <span className="block font-bold text-base-content">
                                                {module.label}
                                            </span>
                                            <span className="block text-[11px] leading-tight font-medium text-base-content/80">
                                                {module.description}
                                            </span>
                                        </span>
                                    </Link>
                                ) : (
                                    <button
                                        key={module.label}
                                        type="button"
                                        disabled
                                        title={`${module.label}: formulário em implementação`}
                                        className="btn h-auto min-h-14 justify-start gap-2 border-primary/30 bg-primary/5 px-3 py-2 text-left text-base-content shadow-md shadow-primary/20 disabled:text-base-content disabled:opacity-100 dark:bg-primary/10 dark:shadow-primary/15"
                                    >
                                        <module.icon className="size-5 shrink-0 text-primary drop-shadow-[0_0_6px_currentColor]" />
                                        <span>
                                            <span className="block font-bold text-base-content">
                                                {module.label}
                                            </span>
                                            <span className="block text-[11px] leading-tight font-medium text-base-content/80">
                                                {module.description}
                                            </span>
                                        </span>
                                    </button>
                                ),
                            )}
                        </div>
                        <p className="text-xs text-warning">
                            Os acessos serão liberados conforme cada formulário
                            e sua proteção de backend forem concluídos.
                        </p>
                    </div>
                </section>

                <section className="card border border-base-300 bg-base-100 shadow-sm">
                    <div className="card-body">
                        <h2 className="card-title">Vagas recentes</h2>
                        <div className="overflow-x-auto">
                            <table className="table table-zebra">
                                <thead>
                                    <tr>
                                        <th>Vaga</th>
                                        <th>Setor</th>
                                        <th>Status</th>
                                        <th>Candidaturas</th>
                                        <th>Publicação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentJobs.map((job) => (
                                        <tr key={job.id}>
                                            <td className="font-semibold">
                                                {job.title}
                                            </td>
                                            <td>{job.department ?? '—'}</td>
                                            <td>
                                                <span className="badge badge-outline">
                                                    {statusLabels[job.status] ??
                                                        job.status}
                                                </span>
                                            </td>
                                            <td>{job.applications_count}</td>
                                            <td>
                                                {job.published_at
                                                    ? new Intl.DateTimeFormat(
                                                          'pt-BR',
                                                      ).format(
                                                          new Date(
                                                              job.published_at,
                                                          ),
                                                      )
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                    {recentJobs.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="py-10 text-center text-base-content/55"
                                            >
                                                Nenhuma vaga cadastrada.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}

function MetricCard({
    icon: Icon,
    label,
    value,
    tone,
}: {
    icon: typeof Users;
    label: string;
    value: number;
    tone: string;
}) {
    return (
        <div className="stat rounded-box border border-base-300 bg-base-100 shadow-sm">
            <div className={`stat-figure ${tone}`}>
                <Icon className="size-7" />
            </div>
            <div className="stat-title">{label}</div>
            <div className={`stat-value ${tone}`}>{value}</div>
        </div>
    );
}
