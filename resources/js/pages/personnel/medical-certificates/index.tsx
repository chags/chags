import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, FileCheck2, XCircle } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

type Document = {
    id: number;
    employee: string;
    email: string;
    department: string | null;
    type: 'medical_certificate' | 'absence_declaration';
    startsOn: string;
    endsOn: string;
    startsAt: string | null;
    endsAt: string | null;
    reason: string;
    status: 'pending' | 'approved' | 'cancelled';
    reviewer: string | null;
    reviewNotes: string | null;
    documentUrl: string;
};

type Props = {
    documents: {
        data: Document[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { status: string | null };
};

const statusLabels = {
    pending: 'Pendente',
    approved: 'Aprovado',
    cancelled: 'Rejeitado',
};

const statusClasses = {
    pending: 'badge-warning',
    approved: 'badge-success',
    cancelled: 'badge-error',
};

const formatDate = (date: string) =>
    new Intl.DateTimeFormat('pt-BR', { timeZone: 'UTC' }).format(
        new Date(`${date}T00:00:00Z`),
    );

export default function MedicalCertificates({ documents, filters }: Props) {
    const [notes, setNotes] = useState<Record<number, string>>({});
    const [processing, setProcessing] = useState<number | null>(null);

    const filter = (status: string) => {
        router.get(
            '/personnel/medical-certificates',
            status ? { status } : {},
            { preserveState: true, replace: true },
        );
    };

    const review = async (document: Document, decision: 'approve' | 'reject') => {
        const reviewNotes = notes[document.id]?.trim() ?? '';
        if (decision === 'reject' && !reviewNotes) {
            toast.warning('Informe o motivo da rejeição.', { duration: 18_000 });
            return;
        }

        setProcessing(document.id);
        try {
            const response = await fetch(
                `/personnel/medical-certificates/${document.id}`,
                {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            documentQuery('meta[name="csrf-token"]') ?? '',
                    },
                    body: JSON.stringify({ decision, notes: reviewNotes || null }),
                },
            );
            const result = await response.json();
            if (!response.ok) {
                throw new Error(
                    Object.values(result.errors ?? {}).flat().join(' ') ||
                        result.message,
                );
            }
            toast.success(result.message, { duration: 18_000 });
            router.reload({ only: ['documents'] });
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Não foi possível analisar o documento.',
                { duration: 18_000 },
            );
        } finally {
            setProcessing(null);
        }
    };

    return (
        <>
            <Head title="Aprovar atestados" />
            <main className="app-page gap-6">
                <header>
                    <p className="text-sm font-semibold text-primary">Setor Pessoal</p>
                    <h1 className="mt-1 text-3xl font-bold">Aprovar atestados</h1>
                    <p className="mt-1 text-base-content/60">
                        Confira documentos, analise as justificativas e abone a jornada prevista.
                    </p>
                </header>

                <section className="card border border-base-300 bg-base-100 shadow-sm">
                    <div className="card-body">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="card-title"><FileCheck2 className="size-5" /> Documentos recebidos</h2>
                            <select className="select select-sm" value={filters.status ?? ''} onChange={(event) => filter(event.target.value)}>
                                <option value="">Todos os status</option>
                                <option value="pending">Pendentes</option>
                                <option value="approved">Aprovados</option>
                                <option value="cancelled">Rejeitados</option>
                            </select>
                        </div>

                        {documents.data.length === 0 ? (
                            <div className="mt-4 alert alert-info">Nenhum documento encontrado.</div>
                        ) : (
                            <div className="mt-4 overflow-x-auto">
                                <table className="table table-zebra">
                                    <thead><tr><th>Colaborador</th><th>Período</th><th>Documento</th><th>Status</th><th>Análise</th></tr></thead>
                                    <tbody>
                                        {documents.data.map((item) => (
                                            <tr key={item.id}>
                                                <td><p className="font-semibold">{item.employee}</p><p className="text-xs text-base-content/55">{item.department ?? item.email}</p></td>
                                                <td className="whitespace-nowrap">
                                                    <p>{formatDate(item.startsOn)}{item.endsOn !== item.startsOn ? ` a ${formatDate(item.endsOn)}` : ''}</p>
                                                    {item.startsAt && <p className="text-xs">{item.startsAt}–{item.endsAt}</p>}
                                                </td>
                                                <td><a href={item.documentUrl} target="_blank" rel="noreferrer" className="btn btn-outline btn-sm"><ExternalLink className="size-4" /> Conferir</a></td>
                                                <td><span className={`badge ${statusClasses[item.status]}`}>{statusLabels[item.status]}</span></td>
                                                <td className="min-w-72">
                                                    {item.status === 'pending' ? (
                                                        <div className="space-y-2">
                                                            <p className="text-sm">{item.reason}</p>
                                                            <input className="input input-sm w-full" placeholder="Observação; obrigatória ao rejeitar" value={notes[item.id] ?? ''} onChange={(event) => setNotes((current) => ({ ...current, [item.id]: event.target.value }))} />
                                                            <div className="flex gap-2">
                                                                <button className="btn btn-success btn-sm" disabled={processing === item.id} onClick={() => review(item, 'approve')}><CheckCircle2 className="size-4" /> Aprovar</button>
                                                                <button className="btn btn-error btn-outline btn-sm" disabled={processing === item.id} onClick={() => review(item, 'reject')}><XCircle className="size-4" /> Rejeitar</button>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className="text-sm"><p>{item.reviewer ?? 'Analisado'}</p>{item.reviewNotes && <p className="text-xs text-base-content/55">{item.reviewNotes}</p>}</div>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        <div className="mt-4 flex flex-wrap justify-center gap-1">
                            {documents.links.map((link, index) => link.url ? (
                                <Link key={index} href={link.url} preserveScroll className={`btn btn-sm ${link.active ? 'btn-primary' : 'btn-ghost'}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                            ) : null)}
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}

function documentQuery(selector: string): string | undefined {
    return window.document.querySelector<HTMLMetaElement>(selector)?.content;
}
