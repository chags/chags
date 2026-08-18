import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BriefcaseBusiness,
    Check,
    Clock3,
    MapPin,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { PublicSiteShell } from '@/components/public-site-shell';

type DiscResult = {
    profile: string;
    label: string;
    description: string;
    disclaimer: string;
};
type TimelineItem = {
    key: string;
    name: string;
    description: string | null;
    status:
        | 'completed'
        | 'current'
        | 'pending'
        | 'action_required'
        | 'rejected'
        | 'blocked';
    completed_at: string | null;
    action: { type: string; label: string; url: string } | null;
    result?: DiscResult | null;
};
type CandidateApplication = {
    id: number;
    status: string;
    applied_at: string | null;
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
    };
    timeline: TimelineItem[];
    interviews: {id:number;stage:string;starts_at:string;ends_at:string;timezone:string;format:string;location:string|null;meeting_url:string|null;instructions:string|null;response:string}[];
};

export default function CandidateApplicationShow({
    application,
}: {
    application: CandidateApplication;
}) {
    const resultDialog = useRef<HTMLDialogElement>(null);
    const [selectedResult, setSelectedResult] = useState<DiscResult | null>(
        null,
    );
    return (
        <PublicSiteShell>
            <Head title={`Acompanhamento — ${application.job.title}`} />
            <main className="min-h-[70vh] bg-base-200/60 px-4 py-8 lg:px-8">
                <div className="mx-auto max-w-5xl">
                    <Link
                        href="/candidato"
                        className="btn mb-5 btn-ghost btn-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Minhas candidaturas
                    </Link>
                    <section className="card overflow-hidden border border-base-300 bg-base-100 shadow-md sm:card-side">
                        {application.job.image_url ? (
                            <img
                                src={application.job.image_url}
                                alt=""
                                className="aspect-square w-full object-cover sm:w-52"
                            />
                        ) : (
                            <div className="grid aspect-square w-full place-items-center bg-primary/10 sm:w-52">
                                <BriefcaseBusiness className="size-14 text-primary/35" />
                            </div>
                        )}
                        <div className="card-body">
                            <span className="text-sm font-semibold text-primary">
                                Acompanhamento da candidatura
                            </span>
                            <h1 className="text-3xl font-black">
                                {application.job.title}
                            </h1>
                            <p>
                                {application.job.company} ·{' '}
                                {application.job.department}
                            </p>
                            <p className="flex items-center gap-2 text-sm text-base-content/60">
                                <MapPin className="size-4" />
                                {[application.job.city, application.job.state]
                                    .filter(Boolean)
                                    .join(' - ') || 'Local a definir'}
                            </p>
                            <p className="text-xs text-base-content/50">
                                Candidatura enviada em{' '}
                                {application.applied_at
                                    ? new Date(
                                          application.applied_at,
                                      ).toLocaleString('pt-BR')
                                    : '—'}
                            </p>
                        </div>
                    </section>

                    <section className="card mt-6 border border-base-300 bg-base-100 shadow-md">
                        <div className="card-body">
                            <h2 className="card-title text-2xl">
                                Etapas do processo
                            </h2>
                            <p className="text-sm text-base-content/60">
                                Acompanhe o andamento da sua candidatura.
                                Algumas etapas podem variar conforme a
                                oportunidade.
                            </p>
                            <ol className="mt-6 space-y-0">
                                {application.timeline.map((item, index) => {
                                    const completed =
                                        item.status === 'completed';
                                    const current =
                                        item.status === 'current' ||
                                        item.status === 'action_required';
                                    const rejected = item.status === 'rejected';
                                    const blocked = item.status === 'blocked';
                                    return (
                                        <li
                                            key={item.key}
                                            className="relative grid grid-cols-[2.5rem_1fr] gap-4 pb-8 last:pb-0"
                                        >
                                            {index <
                                                application.timeline.length -
                                                    1 && (
                                                <span
                                                    className={`absolute top-9 left-[1.2rem] h-[calc(100%-1.25rem)] w-0.5 ${completed ? 'bg-success' : rejected ? 'bg-error' : 'bg-base-300'}`}
                                                />
                                            )}
                                            <span
                                                className={`relative z-10 grid size-10 place-items-center rounded-full border-2 ${completed ? 'border-success bg-success text-success-content' : rejected ? 'border-error bg-error text-error-content ring-4 ring-error/15' : current ? 'border-primary bg-primary text-primary-content ring-4 ring-primary/15' : 'border-base-300 bg-base-100 text-base-content/35'}`}
                                            >
                                                {completed ? (
                                                    <Check className="size-5" />
                                                ) : (
                                                    <Clock3 className="size-4" />
                                                )}
                                            </span>
                                            <div
                                                className={`rounded-box p-4 ${current ? 'border border-primary/30 bg-primary/5' : rejected ? 'border border-error/30 bg-error/5' : blocked ? 'opacity-45' : ''}`}
                                            >
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h3 className="font-bold">
                                                        {item.name}
                                                    </h3>
                                                    {current && (
                                                        <span
                                                            className={`badge badge-sm ${item.status === 'action_required' ? 'badge-warning' : 'badge-primary'}`}
                                                        >
                                                            {item.status ===
                                                            'action_required'
                                                                ? 'Ação necessária'
                                                                : 'Fase atual'}
                                                        </span>
                                                    )}
                                                    {completed && (
                                                        <span className="badge badge-sm badge-success">
                                                            Concluída
                                                        </span>
                                                    )}
                                                    {rejected && (
                                                        <span className="badge badge-sm badge-error">
                                                            Reprovada
                                                        </span>
                                                    )}
                                                    {blocked && (
                                                        <span className="badge badge-sm badge-ghost">
                                                            Etapa encerrada
                                                        </span>
                                                    )}
                                                </div>
                                                {item.description && (
                                                    <p className="mt-1 text-sm text-base-content/60">
                                                        {item.description}
                                                    </p>
                                                )}
                                                {item.completed_at && (
                                                    <p className="mt-2 text-xs text-base-content/45">
                                                        Concluída em{' '}
                                                        {new Date(
                                                            item.completed_at,
                                                        ).toLocaleString(
                                                            'pt-BR',
                                                        )}
                                                    </p>
                                                )}
                                                {item.action && (
                                                    <Link
                                                        href={item.action.url}
                                                        className="btn mt-3 btn-primary btn-sm"
                                                    >
                                                        {item.action.label}
                                                    </Link>
                                                )}
                                                {item.result && (
                                                    <div className="mt-3 flex flex-wrap items-center gap-2">
                                                        <span className="badge badge-success">
                                                            {item.result.label}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            className="btn btn-link btn-sm"
                                                            onClick={() => {
                                                                setSelectedResult(
                                                                    item.result ??
                                                                        null,
                                                                );
                                                                resultDialog.current?.showModal();
                                                            }}
                                                        >
                                                            Conhecer meu perfil
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>
                        </div>
                    </section>
                    {application.interviews.map((interview) => (
                        <section key={interview.id} className="card mt-6 border border-primary/30 bg-base-100 shadow-md"><div className="card-body"><h2 className="card-title">{interview.stage}</h2><p>{new Date(interview.starts_at).toLocaleString('pt-BR')} · {interview.timezone}</p><p>{interview.format === 'online' ? 'Entrevista online' : interview.location}</p>{interview.instructions&&<p className="text-sm">{interview.instructions}</p>}<div className="card-actions mt-3">{interview.meeting_url&&<a className="btn btn-primary btn-sm" href={interview.meeting_url} target="_blank" rel="noreferrer">Entrar na reunião</a>}{interview.response==='pending'&&<><button className="btn btn-success btn-sm" onClick={()=>router.post(`/candidato/entrevistas/${interview.id}/responder`,{response:'accepted'})}>Confirmar presença</button><button className="btn btn-outline btn-sm" onClick={()=>{const reason=prompt('Informe o motivo e uma sugestão de horário:');if(reason)router.post(`/candidato/entrevistas/${interview.id}/responder`,{response:'reschedule_requested',reason})}}>Solicitar reagendamento</button></>}</div></div></section>
                    ))}
                    <div className="mt-6 alert text-sm alert-info">
                        As avaliações apoiam o processo seletivo, mas as
                        decisões são analisadas pela equipe responsável.
                    </div>
                </div>
            </main>
            <dialog ref={resultDialog} className="modal">
                <div className="modal-box">
                    <h3 className="text-xl font-bold">
                        {selectedResult?.label}
                    </h3>
                    <p className="mt-4 leading-relaxed">
                        {selectedResult?.description}
                    </p>
                    <div className="mt-4 alert text-sm alert-info">
                        {selectedResult?.disclaimer}
                    </div>
                    <div className="modal-action">
                        <button
                            className="btn btn-primary"
                            onClick={() => resultDialog.current?.close()}
                        >
                            Fechar
                        </button>
                    </div>
                </div>
                <form method="dialog" className="modal-backdrop">
                    <button>Fechar</button>
                </form>
            </dialog>
        </PublicSiteShell>
    );
}
