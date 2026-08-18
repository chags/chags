import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, CheckCircle2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { PublicSiteShell } from '@/components/public-site-shell';

type Question = {
    id: number;
    position: number;
    prompt: string;
    options: { id: number; code: string; text: string }[];
};
type Result = {
    profile: string;
    label: string;
    description: string;
    scores: Record<string, number>;
    disclaimer: string;
};
type Assessment = {
    status: string;
    current_position: number;
    answers: Record<string, number>;
    result: Result | null;
};
const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

export default function DiscShow({
    application,
    assessment,
    questions,
}: {
    application: { id: number; job_title: string };
    assessment: Assessment | null;
    questions: Question[];
}) {
    const [started, setStarted] = useState(Boolean(assessment));
    const [consent, setConsent] = useState(false);
    const [position, setPosition] = useState(
        Math.max(1, assessment?.current_position ?? 1),
    );
    const [answers, setAnswers] = useState<Record<number, number>>(
        assessment?.answers ?? {},
    );
    const [result, setResult] = useState<Result | null>(
        assessment?.result ?? null,
    );
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const question = questions[position - 1];
    const completed = assessment?.status === 'completed' || Boolean(result);
    const answeredCount = useMemo(() => Object.keys(answers).length, [answers]);

    const start = () => {
        setBusy(true);
        router.post(
            `/candidato/candidaturas/${application.id}/disc/iniciar`,
            { consent },
            {
                onSuccess: () => setStarted(true),
                onError: (errors) => setError(Object.values(errors).join(' ')),
                onFinish: () => setBusy(false),
                preserveScroll: true,
            },
        );
    };
    const persist = async () => {
        const optionId = answers[question.id];
        if (!optionId) {
            setError('Selecione uma alternativa para continuar.');
            return false;
        }
        setBusy(true);
        setError('');
        const response = await fetch(
            `/candidato/candidaturas/${application.id}/disc/respostas/${question.id}`,
            {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: JSON.stringify({ option_id: optionId }),
            },
        );
        const data = await response.json();
        setBusy(false);
        if (!response.ok) {
            setError(data.message ?? 'Não foi possível salvar a resposta.');
            return false;
        }
        return true;
    };
    const complete = async () => {
        if (
            !(await persist()) ||
            !confirm(
                'Concluir o teste? Depois disso, suas respostas não poderão ser alteradas.',
            )
        )
            return;
        setBusy(true);
        setError('');
        const response = await fetch(
            `/candidato/candidaturas/${application.id}/disc/concluir`,
            {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            },
        );
        const data = await response.json();
        setBusy(false);
        if (!response.ok) {
            setError(data.message);
            return;
        }
        setResult(data.result);
    };

    return (
        <PublicSiteShell>
            <Head title="Teste comportamental DISC" />
            <main className="min-h-[70vh] bg-base-200/60 px-4 py-10">
                <div className="mx-auto max-w-3xl">
                    <Link
                        href={`/candidato/candidaturas/${application.id}`}
                        className="btn mb-5 btn-ghost btn-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Voltar ao processo
                    </Link>
                    {!started ? (
                        <section className="card border border-base-300 bg-base-100 shadow-xl">
                            <div className="card-body p-7 sm:p-10">
                                <span className="badge badge-primary">
                                    20 perguntas · aproximadamente 10 minutos
                                </span>
                                <h1 className="mt-3 text-3xl font-black">
                                    Teste comportamental DISC
                                </h1>
                                <p className="text-base-content/65">
                                    Responda de acordo com a forma como você
                                    normalmente age. Não existem respostas
                                    certas ou erradas.
                                </p>
                                <div className="mt-4 alert text-sm alert-info">
                                    O resultado descreve preferências
                                    comportamentais. Não é diagnóstico
                                    psicológico e não determina sozinho uma
                                    decisão no processo seletivo.
                                </div>
                                <label className="mt-4 flex cursor-pointer gap-3">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-primary"
                                        checked={consent}
                                        onChange={(e) =>
                                            setConsent(e.target.checked)
                                        }
                                    />
                                    <span>
                                        Li e estou ciente da finalidade deste
                                        questionário.
                                    </span>
                                </label>
                                {error && (
                                    <div
                                        role="alert"
                                        className="alert alert-error"
                                    >
                                        {error}
                                    </div>
                                )}
                                <button
                                    className="btn mt-4 btn-primary"
                                    disabled={!consent || busy}
                                    onClick={start}
                                >
                                    Começar teste
                                    <ArrowRight className="size-4" />
                                </button>
                            </div>
                        </section>
                    ) : completed && result ? (
                        <section className="card border border-success/30 bg-base-100 shadow-xl">
                            <div className="card-body p-8 text-center">
                                <CheckCircle2 className="mx-auto size-16 text-success" />
                                <span className="mx-auto badge badge-success">
                                    Teste concluído
                                </span>
                                <h1 className="mt-3 text-3xl font-black">
                                    {result.label}
                                </h1>
                                <p className="mt-3 text-left leading-relaxed">
                                    {result.description}
                                </p>
                                <div className="mt-5 grid grid-cols-4 gap-2">
                                    {Object.entries(result.scores).map(
                                        ([key, score]) => (
                                            <div
                                                key={key}
                                                className="rounded-box bg-base-200 p-3"
                                            >
                                                <strong className="block text-xl text-primary">
                                                    {key}
                                                </strong>
                                                <span className="text-sm">
                                                    {score} pontos
                                                </span>
                                            </div>
                                        ),
                                    )}
                                </div>
                                <div className="mt-5 alert text-left text-sm alert-info">
                                    {result.disclaimer}
                                </div>
                                <Link
                                    href={`/candidato/candidaturas/${application.id}`}
                                    className="btn mt-5 btn-primary"
                                >
                                    Voltar ao acompanhamento
                                </Link>
                            </div>
                        </section>
                    ) : question ? (
                        <section className="card border border-base-300 bg-base-100 shadow-xl">
                            <div className="card-body p-6 sm:p-9">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="font-semibold text-primary">
                                        Pergunta {position} de 20
                                    </span>
                                    <span>{answeredCount}/20 respondidas</span>
                                </div>
                                <progress
                                    className="progress mt-2 w-full progress-primary"
                                    value={position}
                                    max={20}
                                    aria-valuemin={1}
                                    aria-valuemax={20}
                                    aria-valuenow={position}
                                />
                                <h1
                                    className="mt-6 text-2xl font-bold"
                                    tabIndex={-1}
                                >
                                    {question.prompt}
                                </h1>
                                <div className="mt-5 grid gap-3">
                                    {question.options.map((option) => (
                                        <label
                                            key={option.id}
                                            className={`cursor-pointer rounded-box border p-4 transition ${answers[question.id] === option.id ? 'border-primary bg-primary/10 ring-2 ring-primary/20' : 'border-base-300 hover:border-primary/50'}`}
                                        >
                                            <input
                                                type="radio"
                                                className="radio mr-3 radio-primary"
                                                name={`question-${question.id}`}
                                                checked={
                                                    answers[question.id] ===
                                                    option.id
                                                }
                                                onChange={() =>
                                                    setAnswers((current) => ({
                                                        ...current,
                                                        [question.id]:
                                                            option.id,
                                                    }))
                                                }
                                            />
                                            {option.text}
                                        </label>
                                    ))}
                                </div>
                                {error && (
                                    <div
                                        role="alert"
                                        className="mt-4 alert alert-error"
                                    >
                                        {error}
                                    </div>
                                )}
                                <div className="mt-6 flex justify-between">
                                    <button
                                        className="btn"
                                        disabled={position === 1 || busy}
                                        onClick={() =>
                                            setPosition(position - 1)
                                        }
                                    >
                                        <ArrowLeft className="size-4" />
                                        Anterior
                                    </button>
                                    {position < 20 ? (
                                        <button
                                            className="btn btn-primary"
                                            disabled={busy}
                                            onClick={async () => {
                                                if (await persist())
                                                    setPosition(position + 1);
                                            }}
                                        >
                                            Próxima
                                            <ArrowRight className="size-4" />
                                        </button>
                                    ) : (
                                        <button
                                            className="btn btn-success"
                                            disabled={
                                                busy || !answers[question.id]
                                            }
                                            onClick={complete}
                                        >
                                            Concluir teste
                                            <CheckCircle2 className="size-4" />
                                        </button>
                                    )}
                                </div>
                            </div>
                        </section>
                    ) : null}
                </div>
            </main>
        </PublicSiteShell>
    );
}
