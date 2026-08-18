import { Head, Link, useForm } from '@inertiajs/react';
import { BriefcaseBusiness, LogIn } from 'lucide-react';
import type { FormEvent } from 'react';
import { PublicSiteShell } from '@/components/public-site-shell';

export default function CandidateLogin() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/candidato/entrar');
    };

    return (
        <PublicSiteShell>
            <Head title="Área do candidato" />
            <main className="relative grid min-h-[70vh] place-items-center overflow-hidden bg-base-200 px-4 py-14">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,color-mix(in_oklab,var(--color-primary)_18%,transparent),transparent_48%)]" />
                <section className="card relative w-full max-w-md border border-base-300 bg-base-100 shadow-xl">
                    <div className="card-body p-7 sm:p-9">
                        <span className="grid size-12 place-items-center rounded-2xl bg-primary text-primary-content">
                            <BriefcaseBusiness className="size-6" />
                        </span>
                        <h1 className="mt-3 text-3xl font-black">
                            Área do candidato
                        </h1>
                        <p className="text-sm text-base-content/60">
                            Entre para acompanhar suas candidaturas e as etapas
                            dos processos seletivos.
                        </p>

                        <form onSubmit={submit} className="mt-4 space-y-4">
                            <label className="fieldset">
                                <span className="fieldset-legend">E-mail</span>
                                <input
                                    className="input w-full"
                                    type="email"
                                    value={data.email}
                                    onChange={(event) =>
                                        setData('email', event.target.value)
                                    }
                                    autoComplete="email"
                                    required
                                    autoFocus
                                />
                                {errors.email && (
                                    <span className="text-sm text-error">
                                        {errors.email}
                                    </span>
                                )}
                            </label>
                            <label className="fieldset">
                                <span className="fieldset-legend">Senha</span>
                                <input
                                    className="input w-full"
                                    type="password"
                                    value={data.password}
                                    onChange={(event) =>
                                        setData('password', event.target.value)
                                    }
                                    autoComplete="current-password"
                                    required
                                />
                                {errors.password && (
                                    <span className="text-sm text-error">
                                        {errors.password}
                                    </span>
                                )}
                            </label>
                            <label className="flex cursor-pointer items-center gap-3 text-sm">
                                <input
                                    type="checkbox"
                                    className="checkbox checkbox-sm checkbox-primary"
                                    checked={data.remember}
                                    onChange={(event) =>
                                        setData(
                                            'remember',
                                            event.target.checked,
                                        )
                                    }
                                />
                                Lembrar meu acesso
                            </label>
                            <button
                                className="btn w-full btn-primary"
                                disabled={processing}
                            >
                                {processing ? (
                                    <span className="loading loading-spinner" />
                                ) : (
                                    <LogIn className="size-4" />
                                )}
                                Entrar
                            </button>
                        </form>

                        <Link
                            href="/trabalhe-conosco"
                            className="btn mt-2 btn-ghost"
                        >
                            Ver vagas abertas
                        </Link>
                    </div>
                </section>
            </main>
        </PublicSiteShell>
    );
}
