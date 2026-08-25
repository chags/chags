import { faBookOpen } from '@fortawesome/free-solid-svg-icons/faBookOpen';
import { faBriefcase } from '@fortawesome/free-solid-svg-icons/faBriefcase';
import { faBuilding } from '@fortawesome/free-solid-svg-icons/faBuilding';
import { faChevronDown } from '@fortawesome/free-solid-svg-icons/faChevronDown';
import { faClipboardCheck } from '@fortawesome/free-solid-svg-icons/faClipboardCheck';
import { faClock } from '@fortawesome/free-solid-svg-icons/faClock';
import { faFileLines } from '@fortawesome/free-solid-svg-icons/faFileLines';
import { faGaugeHigh } from '@fortawesome/free-solid-svg-icons/faGaugeHigh';
import { faHouse } from '@fortawesome/free-solid-svg-icons/faHouse';
import { faIdBadge } from '@fortawesome/free-solid-svg-icons/faIdBadge';
import { faLaptop } from '@fortawesome/free-solid-svg-icons/faLaptop';
import { faPeopleGroup } from '@fortawesome/free-solid-svg-icons/faPeopleGroup';
import { faShieldHalved } from '@fortawesome/free-solid-svg-icons/faShieldHalved';
import { faSliders } from '@fortawesome/free-solid-svg-icons/faSliders';
import { faUsers } from '@fortawesome/free-solid-svg-icons/faUsers';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import { dashboard as hrDashboard } from '@/routes/hr';
import { index as systemSettings } from '@/routes/system-settings';
import { index as usersIndex } from '@/routes/users';

type Props = {
    collapsed?: boolean;
};

const hrLinks = [
    { label: 'Visão geral do RH', href: '/hr', icon: faHouse },
    { label: 'Setores', href: '/hr/departments', icon: faBuilding },
    { label: 'Cargos', href: '/hr/positions', icon: faIdBadge },
    { label: 'Vagas', href: '/hr/jobs', icon: faBriefcase },
    { label: 'Candidaturas', href: '/hr/applications', icon: faFileLines },
    { label: 'Avaliações', href: '/hr/evaluations', icon: faClipboardCheck },
];

const closeMobileDrawer = () => {
    const drawer = document.getElementById('app-drawer') as HTMLInputElement;

    if (drawer) {
        drawer.checked = false;
    }
};

