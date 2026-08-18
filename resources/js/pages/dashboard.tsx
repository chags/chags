import { Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    CheckCircle2,
    Clock3,
    ShieldCheck,
    Users,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { edit } from '@/routes/profile';

const recentActivity = [
    {
        action: 'Super-admin configurado',
        status: 'Concluído',
        time: 'Agora',
        tone: 'badge-success',
    },
    {
        action: 'Permissões do Spatie ativadas',
        status: 'Ativo',
        time: 'Hoje',
        tone: 'badge-primary',
    },
    {
        action: 'Ambiente local conectado',
        status: 'Online',
        time: 'Hoje',
        tone: 'badge-info',
    },
];

export default function Dashboard() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Dashboard" />

            <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 py-6 md:p-8">
                <section className="hero min-h-56 overflow-hidden rounded-box bg-primary text-primary-content shadow-lg">
                    <div className="hero-content w-full justify-between px-6 py-10 md:px-10">
                        <div className="max-w-2xl">
                            <div className="mb-4 badge badge-soft border-primary-content/25 text-primary-content">
                                <ShieldCheck className="size-3.5" />
                                Área administrativa
                            </div>
                            <h1 className="text-3xl font-bold tracking-tight md:text-4xl">
                                Olá, {auth.user?.name}
                            </h1>
                            <p className="mt-3 max-w-xl text-primary-content/75">
                                Acompanhe o ambiente, gerencie acessos e
                                encontre rapidamente as principais ações do
                                sistema.
                            </p>
                        </div>
                        <ShieldCheck className="hidden size-28 opacity-15 md:block" />
                    </div>
                </section>

                <section className="stats w-full stats-vertical border border-base-300 bg-base-100 shadow-sm lg:stats-horizontal">
                    <div className="stat">
                        <div className="stat-figure text-primary">
                            <Users className="size-7" />
                        </div>
                        <div className="stat-title">Usuários ativos</div>
                        <div className="stat-value text-primary">1</div>
                        <div className="stat-desc">Administrador local</div>
                    </div>
                    <div className="stat">
                        <div className="stat-figure text-success">
                            <CheckCircle2 className="size-7" />
                        </div>
                        <div className="stat-title">Serviços</div>
                        <div className="stat-value text-success">Online</div>
                        <div className="stat-desc">Ambiente operacional</div>
                    </div>
                    <div className="stat">
                        <div className="stat-figure text-secondary">
                            <Activity className="size-7" />
                        </div>
                        <div className="stat-title">Atividade</div>
                        <div className="stat-value text-secondary">100%</div>
                        <div className="stat-desc">Configuração concluída</div>
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-[1.6fr_1fr]">
                    <section className="card border border-base-300 bg-base-100 shadow-sm">
                        <div className="card-body">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="card-title">
                                        Atividade recente
                                    </h2>
                                    <p className="text-sm text-base-content/60">
                                        Últimas alterações importantes do
                                        sistema
                                    </p>
                                </div>
                                <span className="badge badge-outline">
                                    Atualizado agora
                                </span>
                            </div>

                            <div className="mt-2 overflow-x-auto">
                                <table className="table">
                                    <thead>
                                        <tr>
                                            <th>Evento</th>
                                            <th>Status</th>
                                            <th className="text-right">
                                                Quando
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentActivity.map((item) => (
                                            <tr key={item.action}>
                                                <td className="font-medium">
                                                    {item.action}
                                                </td>
                                                <td>
                                                    <span
                                                        className={`badge badge-soft badge-sm ${item.tone}`}
                                                    >
                                                        {item.status}
                                                    </span>
                                                </td>
                                                <td className="text-right text-base-content/60">
                                                    {item.time}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <aside className="card border border-base-300 bg-base-100 shadow-sm">
                        <div className="card-body">
                            <h2 className="card-title">
                                Configuração da conta
                            </h2>
                            <p className="text-sm text-base-content/60">
                                Complete os dados administrativos do seu perfil.
                            </p>

                            <div className="mt-2 flex items-center justify-between text-sm">
                                <span>Progresso</span>
                                <span className="font-semibold">75%</span>
                            </div>
                            <progress
                                className="progress w-full progress-primary"
                                value="75"
                                max="100"
                            />

                            <ul className="mt-2 space-y-3 text-sm">
                                <li className="flex items-center gap-2">
                                    <CheckCircle2 className="size-4 text-success" />
                                    Usuário administrador criado
                                </li>
                                <li className="flex items-center gap-2">
                                    <CheckCircle2 className="size-4 text-success" />
                                    Controle de permissões ativo
                                </li>
                                <li className="flex items-center gap-2 text-base-content/60">
                                    <Clock3 className="size-4" />
                                    Revisar dados do perfil
                                </li>
                            </ul>

                            <div className="mt-3 card-actions">
                                <Link
                                    href={edit()}
                                    className="btn btn-block btn-primary"
                                >
                                    Abrir configurações
                                    <ArrowRight className="size-4" />
                                </Link>
                            </div>
                        </div>
                    </aside>
                </div>
            </main>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
