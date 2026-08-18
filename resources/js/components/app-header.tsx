import { faAnglesLeft } from '@fortawesome/free-solid-svg-icons/faAnglesLeft';
import { faAnglesRight } from '@fortawesome/free-solid-svg-icons/faAnglesRight';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell, Globe, LogOut, Menu, Settings, UserRound } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { useInitials } from '@/hooks/use-initials';
import { home, logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    sidebarCollapsed?: boolean;
    onSidebarToggle?: () => void;
};

export function AppHeader({
    breadcrumbs = [],
    sidebarCollapsed = false,
    onSidebarToggle,
}: Props) {
    const { auth } = usePage().props;
    const getInitials = useInitials();

    const handleLogout = () => {
        router.flushAll();
    };

    return (
        <header className="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
            {auth.impersonation.active && (
                <div className="flex min-h-10 items-center justify-center gap-3 bg-warning px-4 py-2 text-center text-sm text-warning-content">
                    <span>
                        Você está acessando como{' '}
                        <strong>{auth.user.name}</strong>.
                    </span>
                    <Link
                        href="/impersonation/stop"
                        method="post"
                        as="button"
                        className="btn btn-neutral btn-xs"
                    >
                        Voltar ao Super Admin
                    </Link>
                </div>
            )}
            <nav className="navbar mx-auto min-h-16 max-w-7xl px-4">
                <div className="navbar-start gap-1">
                    {onSidebarToggle && (
                        <button
                            type="button"
                            onClick={onSidebarToggle}
                            aria-label={
                                sidebarCollapsed
                                    ? 'Expandir menu lateral'
                                    : 'Recolher menu lateral'
                            }
                            title={
                                sidebarCollapsed
                                    ? 'Expandir menu lateral'
                                    : 'Recolher menu lateral'
                            }
                            className="btn hidden btn-circle btn-ghost lg:inline-flex"
                        >
                            <FontAwesomeIcon
                                icon={
                                    sidebarCollapsed
                                        ? faAnglesRight
                                        : faAnglesLeft
                                }
                                className="text-xl"
                                fixedWidth
                            />
                        </button>
                    )}
                    <div className="lg:hidden">
                        <label
                            htmlFor="app-drawer"
                            aria-label="Abrir navegação"
                            className="btn btn-circle btn-ghost drawer-button"
                        >
                            <Menu className="size-5" />
                        </label>
                    </div>
                </div>

                <div className="navbar-end gap-1">
                    <a
                        href={home().url}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Ir para o site"
                        title="Ir para o site"
                        className="btn btn-circle btn-ghost"
                    >
                        <Globe className="size-5" />
                    </a>

                    <div className="dropdown dropdown-end">
                        <div
                            tabIndex={0}
                            role="button"
                            aria-label="Notificações"
                            className="btn btn-circle btn-ghost"
                        >
                            <div className="indicator">
                                <Bell className="size-5" />
                                <span className="indicator-item badge badge-xs badge-primary" />
                            </div>
                        </div>
                        <div
                            tabIndex={-1}
                            className="dropdown-content card z-50 mt-3 w-80 border border-base-300 bg-base-100 shadow-xl card-sm"
                        >
                            <div className="card-body gap-3">
                                <div className="flex items-center justify-between">
                                    <h2 className="card-title text-base">
                                        Notificações
                                    </h2>
                                    <span className="badge badge-sm badge-primary">
                                        1 nova
                                    </span>
                                </div>
                                <div className="rounded-box bg-base-200 p-3">
                                    <p className="font-medium">
                                        Ambiente configurado
                                    </p>
                                    <p className="mt-1 text-sm text-base-content/65">
                                        Seu painel administrativo está pronto
                                        para uso.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="dropdown dropdown-end">
                        <div
                            tabIndex={0}
                            role="button"
                            aria-label="Abrir menu do usuário"
                            className="placeholder btn avatar btn-circle btn-ghost"
                        >
                            {auth.user?.avatar ? (
                                <div className="w-10 rounded-full">
                                    <img
                                        src={auth.user.avatar}
                                        alt={auth.user.name}
                                    />
                                </div>
                            ) : (
                                <div className="w-10 rounded-full bg-neutral text-neutral-content">
                                    <span className="text-sm">
                                        {getInitials(auth.user?.name ?? '')}
                                    </span>
                                </div>
                            )}
                        </div>
                        <ul
                            tabIndex={-1}
                            className="menu dropdown-content z-50 mt-3 w-64 menu-sm rounded-box border border-base-300 bg-base-100 p-2 shadow-xl"
                        >
                            <li className="menu-title px-3 py-2 normal-case">
                                <span className="truncate text-sm font-semibold text-base-content">
                                    {auth.user?.name}
                                </span>
                                <span className="truncate text-xs font-normal text-base-content/60">
                                    {auth.user?.email}
                                </span>
                            </li>
                            <li>
                                <Link href={edit()} prefetch>
                                    <UserRound className="size-4" />
                                    Meu perfil
                                </Link>
                            </li>
                            <li>
                                <Link href={edit()} prefetch>
                                    <Settings className="size-4" />
                                    Configurações
                                </Link>
                            </li>
                            <li className="mt-1 border-t border-base-300 pt-1">
                                <Link
                                    href={logout()}
                                    as="button"
                                    onClick={handleLogout}
                                    className="text-error"
                                    data-test="logout-button"
                                >
                                    <LogOut className="size-4" />
                                    Sair
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            {breadcrumbs.length > 1 && (
                <div className="border-t border-base-200 bg-base-100">
                    <div className="mx-auto flex h-11 max-w-7xl items-center px-4 text-sm text-base-content/65">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}
        </header>
    );
}
