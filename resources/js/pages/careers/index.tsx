import { Head, Link } from '@inertiajs/react';
import { BriefcaseBusiness, Building2, MapPin } from 'lucide-react';
import { PublicSiteShell } from '@/components/public-site-shell';

type Job = {
    id: number;
    slug: string;
    title: string;
    image_url: string | null;
    company: string;
    unit: string;
    department: string;
    position: string | null;
    workplace_type: string;
    employment_type: string;
    city: string | null;
    state: string | null;
    closes_at: string | null;
    accepting_applications: boolean;
};
const workplace: Record<string, string> = {
    onsite: 'Presencial',
    hybrid: 'Híbrido',
    remote: 'Remoto',
};
export default function CareersIndex({ jobs }: { jobs: Job[] }) {
    return (
        <PublicSiteShell>
            <Head title="Trabalhe Conosco" />
            <main className="bg-base-100">
                <section className="relative overflow-hidden bg-primary px-4 py-20 text-center text-primary-content">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,.18),transparent_42%)]" />
                    <div className="relative mx-auto max-w-3xl">
                        <span className="badge border-primary-content/25 bg-primary-content/10 text-primary-content">
                            Carreiras
                        </span>
                        <h1 className="mt-5 text-4xl font-black sm:text-5xl">
                            Construa o futuro com a gente.
                        </h1>
                        <p className="mx-auto mt-3 max-w-2xl text-primary-content/75">
                            Encontre uma oportunidade para construir soluções e
                            crescer com a nossa equipe.
                        </p>
                    </div>
                </section>
                <section className="mx-auto max-w-7xl p-4 py-14 md:p-8 md:py-16">
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {jobs.map((job) => (
                            <article
                                key={job.id}
                                className="card overflow-hidden border border-base-300 bg-base-100 shadow-md transition duration-300 hover:-translate-y-1 hover:shadow-xl"
                            >
                                {job.image_url ? (
                                    <img
                                        src={job.image_url}
                                        alt={`Vaga ${job.title}`}
                                        className="aspect-square w-full object-cover"
                                    />
                                ) : (
                                    <div className="grid aspect-square w-full place-items-center bg-primary/10">
                                        <BriefcaseBusiness className="size-16 text-primary/40" />
                                    </div>
                                )}
                                <div className="card-body">
                                    <div className="flex gap-2">
                                        <span className="badge badge-primary">
                                            {workplace[job.workplace_type]}
                                        </span>
                                        <span className="badge badge-outline">
                                            {job.employment_type.toUpperCase()}
                                        </span>
                                        {!job.accepting_applications && (
                                            <span className="badge badge-warning">
                                                Inscrições encerradas
                                            </span>
                                        )}
                                    </div>
                                    <h2 className="mt-2 card-title">
                                        {job.title}
                                    </h2>
                                    <p className="flex items-center gap-2 text-sm text-base-content/65">
                                        <Building2 className="size-4" />
                                        {job.department} · {job.unit}
                                    </p>
                                    <p className="flex items-center gap-2 text-sm text-base-content/65">
                                        <MapPin className="size-4" />
                                        {job.city
                                            ? [job.city, job.state]
                                                  .filter(Boolean)
                                                  .join(' - ')
                                            : 'Local a definir'}
                                    </p>
                                    <div className="mt-3 card-actions">
                                        <Link
                                            href={`/trabalhe-conosco/${job.slug}`}
                                            className="btn w-full btn-primary"
                                        >
                                            {job.accepting_applications
                                                ? 'Ver vaga e candidatar-se'
                                                : 'Consultar vaga'}
                                        </Link>
                                    </div>
                                </div>
                            </article>
                        ))}
                        {!jobs.length && (
                            <div className="col-span-full py-16 text-center">
                                <BriefcaseBusiness className="mx-auto size-12 opacity-30" />
                                <h2 className="mt-3 text-xl font-bold">
                                    Nenhuma vaga aberta no momento
                                </h2>
                                <p className="text-base-content/60">
                                    Volte em breve para conferir novas
                                    oportunidades.
                                </p>
                            </div>
                        )}
                    </div>
                </section>
            </main>
        </PublicSiteShell>
    );
}