export function AppSidebar({ collapsed = false }: Props) {
    const { isCurrentUrl } = useCurrentUrl();
    const { auth, companyBrand, name: applicationName } = usePage().props;
    const [hrMenuOpen, setHrMenuOpen] = useState(false);
    const [adminMenuOpen, setAdminMenuOpen] = useState(false);
    const [virtualOfficeMenuOpen, setVirtualOfficeMenuOpen] = useState(false);
    const [personnelMenuOpen, setPersonnelMenuOpen] = useState(false);
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

                    {auth.abilities.virtualOfficeView && (
                        <li className="mt-4">
                            <button
                                type="button"
                                onClick={() =>
                                    setVirtualOfficeMenuOpen((open) => !open)
                                }
                                aria-expanded={virtualOfficeMenuOpen}
                                aria-controls="virtual-office-sidebar-submenu"
                                data-tip="Escritório Virtual"
                                className={`!flex min-h-14 w-full items-center gap-3 ${iconLinkClass} ${isCurrentUrl('/virtual-office', undefined, true) ? 'menu-active font-semibold' : ''}`}
                            >
                                <FontAwesomeIcon
                                    icon={faLaptop}
                                    className="block shrink-0 text-2xl"
                                    fixedWidth
                                />
                                <span
                                    className={`flex items-center leading-none ${collapsedClass}`}
                                >
                                    Escritório Virtual
                                </span>
                                <FontAwesomeIcon
                                    icon={faChevronDown}
                                    className={`ml-auto text-sm transition-transform duration-200 ${collapsedClass} ${virtualOfficeMenuOpen ? 'rotate-180' : ''}`}
                                    fixedWidth
                                />
                            </button>
                            {virtualOfficeMenuOpen && (
                                <ul
                                    id="virtual-office-sidebar-submenu"
                                    className={`mt-1 ml-5 gap-1 border-l border-base-300 pl-3 ${collapsedClass}`}
                                >
                                    <li>
                                        <Link
                                            href="/virtual-office"
                                            onClick={closeMobileDrawer}
                                            className={`!flex min-h-10 items-center gap-2 text-sm ${isCurrentUrl('/virtual-office') ? 'menu-active font-semibold' : ''}`}
                                        >
                                            <FontAwesomeIcon
                                                icon={faGaugeHigh}
                                                className="shrink-0"
                                                fixedWidth
                                            />
                                            <span>Dashboard</span>
                                        </Link>
                                    </li>
                                    {auth.abilities.tracksTime && (
                                        <li>
                                            <Link
                                                href="/virtual-office/time-card"
                                                onClick={closeMobileDrawer}
                                                className={`!flex min-h-10 items-center gap-2 text-sm ${isCurrentUrl('/virtual-office/time-card') ? 'menu-active font-semibold' : ''}`}
                                            >
                                                <FontAwesomeIcon
                                                    icon={faClock}
                                                    className="shrink-0"
                                                    fixedWidth
                                                />
                                                <span>Cartão de Ponto</span>
                                            </Link>
                                        </li>
                                    )}
                                </ul>
                            )}
                        </li>
                    )}

                    {auth.abilities.hrView && (
                        <li className="mt-4">
                            <button
                                type="button"
                                onClick={() => setHrMenuOpen((open) => !open)}
                                aria-expanded={hrMenuOpen}
                                aria-controls="hr-sidebar-submenu"
                                data-tip="Recursos Humanos"
                                className={`!flex min-h-14 w-full items-center gap-3 ${iconLinkClass} ${isCurrentUrl(hrDashboard(), undefined, true) ? 'menu-active font-semibold' : ''}`}
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
                                <FontAwesomeIcon
                                    icon={faChevronDown}
                                    className={`ml-auto text-sm transition-transform duration-200 ${collapsedClass} ${hrMenuOpen ? 'rotate-180' : ''}`}
                                    fixedWidth
                                />
                            </button>
                            {hrMenuOpen && (
                                <ul
                                    id="hr-sidebar-submenu"
                                    className={`mt-1 ml-5 gap-1 border-l border-base-300 pl-3 ${collapsedClass}`}
                                >
                                    {hrLinks.map((item) => (
                                        <li key={item.href}>
                                            <Link
                                                href={item.href}
                                                onClick={closeMobileDrawer}
                                                className={`!flex min-h-10 items-center gap-2 text-sm ${isCurrentUrl(item.href) ? 'menu-active font-semibold' : ''}`}
                                            >
                                                <FontAwesomeIcon
                                                    icon={item.icon}
                                                    className="shrink-0"
                                                    fixedWidth
                                                />
                                                <span>{item.label}</span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </li>
                    )}
                    {(auth.abilities.personnelView ||
                        auth.abilities.timeApprovalsView) && (
                        <li className="mt-4">
                            <button
                                type="button"
                                onClick={() =>
                                    setPersonnelMenuOpen((open) => !open)
                                }
                                aria-expanded={personnelMenuOpen}
                                aria-controls="personnel-sidebar-submenu"
                                data-tip="Setor Pessoal"
                                className={`!flex min-h-14 w-full items-center gap-3 ${iconLinkClass} ${isCurrentUrl('/personnel', undefined, true) ? 'menu-active font-semibold' : ''}`}
                            >
                                <FontAwesomeIcon
                                    icon={faClock}
                                    className="block shrink-0 text-2xl"
                                    fixedWidth
                                />
                                <span
                                    className={`flex items-center leading-none ${collapsedClass}`}
                                >
                                    Setor Pessoal
                                </span>
                                <FontAwesomeIcon
                                    icon={faChevronDown}
                                    className={`ml-auto text-sm transition-transform duration-200 ${collapsedClass} ${personnelMenuOpen ? 'rotate-180' : ''}`}
                                    fixedWidth
                                />
                            </button>
                            {personnelMenuOpen && (
                                <ul
                                    id="personnel-sidebar-submenu"
                                    className={`mt-1 ml-5 gap-1 border-l border-base-300 pl-3 ${collapsedClass}`}
                                >
                                    {auth.abilities.timeApprovalsView && (
                                        <li>
                                            <Link
                                                href="/personnel/time-approvals"
                                                onClick={closeMobileDrawer}
                                                className={`!flex min-h-10 items-center gap-2 text-sm ${isCurrentUrl('/personnel/time-approvals') ? 'menu-active font-semibold' : ''}`}
                                            >
                                                <FontAwesomeIcon
                                                    icon={faClipboardCheck}
                                                    className="shrink-0"
                                                    fixedWidth
                                                />
                                                <span>Aprovações de ponto</span>
                                            </Link>
                                        </li>
                                    )}
                                    {auth.abilities.personnelView && (
                                        <li>
                                            <Link
                                                href="/personnel/time-card-settings"
                                                onClick={closeMobileDrawer}
                                                className={`!flex min-h-10 items-center gap-2 text-sm ${isCurrentUrl('/personnel/time-card-settings') ? 'menu-active font-semibold' : ''}`}
                                            >
                                                <FontAwesomeIcon
                                                    icon={faSliders}
                                                    className="shrink-0"
                                                    fixedWidth
                                                />
                                                <span>
                                                    Config. cartão de ponto
                                                </span>
                                            </Link>
                                        </li>
                                    )}
                                </ul>
                            )}
                        </li>
                    )}
                    {(auth.abilities.systemSettingsView ||
                        auth.abilities.usersView) && (
                        <li className="mt-4">
                            <button
                                type="button"
                                onClick={() =>
                                    setAdminMenuOpen((open) => !open)
                                }
                                aria-expanded={adminMenuOpen}
                                aria-controls="admin-sidebar-submenu"
                                data-tip="Administração"
                                className={`!flex min-h-14 w-full items-center gap-3 ${iconLinkClass} ${isCurrentUrl(systemSettings()) || isCurrentUrl(usersIndex()) ? 'menu-active font-semibold' : ''}`}
                            >
                                <FontAwesomeIcon
                                    icon={faShieldHalved}
                                    className="block shrink-0 text-2xl"
                                    fixedWidth
                                />
                                <span
                                    className={`flex items-center leading-none ${collapsedClass}`}
                                >
                                    Administração
                                </span>
                                <FontAwesomeIcon
                                    icon={faChevronDown}
                                    className={`ml-auto text-sm transition-transform duration-200 ${collapsedClass} ${adminMenuOpen ? 'rotate-180' : ''}`}
                                    fixedWidth
                                />
                            </button>
                            {adminMenuOpen && (
                                <ul
                                    id="admin-sidebar-submenu"
                                    className={`mt-1 ml-5 gap-1 border-l border-base-300 pl-3 ${collapsedClass}`}
                                >
                                    {auth.abilities.systemSettingsView && (
                                        <li>
                                            <Link
                                                href={systemSettings()}
                                                onClick={closeMobileDrawer}
                                                className={`!flex min-h-10 items-center gap-2 text-sm ${isCurrentUrl(systemSettings()) ? 'menu-active font-semibold' : ''}`}
                                            >
                                                <FontAwesomeIcon
                                                    icon={faSliders}
                                                    className="shrink-0"
                                                    fixedWidth
                                                />
                                                <span>
                                                    Configurações do sistema
                                                </span>
                                            </Link>
                                        </li>
                                    )}
                                    {auth.abilities.usersView && (
                                        <li>
                                            <Link
                                                href={usersIndex()}
                                                onClick={closeMobileDrawer}
                                                className={`!flex min-h-10 items-center gap-2 text-sm ${isCurrentUrl(usersIndex()) ? 'menu-active font-semibold' : ''}`}
                                            >
                                                <FontAwesomeIcon
                                                    icon={faUsers}
                                                    className="shrink-0"
                                                    fixedWidth
                                                />
                                                <span>Usuários</span>
                                            </Link>
                                        </li>
                                    )}
                                </ul>
                            )}
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
