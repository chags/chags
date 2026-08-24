import { faLaptopCode } from '@fortawesome/free-solid-svg-icons/faLaptopCode';
import { faMoon } from '@fortawesome/free-solid-svg-icons/faMoon';
import { faRightToBracket } from '@fortawesome/free-solid-svg-icons/faRightToBracket';
import { faSun } from '@fortawesome/free-solid-svg-icons/faSun';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import { dashboard } from '@/routes';

export function PublicSiteShell({ children }: { children: ReactNode }) {
    const { auth, companyBrand, name: applicationName } = usePage().props;
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const brand = companyBrand?.name ?? applicationName;
    const accessHref = auth.user
        ? auth.abilities.candidatePortal
            ? '/candidato'
            : dashboard()
        : '/candidato/entrar';
    const accessLabel = auth.user
        ? auth.abilities.candidatePortal
            ? 'Minhas candidaturas'
            : 'Intranet'
        : 'Área do candidato';

    return (
        <div className="min-h-screen bg-base-100 text-base-content">
            <header className="sticky top-0 z-50 border-b border-base-300 bg-base-100/90 backdrop-blur-xl">
                <nav className="navbar mx-auto min-h-20 max-w-7xl px-4 lg:px-8">
                    <div className="navbar-start">
                        <Link href="/" className="flex items-center gap-3">
                            <span className="grid size-11 place-items-center overflow-hidden rounded-xl bg-primary text-primary-content">
                                {companyBrand?.logoUrl ? (
                                    <img
                                        src={companyBrand.logoUrl}
                                        alt={`Logo ${brand}`}
                                        className="size-full object-contain"
                                    />
                                ) : (
                                    <FontAwesomeIcon
                                        icon={faLaptopCode}
                                        className="text-xl"
                                    />
                                )}
                            </span>
                            <span className="max-w-44 truncate text-lg font-bold sm:max-w-none">
                                {brand}
                            </span>
                        </Link>
                    </div>
                    <div className="navbar-center hidden gap-1 lg:flex">
                        <Link href="/" className="btn btn-ghost">
                            Site institucional
                        </Link>
                        <Link
                            href="/trabalhe-conosco"
                            className="btn btn-ghost text-primary"
                        >
                            Trabalhe Conosco
                        </Link>
                    </div>
                    <div className="navbar-end gap-2">
                        <button
                            type="button"
                            className="btn btn-circle btn-ghost"
                            aria-label="Alternar tema"
                            onClick={() =>
                                updateAppearance(
                                    resolvedAppearance === 'dark'
                                        ? 'light'
                                        : 'dark',
                                )
                            }
                        >
                            <FontAwesomeIcon
                                icon={
                                    resolvedAppearance === 'dark'
                                        ? faSun
                                        : faMoon
                                }
                            />
                        </button>
                        <Link href={accessHref} className="btn btn-primary">
                            <FontAwesomeIcon icon={faRightToBracket} />
                            <span className="hidden sm:inline">
                                {accessLabel}
                            </span>
                        </Link>
                    </div>
                </nav>
            </header>
            {children}
            <footer className="footer bg-neutral p-10 text-neutral-content sm:footer-horizontal lg:px-[max(2rem,calc((100vw-80rem)/2))]">
                <aside className="max-w-md">
                    <div className="flex items-center gap-3">
                        <span className="grid size-11 place-items-center rounded-xl bg-primary text-primary-content">
                            <FontAwesomeIcon icon={faLaptopCode} />
                        </span>
                        <strong className="text-lg">{brand}</strong>
                    </div>
                    <p className="mt-2 text-neutral-content/60">
                        Tecnologia feita por pessoas. Encontre seu próximo
                        desafio com a nossa equipe.
                    </p>
                </aside>
                <nav>
                    <h2 className="footer-title">Empresa</h2>
                    <Link href="/" className="link link-hover">
                        Site institucional
                    </Link>
                    <Link href="/trabalhe-conosco" className="link link-hover">
                        Vagas abertas
                    </Link>
                </nav>
            </footer>
            <div className="border-t border-neutral-content/10 bg-neutral px-4 py-4 text-center text-xs text-neutral-content/50">
                © {new Date().getFullYear()} {brand}. Todos os direitos
                reservados.
            </div>
        </div>
    );
}
