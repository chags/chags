import { faArrowRight } from '@fortawesome/free-solid-svg-icons/faArrowRight';
import { faBars } from '@fortawesome/free-solid-svg-icons/faBars';
import { faBolt } from '@fortawesome/free-solid-svg-icons/faBolt';
import { faCheck } from '@fortawesome/free-solid-svg-icons/faCheck';
import { faCloud } from '@fortawesome/free-solid-svg-icons/faCloud';
import { faCode } from '@fortawesome/free-solid-svg-icons/faCode';
import { faEnvelope } from '@fortawesome/free-solid-svg-icons/faEnvelope';
import { faHeadset } from '@fortawesome/free-solid-svg-icons/faHeadset';
import { faLaptopCode } from '@fortawesome/free-solid-svg-icons/faLaptopCode';
import { faLocationDot } from '@fortawesome/free-solid-svg-icons/faLocationDot';
import { faLock } from '@fortawesome/free-solid-svg-icons/faLock';
import { faMoon } from '@fortawesome/free-solid-svg-icons/faMoon';
import { faNetworkWired } from '@fortawesome/free-solid-svg-icons/faNetworkWired';
import { faRightToBracket } from '@fortawesome/free-solid-svg-icons/faRightToBracket';
import { faServer } from '@fortawesome/free-solid-svg-icons/faServer';
import { faShieldHalved } from '@fortawesome/free-solid-svg-icons/faShieldHalved';
import { faSun } from '@fortawesome/free-solid-svg-icons/faSun';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Head, Link, usePage } from '@inertiajs/react';
import { useAppearance } from '@/hooks/use-appearance';
import { dashboard, login } from '@/routes';

type Props = {
    company: {
        name: string;
        tradeName: string;
        cnpj: string;
        logoUrl: string | null;
        address: string;
    } | null;
    contactEmail: string | null;
};

const services = [
    {
        icon: faCode,
        title: 'Desenvolvimento de software',
        text: 'Sistemas web, integrações, APIs e soluções sob medida para sua operação.',
    },
    {
        icon: faNetworkWired,
        title: 'Infraestrutura e redes',
        text: 'Ambientes estáveis, conectados e preparados para acompanhar o seu crescimento.',
    },
    {
        icon: faCloud,
        title: 'Computação em nuvem',
        text: 'Migração, configuração e gestão de recursos em nuvem com eficiência.',
    },
    {
        icon: faShieldHalved,
        title: 'Segurança da informação',
        text: 'Proteção de dados, controle de acesso e redução de riscos tecnológicos.',
    },
    {
        icon: faHeadset,
        title: 'Suporte e gestão de TI',
        text: 'Atendimento técnico próximo, manutenção preventiva e evolução contínua.',
    },
    {
        icon: faBolt,
        title: 'Automação e integração',
        text: 'Processos conectados para reduzir tarefas manuais e aumentar a produtividade.',
    },
];

const differentiators = [
    'Atendimento próximo e consultivo',
    'Soluções adequadas a cada operação',
    'Segurança desde a concepção',
    'Comunicação clara durante os projetos',
    'Tecnologias atuais e sustentáveis',
    'Suporte e evolução contínua',
];

const steps = [
    ['01', 'Entendimento', 'Conhecemos sua operação, desafios e prioridades.'],
    [
        '02',
        'Planejamento',
        'Definimos a solução, o escopo e uma execução transparente.',
    ],
    [
        '03',
        'Implementação',
        'Construímos e integramos a tecnologia ao seu negócio.',
    ],
    ['04', 'Validação', 'Testamos, entregamos e acompanhamos os resultados.'],
    [
        '05',
        'Evolução',
        'Mantemos a solução segura, estável e preparada para crescer.',
    ],
];

const faqs = [
    [
        'Quais tipos de empresa vocês atendem?',
        'Atendemos empresas que precisam organizar, modernizar ou terceirizar serviços de tecnologia, respeitando o porte e a realidade de cada operação.',
    ],
    [
        'O atendimento pode ser remoto?',
        'Sim. Grande parte dos serviços pode ser conduzida remotamente, com atendimento presencial avaliado conforme a necessidade e a localização.',
    ],
    [
        'Vocês desenvolvem soluções personalizadas?',
        'Sim. Analisamos o processo do cliente e desenvolvemos sistemas, integrações e automações adequados aos objetivos definidos.',
    ],
    [
        'Como funciona o levantamento inicial?',
        'Começamos com uma conversa de diagnóstico para entender o cenário, identificar prioridades e propor o caminho mais adequado.',
    ],
];

