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
            <div className="flex min-h-screen items-center justify-center bg-slate-100 px-4">
                <div className="w-full max-w-md rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
                    <h1 className="mb-2 text-2xl font-semibold text-slate-900">Login provisório</h1>
                    <p className="mb-6 text-sm text-slate-600">
                        Acesso local temporário enquanto o WorkOS não estiver configurado.
                    </p>

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700" htmlFor="email">
                                E-mail
                            </label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full rounded-md border border-slate-300 px-3 py-2"
                                required
                            />
                            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700" htmlFor="password">
                                Senha
                            </label>
                            <input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="w-full rounded-md border border-slate-300 px-3 py-2"
                                required
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                        >
                            {processing ? 'Entrando...' : 'Entrar'}
                        </button>
                    </form>

                    <p className="mt-4 text-center text-sm text-slate-500">
                        <Link href="/" className="text-slate-700 underline">
                            Voltar para a home
                        </Link>
                    </p>
                </div>
            </div>
        </>
    );
}
