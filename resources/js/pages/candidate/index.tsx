import { Head, Link, router } from '@inertiajs/react';
import { BriefcaseBusiness, Building2, LogOut, MapPin } from 'lucide-react';
import { PublicSiteShell } from '@/components/public-site-shell';

type CandidateApplication = {
    id: number;
    job: {
        title: string;
        slug: string;
        image_url: string | null;
        company: string;
        unit: string;
        department: string;
        workplace_type: string;
        employment_type: string;
        city: string | null;
        state: string | null;
        status: string;
    };
    status: string;
    current_stage: string;
    applied_at: string | null;
};

const workplace: Record<string, string> = {
    onsite: 'Presencial',
    hybrid: 'Híbrido',
    remote: 'Remoto',
};
const statuses: Record<string, string> = {
    active: 'Em andamento',
    rejected: 'Processo encerrado',
    withdrawn: 'Retirada',
    hired: 'Aprovada',
};

export default function CandidateIndex({
    candidate,
    applications,
}: {
    candidate: { firstName: string };
    applications: CandidateApplication[];
}) {
    return (
        <PublicSiteShell>
            <Head title="Minhas candidaturas" />
            <main className="min-h-[70vh] bg-base-200/60">
                <section className="bg-primary px-4 py-12 text-primary-content">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-5">
                        <div>
                            <p className="font-semibold text-primary-content/70">
                                Área do candidato
                            </p>
                            <h1 className="mt-1 text-4xl font-black">
                                Olá, {candidate.firstName}
                            </h1>
                            <p className="mt-2 text-primary-content/75">
                                Acompanhe suas oportunidades e as próximas
                                etapas.
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Link
                                href="/trabalhe-conosco"
                                className="btn border-primary-content/20 bg-primary-content text-primary hover:bg-primary-content/90"
                            >
                                Ver novas vagas
                            </Link>
                            <button
                                type="button"
                                className="btn btn-square border-primary-content/25 bg-transparent text-primary-content hover:bg-primary-content/10"
                                title="Sair"
                                onClick={() => router.post('/candidato/sair')}
                            >
                                <LogOut className="size-5" />
                            </button>
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-4 py-10 lg:px-8">
                    <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        {applications.map((application) => (
                            <article
                                key={application.id}
                                className={`card overflow-hidden border border-base-300 bg-base-100 shadow-md transition ${['published', 'closed'].includes(application.job.status) ? 'hover:-translate-y-1 hover:shadow-xl' : 'cursor-not-allowed opacity-55 grayscale'}`}
                            >
                                {application.job.image_url ? (
                                    <img
                                        src={application.job.image_url}
                                        alt=""
                                        className="aspect-square w-full object-cover"
                                    />
                                ) : (
                                    <div className="grid aspect-square place-items-center bg-primary/10">
                                        <BriefcaseBusiness className="size-16 text-primary/35" />
                                    </div>
                                )}
                                <div className="card-body">
                                    <div className="flex flex-wrap gap-2">
                                        <span
                                            className={`badge ${application.status === 'hired' ? 'badge-success' : application.status === 'active' ? 'badge-info' : 'badge-ghost'}`}
                                        >
                                            {statuses[application.status] ??
                                                application.status}
                                        </span>
                                        <span className="badge badge-outline">
                                            {workplace[
                                                application.job.workplace_type
                                            ] ?? application.job.workplace_type}
                                        </span>
                                        {application.job.status ===
                                            'closed' && (
                                            <span className="badge badge-warning">
                                                Inscrições encerradas
                                            </span>
                                        )}
                                    </div>
                                    <h2 className="mt-2 card-title">
                                        {application.job.title}
                                    </h2>
                                    <p className="flex items-center gap-2 text-sm text-base-content/65">
                                        <Building2 className="size-4" />
                                        {application.job.company} ·{' '}
                                        {application.job.department}
                                    </p>
                                    <p className="flex items-center gap-2 text-sm text-base-content/65">
                                        <MapPin className="size-4" />
                                        {[
                                            application.job.city,
                                            application.job.state,
                                        ]
                                            .filter(Boolean)
                                            .join(' - ') || 'Local a definir'}
                                    </p>
                                    <div className="mt-3 rounded-box bg-base-200 p-3">
                                        <span className="text-xs font-semibold tracking-wide text-base-content/50 uppercase">
                                            Fase atual
                                        </span>
                                        <p className="font-semibold text-primary">
                                            {application.current_stage}
                                        </p>
                                    </div>
                                    <p className="text-xs text-base-content/50">
                                        Inscrição em{' '}
                                        {application.applied_at
                                            ? new Date(
                                                  application.applied_at,
                                              ).toLocaleDateString('pt-BR')
                                            : '—'}
                                    </p>
                                    {['published', 'closed'].includes(
                                        application.job.status,
                                    ) ? (
                                        <Link
                                            href={`/candidato/candidaturas/${application.id}`}
                                            className="btn mt-2 w-full btn-primary"
                                        >
                                            Acompanhar processo
                                        </Link>
                                    ) : (
                                        <button
                                            type="button"
                                            className="btn mt-2 w-full"
                                            disabled
                                        >
                                            Vaga indisponível
                                        </button>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                    {!applications.length && (
                        <div className="mx-auto max-w-xl py-20 text-center">
                            <BriefcaseBusiness className="mx-auto size-14 text-primary/35" />
                            <h2 className="mt-4 text-2xl font-bold">
                                Você ainda não possui candidaturas
                            </h2>
                            <p className="mt-2 text-base-content/60">
                                Conheça nossas oportunidades e encontre seu
                                próximo desafio.
                            </p>
                            <Link
                                href="/trabalhe-conosco"
                                className="btn mt-5 btn-primary"
                            >
                                Consultar vagas abertas
                            </Link>
                        </div>
                    )}
                </section>
            </main>
        </PublicSiteShell>
    );
}