export default function Welcome({ company, contactEmail }: Props) {
    const { auth, name: applicationName } = usePage().props;
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const brand = company?.tradeName ?? applicationName;
    const intranetRoute = auth.user ? dashboard() : login();

    const toggleTheme = () => {
        updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark');
    };

    return (
        <div className="min-h-screen bg-base-100 text-base-content">
            <Head title={`${brand} | Soluções em Tecnologia da Informação`}>
                <meta
                    name="description"
                    content={`${brand}: soluções em desenvolvimento de software, infraestrutura, nuvem, segurança e suporte de TI.`}
                />
                <meta
                    property="og:title"
                    content={`${brand} | Tecnologia para o seu negócio`}
                />
                <meta
                    property="og:description"
                    content="Soluções confiáveis de Tecnologia da Informação para simplificar, proteger e impulsionar empresas."
                />
            </Head>

            <header className="sticky top-0 z-50 border-b border-base-300 bg-base-100/90 backdrop-blur-xl">
                <nav className="navbar mx-auto min-h-20 max-w-7xl px-4 lg:px-8">
                    <div className="navbar-start">
                        <a href="#inicio" className="flex items-center gap-3">
                            <BrandLogo
                                logoUrl={company?.logoUrl}
                                brand={brand}
                            />
                            <span className="max-w-44 truncate text-lg font-bold sm:max-w-none">
                                {brand}
                            </span>
                        </a>
                    </div>

                    <div className="navbar-center hidden lg:flex">
                        <ul className="menu menu-horizontal gap-1 px-1">
                            <li>
                                <a href="#inicio">Início</a>
                            </li>
                            <li>
                                <a href="#servicos">Serviços</a>
                            </li>
                            <li>
                                <a href="#sobre">Sobre</a>
                            </li>
                            <li>
                                <a href="#diferenciais">Diferenciais</a>
                            </li>
                            <li>
                                <a href="#contato">Contato</a>
                            </li>
                        </ul>
                    </div>

                    <div className="navbar-end gap-2">
                        <button
                            type="button"
                            onClick={toggleTheme}
                            className="tooltip btn tooltip-bottom btn-circle btn-ghost"
                            data-tip={
                                resolvedAppearance === 'dark'
                                    ? 'Usar tema claro'
                                    : 'Usar tema escuro'
                            }
                            aria-label={
                                resolvedAppearance === 'dark'
                                    ? 'Ativar tema claro'
                                    : 'Ativar tema escuro'
                            }
                        >
                            <FontAwesomeIcon
                                icon={
                                    resolvedAppearance === 'dark'
                                        ? faSun
                                        : faMoon
                                }
                                className="text-xl"
                                fixedWidth
                            />
                        </button>
                        <Link href={intranetRoute} className="btn btn-primary">
                            <FontAwesomeIcon icon={faRightToBracket} />
                            <span className="hidden sm:inline">Intranet</span>
                        </Link>
                        <div className="dropdown dropdown-end lg:hidden">
                            <button
                                type="button"
                                tabIndex={0}
                                aria-label="Abrir menu"
                                className="btn btn-square btn-ghost"
                            >
                                <FontAwesomeIcon
                                    icon={faBars}
                                    className="text-xl"
                                />
                            </button>
                            <ul
                                tabIndex={-1}
                                className="menu dropdown-content z-50 mt-3 w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow-xl"
                            >
                                <li>
                                    <a href="#inicio">Início</a>
                                </li>
                                <li>
                                    <a href="#servicos">Serviços</a>
                                </li>
                                <li>
                                    <a href="#sobre">Sobre</a>
                                </li>
                                <li>
                                    <a href="#diferenciais">Diferenciais</a>
                                </li>
                                <li>
                                    <a href="#contato">Contato</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>
            </header>

            <main>
                <section
                    id="inicio"
                    className="relative scroll-mt-24 overflow-hidden"
                >
                    <div className="absolute inset-0 bg-gradient-to-br from-primary/15 via-base-100 to-secondary/10" />
                    <div className="relative mx-auto grid min-h-[calc(100vh-5rem)] max-w-7xl items-center gap-12 px-4 py-20 lg:grid-cols-2 lg:px-8">
                        <div>
                            <span className="mb-6 badge gap-2 badge-outline py-3 badge-primary">
                                <span className="status status-success" />
                                Tecnologia para empresas
                            </span>
                            <h1 className="max-w-3xl text-4xl leading-tight font-black sm:text-5xl lg:text-6xl">
                                Tecnologia que{' '}
                                <span className="text-primary">simplifica</span>
                                , protege e impulsiona o seu negócio.
                            </h1>
                            <p className="mt-6 max-w-2xl text-lg leading-relaxed text-base-content/70">
                                Desenvolvemos soluções de Tecnologia da
                                Informação para tornar sua operação mais
                                eficiente, segura e preparada para crescer.
                            </p>
                            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                <a
                                    href="#contato"
                                    className="btn btn-lg btn-primary"
                                >
                                    Falar com um especialista
                                    <FontAwesomeIcon icon={faArrowRight} />
                                </a>
                                <a
                                    href="#servicos"
                                    className="btn btn-outline btn-lg"
                                >
                                    Conhecer serviços
                                </a>
                            </div>
                        </div>

                        <div className="relative mx-auto w-full max-w-lg">
                            <div className="absolute -inset-10 rounded-full bg-primary/20 blur-3xl" />
                            <div className="relative rounded-box border border-primary/20 bg-base-100/80 p-6 shadow-2xl backdrop-blur">
                                <div className="mb-6 flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <BrandLogo
                                            logoUrl={company?.logoUrl}
                                            brand={brand}
                                            large
                                        />
                                        <div>
                                            <p className="font-bold">{brand}</p>
                                            <p className="text-xs text-base-content/55">
                                                Ambiente tecnológico
                                            </p>
                                        </div>
                                    </div>
                                    <span className="badge badge-soft badge-success">
                                        Operacional
                                    </span>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    {(
                                        [
                                            [
                                                faServer,
                                                'Infraestrutura',
                                                'Estável',
                                            ],
                                            [faLock, 'Segurança', 'Protegida'],
                                            [faCloud, 'Nuvem', 'Escalável'],
                                            [
                                                faLaptopCode,
                                                'Sistemas',
                                                'Integrados',
                                            ],
                                        ] as const
                                    ).map(([icon, title, status]) => (
                                        <div
                                            key={String(title)}
                                            className="rounded-box bg-base-200 p-4"
                                        >
                                            <FontAwesomeIcon
                                                icon={icon}
                                                className="text-2xl text-primary"
                                            />
                                            <p className="mt-4 font-semibold">
                                                {String(title)}
                                            </p>
                                            <p className="text-xs text-base-content/55">
                                                {String(status)}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    id="servicos"
                    className="scroll-mt-24 bg-base-200 py-24"
                >
                    <div className="mx-auto max-w-7xl px-4 lg:px-8">
                        <SectionHeading
                            eyebrow="O que fazemos"
                            title="Soluções de TI para desafios reais"
                            text="Tecnologia planejada para melhorar processos, reduzir riscos e apoiar o crescimento da sua empresa."
                        />
                        <div className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                            {services.map((service) => (
                                <article
                                    key={service.title}
                                    className="card border border-base-300 bg-base-100 shadow-sm transition hover:-translate-y-1 hover:shadow-lg"
                                >
                                    <div className="card-body">
                                        <span className="grid size-12 place-items-center rounded-box bg-primary/15 text-primary">
                                            <FontAwesomeIcon
                                                icon={service.icon}
                                                className="text-2xl"
                                                fixedWidth
                                            />
                                        </span>
                                        <h3 className="mt-3 card-title">
                                            {service.title}
                                        </h3>
                                        <p className="leading-relaxed text-base-content/65">
                                            {service.text}
                                        </p>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>

                <section id="sobre" className="scroll-mt-24 py-24">
                    <div className="mx-auto grid max-w-7xl items-center gap-12 px-4 lg:grid-cols-2 lg:px-8">
                        <div className="relative min-h-96 overflow-hidden rounded-box bg-neutral p-8 text-neutral-content shadow-xl">
                            <div className="absolute -top-16 -right-16 size-56 rounded-full bg-primary/40 blur-2xl" />
                            <div className="relative flex h-full flex-col justify-between">
                                <FontAwesomeIcon
                                    icon={faLaptopCode}
                                    className="text-7xl text-primary"
                                />
                                <div>
                                    <p className="text-3xl font-black">
                                        Estratégia, tecnologia e proximidade.
                                    </p>
                                    <p className="mt-3 text-neutral-content/65">
                                        Soluções construídas para funcionar na
                                        realidade do seu negócio.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <SectionHeading
                                eyebrow="Sobre nós"
                                title="Tecnologia transformada em resultado"
                                align="left"
                            />
                            <p className="mt-6 text-lg leading-relaxed text-base-content/70">
                                Somos uma empresa de serviços de Tecnologia da
                                Informação focada em criar soluções confiáveis,
                                seguras e adequadas à realidade de cada cliente.
                            </p>
                            <p className="mt-4 leading-relaxed text-base-content/65">
                                Combinamos conhecimento técnico, planejamento e
                                atendimento próximo para simplificar operações e
                                construir uma base tecnológica preparada para o
                                futuro.
                            </p>
                            <a href="#contato" className="btn mt-8 btn-primary">
                                Conheça nossa abordagem
                            </a>
                        </div>
                    </div>
                </section>

                <section
                    id="diferenciais"
                    className="scroll-mt-24 bg-neutral py-24 text-neutral-content"
                >
                    <div className="mx-auto max-w-7xl px-4 lg:px-8">
                        <SectionHeading
                            eyebrow="Por que escolher"
                            title="Tecnologia com visão de negócio"
                            text="Mais do que entregar ferramentas, buscamos compreender sua operação e construir relações duradouras."
                            inverted
                        />
                        <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {differentiators.map((item) => (
                                <div
                                    key={item}
                                    className="flex items-center gap-4 rounded-box border border-neutral-content/15 p-5"
                                >
                                    <span className="grid size-9 shrink-0 place-items-center rounded-full bg-primary text-primary-content">
                                        <FontAwesomeIcon icon={faCheck} />
                                    </span>
                                    <span className="font-semibold">
                                        {item}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="py-24">
                    <div className="mx-auto max-w-7xl px-4 lg:px-8">
                        <SectionHeading
                            eyebrow="Como trabalhamos"
                            title="Um processo claro do diagnóstico à evolução"
                        />
                        <div className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-5">
                            {steps.map(([number, title, text]) => (
                                <article
                                    key={number}
                                    className="relative rounded-box border border-base-300 bg-base-100 p-5 shadow-sm"
                                >
                                    <span className="text-4xl font-black text-primary/25">
                                        {number}
                                    </span>
                                    <h3 className="mt-3 text-lg font-bold">
                                        {title}
                                    </h3>
                                    <p className="mt-2 text-sm leading-relaxed text-base-content/60">
                                        {text}
                                    </p>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="bg-base-200 py-24">
                    <div className="mx-auto max-w-4xl px-4 lg:px-8">
                        <SectionHeading
                            eyebrow="Dúvidas frequentes"
                            title="Informação clara desde o primeiro contato"
                        />
                        <div className="mt-10 space-y-3">
                            {faqs.map(([question, answer], index) => (
                                <div
                                    key={question}
                                    className="collapse-arrow collapse border border-base-300 bg-base-100"
                                >
                                    <input
                                        type="radio"
                                        name="institutional-faq"
                                        defaultChecked={index === 0}
                                    />
                                    <div className="collapse-title font-semibold">
                                        {question}
                                    </div>
                                    <div className="collapse-content text-base-content/65">
                                        <p>{answer}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section id="contato" className="scroll-mt-24 py-24">
                    <div className="mx-auto max-w-7xl px-4 lg:px-8">
                        <div className="hero overflow-hidden rounded-box bg-primary text-primary-content shadow-2xl">
                            <div className="hero-content w-full flex-col justify-between gap-10 p-8 text-center lg:flex-row lg:p-14 lg:text-left">
                                <div className="max-w-2xl">
                                    <span className="badge badge-outline border-primary-content/30 text-primary-content">
                                        Vamos conversar?
                                    </span>
                                    <h2 className="mt-5 text-3xl font-black sm:text-4xl">
                                        Transforme sua necessidade em uma
                                        solução eficiente.
                                    </h2>
                                    <p className="mt-4 text-lg text-primary-content/75">
                                        Conte o seu desafio. Nossa equipe
                                        ajudará a encontrar o melhor caminho
                                        tecnológico para sua empresa.
                                    </p>
                                </div>
                                {contactEmail ? (
                                    <a
                                        href={`mailto:${contactEmail}`}
                                        className="btn border-0 bg-base-100 text-base-content btn-lg hover:bg-base-200"
                                    >
                                        <FontAwesomeIcon icon={faEnvelope} />
                                        Solicitar uma conversa
                                    </a>
                                ) : (
                                    <Link
                                        href={intranetRoute}
                                        className="btn border-0 bg-base-100 text-base-content btn-lg hover:bg-base-200"
                                    >
                                        Falar com a equipe
                                    </Link>
                                )}
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <footer className="footer bg-neutral p-10 text-neutral-content sm:footer-horizontal lg:px-[max(2rem,calc((100vw-80rem)/2))]">
                <aside className="max-w-sm">
                    <div className="flex items-center gap-3">
                        <BrandLogo logoUrl={company?.logoUrl} brand={brand} />
                        <span className="text-lg font-bold">{brand}</span>
                    </div>
                    <p className="mt-3 text-neutral-content/60">
                        Soluções de Tecnologia da Informação para empresas mais
                        eficientes, seguras e preparadas para crescer.
                    </p>
                </aside>
                <nav>
                    <h2 className="footer-title">Navegação</h2>
                    <a href="#servicos" className="link link-hover">
                        Serviços
                    </a>
                    <a href="#sobre" className="link link-hover">
                        Sobre
                    </a>
                    <a href="#diferenciais" className="link link-hover">
                        Diferenciais
                    </a>
                    <a href="#contato" className="link link-hover">
                        Contato
                    </a>
                    <Link href="/trabalhe-conosco" className="link link-hover">
                        Trabalhe Conosco
                    </Link>
                </nav>
                <nav>
                    <h2 className="footer-title">Empresa</h2>
                    {company?.address && (
                        <span className="flex max-w-xs gap-2 text-neutral-content/65">
                            <FontAwesomeIcon
                                icon={faLocationDot}
                                className="mt-1"
                            />
                            {company.address}
                        </span>
                    )}
                    {contactEmail && (
                        <a
                            href={`mailto:${contactEmail}`}
                            className="flex link gap-2 link-hover"
                        >
                            <FontAwesomeIcon icon={faEnvelope} />
                            {contactEmail}
                        </a>
                    )}
                    {company?.cnpj && (
                        <span className="text-neutral-content/65">
                            CNPJ {formatCnpj(company.cnpj)}
                        </span>
                    )}
                </nav>
            </footer>
            <div className="border-t border-neutral-content/10 bg-neutral px-4 py-4 text-center text-xs text-neutral-content/50">
                © {new Date().getFullYear()} {brand}. Todos os direitos
                reservados.
            </div>
        </div>
    );
}

function BrandLogo({
    logoUrl,
    brand,
    large = false,
}: {
    logoUrl?: string | null;
    brand: string;
    large?: boolean;
}) {
    const size = large ? 'size-14' : 'size-11';

    return (
        <span
            className={`grid ${size} shrink-0 place-items-center overflow-hidden rounded-xl bg-primary text-primary-content`}
        >
            {logoUrl ? (
                <img
                    src={logoUrl}
                    alt={`Logo ${brand}`}
                    className="size-full object-contain"
                />
            ) : (
                <FontAwesomeIcon
                    icon={faLaptopCode}
                    className={large ? 'text-2xl' : 'text-xl'}
                />
            )}
        </span>
    );
}

function SectionHeading({
    eyebrow,
    title,
    text,
    align = 'center',
    inverted = false,
}: {
    eyebrow: string;
    title: string;
    text?: string;
    align?: 'left' | 'center';
    inverted?: boolean;
}) {
    return (
        <div
            className={
                align === 'center'
                    ? 'mx-auto max-w-3xl text-center'
                    : 'max-w-2xl'
            }
        >
            <span
                className={`text-sm font-bold tracking-widest uppercase ${inverted ? 'text-primary' : 'text-primary'}`}
            >
                {eyebrow}
            </span>
            <h2 className="mt-3 text-3xl font-black sm:text-4xl">{title}</h2>
            {text && (
                <p
                    className={`mt-4 text-lg leading-relaxed ${inverted ? 'text-neutral-content/65' : 'text-base-content/65'}`}
                >
                    {text}
                </p>
            )}
        </div>
    );
}

function formatCnpj(value: string) {
    return value.replace(
        /^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/,
        '$1.$2.$3/$4-$5',
    );
}
