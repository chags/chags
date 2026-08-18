import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, BriefcaseBusiness, Building2, MapPin } from 'lucide-react';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import { PublicSiteShell } from '@/components/public-site-shell';

type Job = {
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
    description: string;
    requirements: string | null;
    benefits: string | null;
    accepting_applications: boolean;
};

type TurnstileConfiguration = {
    enabled: boolean;
    siteKey: string | null;
};

declare global {
    interface Window {
        turnstile?: {
            render: (
                container: HTMLElement,
                options: Record<string, unknown>,
            ) => string;
            remove: (widgetId: string) => void;
            reset: (widgetId: string) => void;
        };
    }
}
const labels: Record<string, string> = {
    onsite: 'Presencial',
    hybrid: 'Híbrido',
    remote: 'Remoto',
};

const states = [
    'AC',
    'AL',
    'AP',
    'AM',
    'BA',
    'CE',
    'DF',
    'ES',
    'GO',
    'MA',
    'MT',
    'MS',
    'MG',
    'PA',
    'PB',
    'PR',
    'PE',
    'PI',
    'RJ',
    'RN',
    'RS',
    'RO',
    'RR',
    'SC',
    'SP',
    'SE',
    'TO',
];

function maskPhone(value: string) {
    const digits = value.replace(/\D/g, '').slice(0, 11);

    if (digits.length <= 10) {
        return digits
            .replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{4})(\d)/, '$1-$2');
    }

    return digits
        .replace(/(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d)/, '$1-$2');
}

