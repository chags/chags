import { faBookOpen } from '@fortawesome/free-solid-svg-icons/faBookOpen';
import { faGaugeHigh } from '@fortawesome/free-solid-svg-icons/faGaugeHigh';
import { faPeopleGroup } from '@fortawesome/free-solid-svg-icons/faPeopleGroup';
import { faShieldHalved } from '@fortawesome/free-solid-svg-icons/faShieldHalved';
import { faSliders } from '@fortawesome/free-solid-svg-icons/faSliders';
import { faUsers } from '@fortawesome/free-solid-svg-icons/faUsers';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Link, usePage } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import { dashboard as hrDashboard } from '@/routes/hr';
import { index as systemSettings } from '@/routes/system-settings';
import { index as usersIndex } from '@/routes/users';

type Props = {
    collapsed?: boolean;
};

const closeMobileDrawer = () => {
    const drawer = document.getElementById('app-drawer') as HTMLInputElement;

    if (drawer) {
        drawer.checked = false;
    }
};

export function AppSidebar({ collapsed = false }: Props) {
    const { isCurrentUrl } = useCurrentUrl();
    const { auth, companyBrand, name: applicationName } = usePage().props;
    const collapsedClass = collapsed ? 'lg:hidden' : '';
    const iconLinkClass = collapsed
        ? 'lg:tooltip lg:tooltip-right lg:justify-center'
        : '';

    return (
        <aside
            className={`flex min-h-full w-72 flex-col border-r border-base-300 bg-base-200 text-base-content transition-[width] duration-200 ${collapsed ? 'lg:w-24' : 'lg:w-72'}`}
        >
            <div
                className={`flex h-16 items-center gap-3 border-b border-base-300 px-4 ${collapsed ? 'lg:justify-center' : ''}`}
            >
                <span className="grid size-12 shrink-0 place-items-center overflow-hidden rounded-lg bg-primary text-primary-content">
                    {companyBrand?.logoUrl ? (
                        <img
                            src={companyBrand.logoUrl}
                            alt={`Logo ${companyBrand.name}`}
                            className="size-full object-contain"
                        />
                    ) : (
                        <FontAwesomeIcon
                            icon={faShieldHalved}
                            className="text-2xl"
                            fixedWidth
                        />
                    )}
                </span>
                <div className={collapsedClass}>
                    <p className="max-w-36 truncate leading-tight font-bold">
                        {companyBrand?.name ?? applicationName}
                    </p>
                    <p className="max-w-36 truncate text-xs text-base-content/55">
                        {companyBrand?.unit ?? 'Painel de controle'}
                    </p>
                </div>
            </div>

            <nav
                className={`flex-1 overflow-y-auto ${collapsed ? 'lg:p-3' : 'p-4'}`}
            >
                <ul className="menu w-full gap-1 p-0">
                    <li className={`menu-title ${collapsedClass}`}>
                        Visão geral
                    </li>

                    <li>
                        <Link
                            href={dashboard()}
                            onClick={closeMobileDrawer}
                            data-tip="Dashboard"
                            className={`!flex min-h-14 items-center gap-3 ${iconLinkClass} ${isCurrentUrl(dashboard()) ? 'menu-active font-semibold' : ''}`}
                        >
                            <FontAwesomeIcon
                                icon={faGaugeHigh}
                                className="block shrink-0 text-2xl"
                                fixedWidth
                            />
                            <span
                                className={`flex items-center leading-none ${collapsedClass}`}
                            >
                                Dashboard
                            </span>
                        </Link>
                    </li>

                    {auth.abilities.hrView && (
                        <>
                            <li className={`mt-4 menu-title ${collapsedClass}`}>
                                Pessoas
                            </li>
                            <li>
                                <Link
                                    href={hrDashboard()}
                                    onClick={closeMobileDrawer}
                                    data-tip="Recursos Humanos"
                                    className={`!flex min-h-14 items-center gap-3 ${iconLinkClass} ${isCurrentUrl(hrDashboard()) ? 'menu-active font-semibold' : ''}`}
                                >
                                    <FontAwesomeIcon
                                        icon={faPeopleGroup}
                                        className="block shrink-0 text-2xl"
                                        fixedWidth
                                    />
                                    <span
                                        className={`flex items-center leading-none ${collapsedClass}`}
                                    >
                                        Recursos Humanos
                                    </span>
                                </Link>
                            </li>
                        </>
                    )}
                    {auth.abilities.systemSettingsView && (
                        <>
                            <li className={`mt-4 menu-title ${collapsedClass}`}>
                                Administração
                            </li>
                            <li>
                                <Link
                                    href={systemSettings()}
                                    onClick={closeMobileDrawer}
                                    data-tip="Configurações do sistema"
                                    className={`!flex min-h-14 items-center gap-3 ${iconLinkClass} ${isCurrentUrl(systemSettings()) ? 'menu-active font-semibold' : ''}`}
                                >
                                    <FontAwesomeIcon
                                        icon={faSliders}
                                        className="block shrink-0 text-2xl"
                                        fixedWidth
                                    />
                                    <span
                                        className={`flex items-center leading-none ${collapsedClass}`}
                                    >
                                        Configurações do sistema
                                    </span>
                                </Link>
                            </li>
                        </>
                    )}
                    {auth.abilities.usersView && (
                        <li>
                            <Link
                                href={usersIndex()}
                                onClick={closeMobileDrawer}
                                data-tip="Usuários"
                                className={`!flex min-h-14 items-center gap-3 ${iconLinkClass} ${isCurrentUrl(usersIndex()) ? 'menu-active font-semibold' : ''}`}
                            >
                                <FontAwesomeIcon
                                    icon={faUsers}
                                    className="block shrink-0 text-2xl"
                                    fixedWidth
                                />
                                <span
                                    className={`flex items-center leading-none ${collapsedClass}`}
                                >
                                    Usuários
                                </span>
                            </Link>
                        </li>
                    )}
                </ul>
            </nav>

            <div
                className={`border-t border-base-300 ${collapsed ? 'lg:p-2' : 'p-4'}`}
            >
                <a
                    href="https://daisyui.com/components/"
                    target="_blank"
                    rel="noreferrer"
                    data-tip="Documentação"
                    className={`btn min-h-12 w-full btn-ghost ${collapsed ? 'lg:tooltip lg:tooltip-right lg:justify-center' : 'justify-start'}`}
                >
                    <FontAwesomeIcon
                        icon={faBookOpen}
                        className="text-2xl"
                        fixedWidth
                    />
                    <span className={collapsedClass}>
                        Documentação da interface
                    </span>
                </a>
                <div
                    className={`mt-3 flex items-center gap-2 px-3 text-xs text-base-content/50 ${collapsed ? 'lg:justify-center' : ''}`}
                >
                    <span className="status status-success" />
                    <span className={collapsedClass}>Sistema operacional</span>
                </div>
            </div>
        </aside>
    );
}
