import { Head, Link } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';

export default function LoginProvisorio() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <>
            <Head title="Login provisório" />
            <div className="flex min-h-screen items-center justify-center bg-base-200 px-4">
                <div className="card w-full max-w-md border border-base-300 bg-base-100 shadow-xl">
                    <div className="card-body p-8">
                        <div className="mb-4 flex items-center gap-3">
                            <div className="badge badge-primary badge-lg">Local</div>
                            <div>
                                <h1 className="text-2xl font-semibold">Login provisório</h1>
                                <p className="text-sm text-base-content/70">
                                    Acesso local temporário enquanto o WorkOS não estiver configurado.
                                </p>
                            </div>
                        </div>

                        <form onSubmit={submit} className="space-y-4">
                            <div className="form-control">
                                <label className="label" htmlFor="email">
                                    <span className="label-text">E-mail</span>
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="input input-bordered w-full bg-base-100 text-base-content"
                                    placeholder="seu@email.com"
                                    required
                                />
                                {errors.email && <p className="mt-1 text-sm text-error">{errors.email}</p>}
                            </div>

                            <div className="form-control">
                                <label className="label" htmlFor="password">
                                    <span className="label-text">Senha</span>
                                </label>
                                <input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="input input-bordered w-full bg-base-100 text-base-content"
                                    placeholder="••••••••"
                                    required
                                />
                            </div>

                            <button type="submit" disabled={processing} className="btn btn-primary w-full">
                                {processing ? 'Entrando...' : 'Entrar'}
                            </button>
                        </form>

                        <p className="mt-4 text-center text-sm text-base-content/70">
                            <Link href="/" className="link link-hover">
                                Voltar para a home
                            </Link>
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