export default function CareerShow({
    job,
    turnstile,
}: {
    job: Job;
    turnstile: TurnstileConfiguration;
}) {
    const [sent, setSent] = useState(false);
    const turnstileContainer = useRef<HTMLDivElement>(null);
    const turnstileWidgetId = useRef<string | null>(null);
    const form = useForm({
        name: '',
        email: '',
        phone: '',
        city: '',
        state: '',
        password: '',
        password_confirmation: '',
        resume: null as File | null,
        privacy_consent: false,
        turnstile_token: '',
    });

    useEffect(() => {
        if (!turnstile.enabled || !turnstile.siteKey) {
            return;
        }

        const renderWidget = () => {
            if (
                !window.turnstile ||
                !turnstileContainer.current ||
                turnstileWidgetId.current
            ) {
                return;
            }

            turnstileWidgetId.current = window.turnstile.render(
                turnstileContainer.current,
                {
                    sitekey: turnstile.siteKey,
                    action: 'career_application',
                    theme: 'auto',
                    size: 'flexible',
                    callback: (token: string) => {
                        form.setData('turnstile_token', token);
                        form.clearErrors('turnstile_token');
                    },
                    'expired-callback': () =>
                        form.setData('turnstile_token', ''),
                    'error-callback': () => form.setData('turnstile_token', ''),
                },
            );
        };

        let script = document.querySelector<HTMLScriptElement>(
            'script[data-turnstile-script]',
        );

        if (window.turnstile) {
            renderWidget();
        } else if (script) {
            script.addEventListener('load', renderWidget);
        } else {
            script = document.createElement('script');
            script.src =
                'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
            script.async = true;
            script.defer = true;
            script.dataset.turnstileScript = 'true';
            script.addEventListener('load', renderWidget);
            document.head.appendChild(script);
        }

        return () => {
            script?.removeEventListener('load', renderWidget);
            if (turnstileWidgetId.current && window.turnstile) {
                window.turnstile.remove(turnstileWidgetId.current);
                turnstileWidgetId.current = null;
            }
        };
    }, [turnstile.enabled, turnstile.siteKey]);

    const resetTurnstile = () => {
        form.setData('turnstile_token', '');
        if (turnstileWidgetId.current && window.turnstile) {
            window.turnstile.reset(turnstileWidgetId.current);
        }
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.clearErrors();
        form.post(`/trabalhe-conosco/${job.slug}/candidatar`, {
            forceFormData: true,
            onSuccess: () => {
                setSent(true);
                form.reset();
            },
            onError: (errors) => {
                resetTurnstile();
                const firstInvalidField = Object.keys(errors)[0];
                const input = firstInvalidField
                    ? document.querySelector<HTMLElement>(
                          `[name="${firstInvalidField}"]`,
                      )
                    : null;
                input?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            },
        });
    };
    return (
        <PublicSiteShell>
            <Head title={job.title} />
            <main className="mx-auto grid max-w-7xl gap-6 bg-base-100 p-4 py-8 lg:grid-cols-[1.4fr_1fr] lg:p-8">
                <div className="lg:col-span-2">
                    <Link
                        href="/trabalhe-conosco"
                        className="btn btn-ghost btn-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Todas as vagas
                    </Link>
                </div>
                <article className="card overflow-hidden border border-base-300 bg-base-100 shadow-lg">
                    {job.image_url ? (
                        <img
                            src={job.image_url}
                            alt={`Vaga ${job.title}`}
                            className="mx-auto aspect-square w-full max-w-2xl object-cover"
                        />
                    ) : (
                        <div className="mx-auto grid aspect-square w-full max-w-2xl place-items-center bg-primary/10">
                            <BriefcaseBusiness className="size-20 text-primary/35" />
                        </div>
                    )}
                    <div className="card-body">
                        <div className="flex flex-wrap gap-2">
                            <span className="badge badge-primary">
                                {labels[job.workplace_type]}
                            </span>
                            <span className="badge badge-outline">
                                {job.employment_type.toUpperCase()}
                            </span>
                        </div>
                        <h1 className="mt-2 text-3xl font-black">
                            {job.title}
                        </h1>
                        <p className="flex gap-2 text-base-content/65">
                            <Building2 className="size-5" />
                            {job.company} · {job.department}
                        </p>
                        <p className="flex gap-2 text-base-content/65">
                            <MapPin className="size-5" />
                            {job.city
                                ? [job.city, job.state]
                                      .filter(Boolean)
                                      .join(' - ')
                                : 'Local a definir'}
                        </p>
                        <TextSection
                            title="Sobre a vaga"
                            text={job.description}
                        />
                        {job.requirements && (
                            <TextSection
                                title="Requisitos"
                                text={job.requirements}
                            />
                        )}{' '}
                        {job.benefits && (
                            <TextSection
                                title="Benefícios"
                                text={job.benefits}
                            />
                        )}
                    </div>
                </article>
                <aside className="card h-fit border border-primary/20 bg-base-100 shadow-xl shadow-primary/10 lg:sticky lg:top-28">
                    <div className="card-body">
                        <h2 className="card-title">
                            {job.accepting_applications
                                ? 'Candidate-se'
                                : 'Inscrições encerradas'}
                        </h2>
                        {!job.accepting_applications ? (
                            <div className="alert alert-warning">
                                Esta vaga continua disponível para consulta, mas
                                não aceita novas candidaturas.
                            </div>
                        ) : sent ? (
                            <div
                                className="alert flex-col items-start gap-1 alert-success"
                                role="status"
                            >
                                <strong className="text-base">
                                    Candidatura enviada com sucesso!
                                </strong>
                                <span className="text-sm">
                                    Recebemos seu currículo e seus dados para
                                    esta vaga.
                                </span>
                            </div>
                        ) : (
                            <form onSubmit={submit} className="grid gap-3">
                                <Input
                                    label="Nome completo"
                                    name="name"
                                    value={form.data.name}
                                    onChange={(v) => form.setData('name', v)}
                                    error={form.errors.name}
                                />
                                <Input
                                    label="E-mail"
                                    name="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(v) => form.setData('email', v)}
                                    error={form.errors.email}
                                />
                                <Input
                                    label="Telefone"
                                    name="phone"
                                    value={form.data.phone}
                                    onChange={(v) =>
                                        form.setData('phone', maskPhone(v))
                                    }
                                    error={form.errors.phone}
                                />
                                <div className="grid grid-cols-[1fr_5rem] gap-2">
                                    <Input
                                        label="Cidade"
                                        name="city"
                                        value={form.data.city}
                                        onChange={(v) =>
                                            form.setData('city', v)
                                        }
                                    />
                                    <label className="fieldset">
                                        <span className="fieldset-legend">
                                            UF
                                        </span>
                                        <select
                                            name="state"
                                            className={`select w-full ${form.errors.state ? 'select-error' : ''}`}
                                            value={form.data.state}
                                            onChange={(event) =>
                                                form.setData(
                                                    'state',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="">Selecione</option>
                                            {states.map((state) => (
                                                <option
                                                    key={state}
                                                    value={state}
                                                >
                                                    {state}
                                                </option>
                                            ))}
                                        </select>
                                        {form.errors.state && (
                                            <span className="text-xs text-error">
                                                {form.errors.state}
                                            </span>
                                        )}
                                    </label>
                                </div>
                                <Input
                                    label="Senha"
                                    name="password"
                                    type="password"
                                    value={form.data.password}
                                    onChange={(v) =>
                                        form.setData('password', v)
                                    }
                                    error={form.errors.password}
                                />
                                <Input
                                    label="Confirmar senha"
                                    name="password_confirmation"
                                    type="password"
                                    value={form.data.password_confirmation}
                                    onChange={(v) =>
                                        form.setData('password_confirmation', v)
                                    }
                                    error={form.errors.password_confirmation}
                                />
                                <label className="fieldset">
                                    <span className="fieldset-legend">
                                        Currículo
                                    </span>
                                    <input
                                        required
                                        type="file"
                                        accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                        className={`file-input w-full ${form.errors.resume ? 'file-input-error' : ''}`}
                                        onChange={(event) =>
                                            form.setData(
                                                'resume',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    <span className="text-xs text-base-content/60">
                                        PDF, DOC ou DOCX — máximo de 5 MB.
                                    </span>
                                    {form.errors.resume && (
                                        <span className="text-xs text-error">
                                            {form.errors.resume}
                                        </span>
                                    )}
                                </label>
                                <label className="flex items-start gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-sm"
                                        checked={form.data.privacy_consent}
                                        onChange={(e) =>
                                            form.setData(
                                                'privacy_consent',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    <span>
                                        Li e concordo com o tratamento dos meus
                                        dados para este processo seletivo,
                                        conforme o{' '}
                                        <Link
                                            href="/privacidade-e-lgpd"
                                            target="_blank"
                                            className="font-semibold text-primary underline"
                                            onClick={(event) =>
                                                event.stopPropagation()
                                            }
                                        >
                                            Aviso de Privacidade e LGPD
                                        </Link>
                                        .
                                    </span>
                                </label>
                                {form.errors.privacy_consent && (
                                    <p className="text-xs text-error">
                                        {form.errors.privacy_consent}
                                    </p>
                                )}
                                {turnstile.enabled && (
                                    <div className="grid gap-1">
                                        <div ref={turnstileContainer} />
                                        {form.errors.turnstile_token && (
                                            <p className="text-xs text-error">
                                                {form.errors.turnstile_token}
                                            </p>
                                        )}
                                    </div>
                                )}
                                <button
                                    disabled={
                                        form.processing ||
                                        (turnstile.enabled &&
                                            !form.data.turnstile_token)
                                    }
                                    className="btn mt-2 btn-primary"
                                >
                                    {form.processing && (
                                        <span className="loading loading-xs loading-spinner" />
                                    )}
                                    Enviar candidatura
                                </button>
                            </form>
                        )}
                    </div>
                </aside>
            </main>
        </PublicSiteShell>
    );
}
function TextSection({ title, text }: { title: string; text: string }) {
    return (
        <section className="mt-5">
            <h2 className="text-xl font-bold">{title}</h2>
            <p className="mt-2 leading-relaxed whitespace-pre-line text-base-content/75">
                {text}
            </p>
        </section>
    );
}
function Input({
    label,
    name,
    type = 'text',
    value,
    onChange,
    error,
}: {
    label: string;
    name: string;
    type?: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <label className="fieldset">
            <span className="fieldset-legend">{label}</span>
            <input
                required={!['city', 'state'].includes(name)}
                name={name}
                type={type}
                className={`input w-full ${error ? 'input-error' : ''}`}
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
            {error && <span className="text-xs text-error">{error}</span>}
        </label>
    );
}
