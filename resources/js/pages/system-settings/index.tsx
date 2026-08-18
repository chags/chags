import { faCamera } from '@fortawesome/free-solid-svg-icons/faCamera';
import { faPenToSquare } from '@fortawesome/free-solid-svg-icons/faPenToSquare';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Head, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useRef, useState } from 'react';
import { index } from '@/routes/system-settings';
import { update as updateAppearance } from '@/routes/system-settings/appearance';
import {
    store as storeCompany,
    update as updateCompany,
} from '@/routes/system-settings/companies';
import { store as storeLogo } from '@/routes/system-settings/companies/logo';
import {
    test as testMail,
    update as updateMail,
} from '@/routes/system-settings/mail';
import { update as updateTurnstile } from '@/routes/system-settings/turnstile';

type Company = {
    id: number;
    headquarters_id: number | null;
    headquarters: { id: number; unit_number: string; unit_name: string } | null;
    unit_type: 'headquarters' | 'branch';
    unit_number: string;
    unit_name: string;
    name: string;
    trade_name: string | null;
    cnpj: string;
    logo_url: string | null;
    address: string;
    address_number: string;
    address_complement: string | null;
    district: string;
    city: string;
    state: string;
    postal_code: string;
    active: boolean;
};

type MailSettings = {
    from_name: string;
    from_address: string;
    host: string;
    port: number;
    username: string | null;
    has_password: boolean;
    encryption: string | null;
    timeout: number;
    last_tested_at: string | null;
    last_test_succeeded: boolean | null;
};

type TurnstileSettings = {
    enabled: boolean;
    site_key: string;
    has_secret_key: boolean;
};

type AiProvider = {
    id: number;
    name: string;
    provider: string;
    enabled: boolean;
    is_default: boolean;
    base_url: string | null;
    model: string;
    has_api_key: boolean;
    organization: string | null;
    timeout: number;
    max_output_tokens: number;
    temperature: number;
    last_tested_at: string | null;
};

type Props = {
    companies: Company[];
    mailSettings: MailSettings | null;
    turnstileSettings: TurnstileSettings;
    aiProviders: AiProvider[];
    theme: string;
    abilities: {
        companyUpdate: boolean;
        mailUpdate: boolean;
        mailTest: boolean;
        appearanceUpdate: boolean;
        turnstileUpdate: boolean;
        aiUpdate: boolean;
        aiTest: boolean;
    };
};

type Tab = 'company' | 'mail' | 'turnstile' | 'ai' | 'appearance';
type CompanySort = 'unit' | 'type' | 'cnpj' | 'location' | 'status';

const csrfToken = () =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

async function jsonRequest(
    url: string,
    method: string,
    body?: Record<string, unknown> | FormData,
) {
    const isFormData = body instanceof FormData;
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
        },
        body: body ? (isFormData ? body : JSON.stringify(body)) : undefined,
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const validationErrors = payload.errors
            ? Object.values(payload.errors).flat().join(' ')
            : null;

        throw new Error(
            validationErrors ||
                payload.message ||
                'Não foi possível concluir a operação.',
        );
    }

    return payload;
}

const emptyCompany = {
    unit_type: 'headquarters',
    unit_number: '',
    unit_name: '',
    headquarters_id: '',
    name: '',
    trade_name: '',
    cnpj: '',
    address: '',
    address_number: '',
    address_complement: '',
    district: '',
    city: '',
    state: '',
    postal_code: '',
    active: true,
};

const aiProviderOptions = [
    ['openai', 'OpenAI (ChatGPT)', 'gpt-5-mini'],
    ['anthropic', 'Anthropic (Claude)', 'claude-sonnet-4-5'],
    ['gemini', 'Google Gemini', 'gemini-2.5-flash'],
    ['github_models', 'GitHub Models', 'openai/gpt-4.1'],
    ['openrouter', 'OpenRouter', 'openai/gpt-4o-mini'],
    ['groq', 'Groq', 'llama-3.3-70b-versatile'],
    ['mistral', 'Mistral AI', 'mistral-small-latest'],
    ['ollama', 'Ollama local (sem chave)', 'llama3.2'],
    ['custom', 'API compatível personalizada', ''],
] as const;

export default function SystemSettings({
    companies,
    mailSettings,
    turnstileSettings,
    aiProviders,
    theme,
    abilities,
}: Props) {
    const [tab, setTab] = useState<Tab>('company');
    const [notice, setNotice] = useState<{
        type: 'success' | 'error';
        message: string;
    } | null>(null);
    const [processing, setProcessing] = useState(false);
    const [editingCompany, setEditingCompany] = useState<Company | null>(null);
    const [selectedTheme, setSelectedTheme] = useState(theme);
    const [turnstileEnabled, setTurnstileEnabled] = useState(
        turnstileSettings.enabled,
    );
    const companyDialog = useRef<HTMLDialogElement>(null);
    const aiDialog = useRef<HTMLDialogElement>(null);
    const [editingAiProvider, setEditingAiProvider] =
        useState<AiProvider | null>(null);
    const [selectedAiType, setSelectedAiType] = useState('openai');
    const [aiTestResult, setAiTestResult] = useState<{
        providerId: number;
        type: 'success' | 'error';
        message: string;
        testedAt?: string;
    } | null>(null);
    const companyForm = useRef<HTMLFormElement>(null);
    const cnpjTimer = useRef<number | undefined>(undefined);
    const [consultingCnpj, setConsultingCnpj] = useState(false);
    const [companySearch, setCompanySearch] = useState('');
    const [companyPage, setCompanyPage] = useState(1);
    const [companyPageSize, setCompanyPageSize] = useState(10);
    const [companySort, setCompanySort] = useState<CompanySort>('unit');
    const [companySortDirection, setCompanySortDirection] = useState<
        'asc' | 'desc'
    >('asc');
    const headquarters = companies.filter(
        (company) => company.unit_type === 'headquarters',
    );
    const normalizedSearch = companySearch.trim().toLocaleLowerCase('pt-BR');
    const filteredCompanies = companies
        .filter((company) =>
            [
                company.unit_number,
                company.unit_name,
                company.name,
                company.trade_name ?? '',
                company.cnpj,
                company.city,
                company.state,
                company.unit_type === 'headquarters' ? 'matriz' : 'filial',
                company.active ? 'ativa' : 'inativa',
            ]
                .join(' ')
                .toLocaleLowerCase('pt-BR')
                .includes(normalizedSearch),
        )
        .sort((left, right) => {
            const values: Record<CompanySort, [string, string]> = {
                unit: [
                    `${left.unit_number} ${left.unit_name}`,
                    `${right.unit_number} ${right.unit_name}`,
                ],
                type: [left.unit_type, right.unit_type],
                cnpj: [left.cnpj, right.cnpj],
                location: [
                    `${left.city} ${left.state}`,
                    `${right.city} ${right.state}`,
                ],
                status: [String(left.active), String(right.active)],
            };
            const result = values[companySort][0].localeCompare(
                values[companySort][1],
                'pt-BR',
                { numeric: true },
            );

            return companySortDirection === 'asc' ? result : -result;
        });
    const companyTotalPages = Math.max(
        1,
        Math.ceil(filteredCompanies.length / companyPageSize),
    );
    const safeCompanyPage = Math.min(companyPage, companyTotalPages);
    const paginatedCompanies = filteredCompanies.slice(
        (safeCompanyPage - 1) * companyPageSize,
        safeCompanyPage * companyPageSize,
    );

    const toggleCompanySort = (column: CompanySort) => {
        setCompanyPage(1);

        if (companySort === column) {
            setCompanySortDirection((direction) =>
                direction === 'asc' ? 'desc' : 'asc',
            );
        } else {
            setCompanySort(column);
            setCompanySortDirection('asc');
        }
    };

    const notify = (type: 'success' | 'error', message: string) => {
        setNotice({ type, message });
        window.setTimeout(() => setNotice(null), 5000);
    };

    const openCompany = (company: Company | null = null) => {
        setEditingCompany(company);
        companyDialog.current?.showModal();
    };

    const lookupCnpj = async (rawCnpj: string) => {
        const cnpj = rawCnpj.replace(/\D/g, '');

        if (cnpj.length !== 14 || consultingCnpj) {
            return;
        }

        setConsultingCnpj(true);

        try {
            const result = await jsonRequest(
                `/settings/system/companies/cnpj/${cnpj}`,
                'GET',
            );
            const form = companyForm.current;

            if (form) {
                Object.entries(
                    result.company as Record<string, string>,
                ).forEach(([name, value]) => {
                    const field = form.elements.namedItem(name);

                    if (field instanceof HTMLInputElement) {
                        field.value = value;
                    }
                });
            }

            notify('success', 'Dados da empresa preenchidos pelo CNPJ.');
        } catch (error) {
            notify(
                'error',
                error instanceof Error
                    ? error.message
                    : 'Erro ao consultar o CNPJ.',
            );
        } finally {
            setConsultingCnpj(false);
        }
    };

    const scheduleCnpjLookup = (value: string) => {
        window.clearTimeout(cnpjTimer.current);

        if (value.replace(/\D/g, '').length === 14) {
            cnpjTimer.current = window.setTimeout(() => lookupCnpj(value), 500);
        }
    };

    const saveCompany = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcessing(true);
        const data = Object.fromEntries(new FormData(event.currentTarget));
        const payload = { ...data, active: data.active === '1' };

        try {
            const route = editingCompany
                ? updateCompany(editingCompany.id)
                : storeCompany();
            const result = await jsonRequest(route.url, route.method, payload);
            companyDialog.current?.close();
            notify('success', result.message);
            router.reload({ only: ['companies'] });
        } catch (error) {
            notify(
                'error',
                error instanceof Error ? error.message : 'Erro inesperado.',
            );
        } finally {
            setProcessing(false);
        }
    };

    const uploadLogo = async (company: Company, file?: File) => {
        if (!file) {
            return;
        }

        const formData = new FormData();
        formData.append('logo', file);
        setProcessing(true);

        try {
            const route = storeLogo(company.id);
            const result = await jsonRequest(route.url, route.method, formData);
            notify('success', result.message);
            router.reload({ only: ['companies'] });
        } catch (error) {
            notify(
                'error',
                error instanceof Error ? error.message : 'Erro no upload.',
            );
        } finally {
            setProcessing(false);
        }
    };

    const saveMail = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcessing(true);

        try {
            const route = updateMail();
            const result = await jsonRequest(
                route.url,
                route.method,
                Object.fromEntries(new FormData(event.currentTarget)),
            );
            notify('success', result.message);
            router.reload({ only: ['mailSettings'] });
        } catch (error) {
            notify(
                'error',
                error instanceof Error ? error.message : 'Erro inesperado.',
            );
        } finally {
            setProcessing(false);
        }
    };

    const sendTestMail = async () => {
        const recipient = window.prompt(
            'Informe o e-mail que receberá o teste:',
        );

        if (!recipient) {
            return;
        }

        setProcessing(true);

        try {
            const route = testMail();
            const result = await jsonRequest(route.url, route.method, {
                recipient,
            });
            notify('success', result.message);
            router.reload({ only: ['mailSettings'] });
        } catch (error) {
            notify(
                'error',
                error instanceof Error ? error.message : 'Falha no teste SMTP.',
            );
        } finally {
            setProcessing(false);
        }
    };

    const saveTurnstile = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcessing(true);

        try {
            const route = updateTurnstile();
            const fields = Object.fromEntries(
                new FormData(event.currentTarget),
            );
            const result = await jsonRequest(route.url, route.method, {
                ...fields,
                enabled: turnstileEnabled,
            });
            notify('success', result.message);
            router.reload({ only: ['turnstileSettings'] });
        } catch (error) {
            notify(
                'error',
                error instanceof Error
                    ? error.message
                    : 'Erro ao salvar o Turnstile.',
            );
        } finally {
            setProcessing(false);
        }
    };

    const openAiProvider = (provider: AiProvider | null = null) => {
        setEditingAiProvider(provider);
        setSelectedAiType(provider?.provider ?? 'openai');
        aiDialog.current?.showModal();
    };

    const saveAiProvider = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcessing(true);

        try {
            const fields = Object.fromEntries(
                new FormData(event.currentTarget),
            );
            const payload = {
                ...fields,
                enabled: fields.enabled === '1',
                is_default: fields.is_default === '1',
            };
            const url = editingAiProvider
                ? `/settings/system/ai/providers/${editingAiProvider.id}`
                : '/settings/system/ai/providers';
            const result = await jsonRequest(
                url,
                editingAiProvider ? 'PUT' : 'POST',
                payload,
            );
            aiDialog.current?.close();
            notify('success', result.message);
            router.reload({ only: ['aiProviders'] });
        } catch (error) {
            notify(
                'error',
                error instanceof Error
                    ? error.message
                    : 'Erro ao salvar o provedor de IA.',
            );
        } finally {
            setProcessing(false);
        }
    };

    const testAiProvider = async (provider: AiProvider) => {
        setProcessing(true);
        setAiTestResult(null);

        try {
            const result = await jsonRequest(
                `/settings/system/ai/providers/${provider.id}/test`,
                'POST',
            );
            notify('success', result.message);
            setAiTestResult({
                providerId: provider.id,
                type: 'success',
                message: result.message,
                testedAt: result.tested_at,
            });
        } catch (error) {
            const message =
                error instanceof Error
                    ? error.message
                    : 'Falha no teste da IA.';
            notify('error', message);
            setAiTestResult({
                providerId: provider.id,
                type: 'error',
                message,
                testedAt: new Date().toISOString(),
            });
        } finally {
            setProcessing(false);
        }
    };

    const removeAiProvider = async (provider: AiProvider) => {
        if (!window.confirm(`Remover o provedor “${provider.name}”?`)) return;
        setProcessing(true);

        try {
            const result = await jsonRequest(
                `/settings/system/ai/providers/${provider.id}`,
                'DELETE',
            );
            notify('success', result.message);
            router.reload({ only: ['aiProviders'] });
        } catch (error) {
            notify(
                'error',
                error instanceof Error
                    ? error.message
                    : 'Erro ao remover a IA.',
            );
        } finally {
            setProcessing(false);
        }
    };

    const saveTheme = async () => {
        setProcessing(true);

        try {
            const route = updateAppearance();
            const result = await jsonRequest(route.url, route.method, {
                theme: selectedTheme,
            });
            document.documentElement.dataset.theme = result.theme;
            notify('success', result.message);
        } catch (error) {
            notify(
                'error',
                error instanceof Error
                    ? error.message
                    : 'Erro ao aplicar tema.',
            );
        } finally {
            setProcessing(false);
        }
    };

    return (
        <>
            <Head title="Configurações do sistema" />
            <main className="mx-auto w-full max-w-7xl p-4 py-6 md:p-8">
                <div className="mb-6">
                    <div className="mb-3 badge badge-outline badge-primary">
                        Administração
                    </div>
                    <h1 className="text-3xl font-bold">
                        Configurações do sistema
                    </h1>
                    <p className="mt-2 text-base-content/60">
                        Gerencie unidades, integrações, envio de e-mails e
                        aparência global.
                    </p>
                </div>

                <div
                    role="tablist"
                    className="tabs tabs-box mb-6 overflow-x-auto bg-base-100"
                >
                    {(
                        [
                            ['company', 'Empresa'],
                            ['mail', 'E-mail (SMTP)'],
                            ['turnstile', 'Turnstile'],
                            ['ai', 'Inteligência Artificial'],
                            ['appearance', 'Aparência'],
                        ] as const
                    ).map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            role="tab"
                            onClick={() => setTab(value)}
                            className={`tab ${tab === value ? 'tab-active' : ''}`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {tab === 'company' && (
                    <section className="card border border-base-300 bg-base-100 shadow-sm">
                        <div className="card-body">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="card-title">
                                        Matriz e filiais
                                    </h2>
                                    <p className="text-sm text-base-content/60">
                                        Cadastre a matriz e organize todas as
                                        unidades vinculadas.
                                    </p>
                                </div>
                                {abilities.companyUpdate && (
                                    <button
                                        type="button"
                                        onClick={() => openCompany()}
                                        className="btn btn-primary"
                                    >
                                        Nova unidade
                                    </button>
                                )}
                            </div>

                            <div className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                <label className="fieldset w-full sm:max-w-md">
                                    <span className="fieldset-legend">
                                        Buscar empresas
                                    </span>
                                    <input
                                        type="search"
                                        className="input w-full"
                                        placeholder="Nome, CNPJ, unidade ou cidade"
                                        value={companySearch}
                                        onChange={(event) => {
                                            setCompanySearch(
                                                event.target.value,
                                            );
                                            setCompanyPage(1);
                                        }}
                                    />
                                </label>
                                <label className="fieldset w-full sm:w-36">
                                    <span className="fieldset-legend">
                                        Itens por página
                                    </span>
                                    <select
                                        className="select w-full"
                                        value={companyPageSize}
                                        onChange={(event) => {
                                            setCompanyPageSize(
                                                Number(event.target.value),
                                            );
                                            setCompanyPage(1);
                                        }}
                                    >
                                        {[5, 10, 25, 50].map((size) => (
                                            <option key={size} value={size}>
                                                {size}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </div>

                            <div className="mt-3 overflow-x-auto">
                                <table className="table table-zebra">
                                    <thead>
                                        <tr>
                                            {(
                                                [
                                                    ['unit', 'Unidade'],
                                                    ['type', 'Tipo'],
                                                    ['cnpj', 'CNPJ'],
                                                    ['location', 'Localização'],
                                                    ['status', 'Status'],
                                                ] as const
                                            ).map(([column, label]) => (
                                                <th key={column}>
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center gap-1 hover:text-primary"
                                                        onClick={() =>
                                                            toggleCompanySort(
                                                                column,
                                                            )
                                                        }
                                                    >
                                                        {label}
                                                        <span
                                                            aria-hidden="true"
                                                            className={
                                                                companySort ===
                                                                column
                                                                    ? 'text-primary'
                                                                    : 'text-base-content/30'
                                                            }
                                                        >
                                                            {companySort ===
                                                                column &&
                                                            companySortDirection ===
                                                                'desc'
                                                                ? '↓'
                                                                : '↑'}
                                                        </span>
                                                    </button>
                                                </th>
                                            ))}
                                            <th className="text-right">
                                                Ações
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {paginatedCompanies.map((company) => (
                                            <tr key={company.id}>
                                                <td>
                                                    <div className="flex items-center gap-3">
                                                        <div className="placeholder avatar">
                                                            <div className="w-12 rounded-lg bg-base-300">
                                                                {company.logo_url ? (
                                                                    <img
                                                                        src={
                                                                            company.logo_url
                                                                        }
                                                                        alt={`Logo ${company.unit_name}`}
                                                                    />
                                                                ) : (
                                                                    <span>
                                                                        {company.unit_name
                                                                            .slice(
                                                                                0,
                                                                                2,
                                                                            )
                                                                            .toUpperCase()}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div className="font-semibold">
                                                                {
                                                                    company.unit_number
                                                                }{' '}
                                                                —{' '}
                                                                {
                                                                    company.unit_name
                                                                }
                                                            </div>
                                                            <div className="text-xs text-base-content/55">
                                                                {company.name}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        className={`badge ${company.unit_type === 'headquarters' ? 'badge-primary' : 'badge-secondary'}`}
                                                    >
                                                        {company.unit_type ===
                                                        'headquarters'
                                                            ? 'Matriz'
                                                            : 'Filial'}
                                                    </span>
                                                </td>
                                                <td>{company.cnpj}</td>
                                                <td>
                                                    {company.city}/
                                                    {company.state}
                                                </td>
                                                <td>
                                                    <span
                                                        className={`badge badge-soft ${company.active ? 'badge-success' : 'badge-ghost'}`}
                                                    >
                                                        {company.active
                                                            ? 'Ativa'
                                                            : 'Inativa'}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div className="flex justify-end gap-2">
                                                        {abilities.companyUpdate && (
                                                            <>
                                                                <label
                                                                    className="tooltip btn btn-square btn-ghost text-info btn-sm hover:bg-info/15"
                                                                    data-tip="Alterar logo"
                                                                    aria-label={`Alterar logo de ${company.unit_name}`}
                                                                >
                                                                    <FontAwesomeIcon
                                                                        icon={
                                                                            faCamera
                                                                        }
                                                                        className="text-lg"
                                                                        fixedWidth
                                                                    />
                                                                    <input
                                                                        type="file"
                                                                        accept="image/jpeg,image/png,image/webp"
                                                                        className="hidden"
                                                                        disabled={
                                                                            processing
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            uploadLogo(
                                                                                company,
                                                                                event
                                                                                    .target
                                                                                    .files?.[0],
                                                                            )
                                                                        }
                                                                    />
                                                                </label>
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        openCompany(
                                                                            company,
                                                                        )
                                                                    }
                                                                    aria-label={`Editar ${company.unit_name}`}
                                                                    data-tip="Editar empresa"
                                                                    className="tooltip btn btn-square btn-ghost text-warning btn-sm hover:bg-warning/15"
                                                                >
                                                                    <FontAwesomeIcon
                                                                        icon={
                                                                            faPenToSquare
                                                                        }
                                                                        className="text-lg"
                                                                        fixedWidth
                                                                    />
                                                                </button>
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                        {filteredCompanies.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={6}
                                                    className="py-10 text-center text-base-content/55"
                                                >
                                                    {companySearch
                                                        ? 'Nenhuma empresa corresponde à busca.'
                                                        : 'Nenhuma unidade cadastrada.'}
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            <div className="mt-4 flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                                <span className="text-base-content/60">
                                    {filteredCompanies.length === 0
                                        ? '0 registros'
                                        : `${(safeCompanyPage - 1) * companyPageSize + 1}–${Math.min(safeCompanyPage * companyPageSize, filteredCompanies.length)} de ${filteredCompanies.length} registros`}
                                </span>
                                <div className="join">
                                    <button
                                        type="button"
                                        className="btn join-item btn-sm"
                                        disabled={safeCompanyPage === 1}
                                        onClick={() =>
                                            setCompanyPage((page) => page - 1)
                                        }
                                    >
                                        Anterior
                                    </button>
                                    <span className="btn pointer-events-none join-item btn-sm">
                                        Página {safeCompanyPage} de{' '}
                                        {companyTotalPages}
                                    </span>
                                    <button
                                        type="button"
                                        className="btn join-item btn-sm"
                                        disabled={
                                            safeCompanyPage ===
                                            companyTotalPages
                                        }
                                        onClick={() =>
                                            setCompanyPage((page) => page + 1)
                                        }
                                    >
                                        Próxima
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                )}

                {tab === 'mail' && (
                    <section className="card border border-base-300 bg-base-100 shadow-sm">
                        <form onSubmit={saveMail} className="card-body">
                            <h2 className="card-title">Servidor SMTP</h2>
                            <p className="text-sm text-base-content/60">
                                As credenciais são armazenadas de forma
                                criptografada.
                            </p>
                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <Field label="Nome do remetente">
                                    <input
                                        className="input w-full"
                                        name="from_name"
                                        required
                                        defaultValue={mailSettings?.from_name}
                                    />
                                </Field>
                                <Field label="E-mail do remetente">
                                    <input
                                        className="input w-full"
                                        type="email"
                                        name="from_address"
                                        required
                                        defaultValue={
                                            mailSettings?.from_address
                                        }
                                    />
                                </Field>
                                <Field label="Host SMTP">
                                    <input
                                        className="input w-full"
                                        name="host"
                                        required
                                        defaultValue={mailSettings?.host}
                                    />
                                </Field>
                                <Field label="Porta">
                                    <input
                                        className="input w-full"
                                        type="number"
                                        name="port"
                                        required
                                        defaultValue={mailSettings?.port ?? 587}
                                    />
                                </Field>
                                <Field label="Usuário">
                                    <input
                                        className="input w-full"
                                        name="username"
                                        defaultValue={
                                            mailSettings?.username ?? ''
                                        }
                                    />
                                </Field>
                                <Field
                                    label={`Senha${mailSettings?.has_password ? ' (já configurada)' : ''}`}
                                >
                                    <input
                                        className="input w-full"
                                        type="password"
                                        name="password"
                                        placeholder={
                                            mailSettings?.has_password
                                                ? 'Deixe em branco para manter'
                                                : ''
                                        }
                                    />
                                </Field>
                                <Field label="Criptografia">
                                    <select
                                        className="select w-full"
                                        name="encryption"
                                        defaultValue={
                                            mailSettings?.encryption ?? 'tls'
                                        }
                                    >
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                        <option value="">Nenhuma</option>
                                    </select>
                                </Field>
                                <Field label="Timeout (segundos)">
                                    <input
                                        className="input w-full"
                                        type="number"
                                        name="timeout"
                                        defaultValue={
                                            mailSettings?.timeout ?? 30
                                        }
                                    />
                                </Field>
                            </div>
                            <div className="mt-5 card-actions justify-end">
                                {abilities.mailTest && (
                                    <button
                                        type="button"
                                        onClick={sendTestMail}
                                        disabled={processing || !mailSettings}
                                        className="btn btn-outline"
                                    >
                                        Testar configuração
                                    </button>
                                )}
                                {abilities.mailUpdate && (
                                    <button
                                        disabled={processing}
                                        className="btn btn-primary"
                                    >
                                        {processing ? (
                                            <span className="loading loading-spinner" />
                                        ) : null}
                                        Salvar SMTP
                                    </button>
                                )}
                            </div>
                        </form>
                    </section>
                )}

                {tab === 'turnstile' && (
                    <section className="card border border-base-300 bg-base-100 shadow-sm">
                        <form onSubmit={saveTurnstile} className="card-body">
                            <h2 className="card-title">Cloudflare Turnstile</h2>
                            <p className="text-sm text-base-content/60">
                                Proteja formulários públicos contra robôs. A
                                chave secreta é armazenada criptografada e não é
                                exibida novamente.
                            </p>

                            <label className="mt-4 flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    className="toggle toggle-primary"
                                    checked={turnstileEnabled}
                                    onChange={(event) =>
                                        setTurnstileEnabled(
                                            event.target.checked,
                                        )
                                    }
                                    disabled={!abilities.turnstileUpdate}
                                />
                                <span className="font-medium">
                                    Ativar proteção Turnstile
                                </span>
                            </label>

                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <Field label="Site key (chave pública)">
                                    <input
                                        className="input w-full"
                                        name="site_key"
                                        required={turnstileEnabled}
                                        defaultValue={
                                            turnstileSettings.site_key
                                        }
                                        disabled={!abilities.turnstileUpdate}
                                        autoComplete="off"
                                    />
                                </Field>
                                <Field
                                    label={`Secret key (chave secreta)${turnstileSettings.has_secret_key ? ' — já configurada' : ''}`}
                                >
                                    <input
                                        className="input w-full"
                                        type="password"
                                        name="secret_key"
                                        required={
                                            turnstileEnabled &&
                                            !turnstileSettings.has_secret_key
                                        }
                                        placeholder={
                                            turnstileSettings.has_secret_key
                                                ? 'Deixe em branco para manter'
                                                : ''
                                        }
                                        disabled={!abilities.turnstileUpdate}
                                        autoComplete="new-password"
                                    />
                                </Field>
                            </div>

                            <div className="mt-4 alert text-sm alert-info">
                                Cadastre os domínios autorizados no painel da
                                Cloudflare. Para desenvolvimento local, use as
                                chaves de teste oficiais.
                            </div>

                            {abilities.turnstileUpdate && (
                                <div className="mt-5 card-actions justify-end">
                                    <button
                                        disabled={processing}
                                        className="btn btn-primary"
                                    >
                                        {processing ? (
                                            <span className="loading loading-spinner" />
                                        ) : null}
                                        Salvar Turnstile
                                    </button>
                                </div>
                            )}
                        </form>
                    </section>
                )}

                {tab === 'ai' && (
                    <section className="card border border-base-300 bg-base-100 shadow-sm">
                        <div className="card-body">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="card-title">
                                        Provedores de inteligência artificial
                                    </h2>
                                    <p className="mt-1 max-w-3xl text-sm text-base-content/60">
                                        Configure uma ou mais APIs e escolha a
                                        padrão. As chaves são criptografadas e
                                        nunca voltam para o navegador.
                                    </p>
                                </div>
                                {abilities.aiUpdate && (
                                    <button
                                        type="button"
                                        className="btn btn-primary btn-sm"
                                        onClick={() => openAiProvider()}
                                    >
                                        Adicionar provedor
                                    </button>
                                )}
                            </div>

                            <div className="mt-4 alert text-sm alert-info">
                                Para Claude use a API da Anthropic. Para modelos
                                integráveis do ecossistema Copilot, use GitHub
                                Models. Ollama permite executar modelos locais
                                sem chave de API.
                            </div>

                            {aiTestResult && (
                                <div
                                    role="status"
                                    className={`mt-4 alert text-sm ${aiTestResult.type === 'success' ? 'alert-success' : 'alert-error'}`}
                                >
                                    <span>
                                        <strong>
                                            {aiTestResult.type === 'success'
                                                ? 'Teste aprovado: '
                                                : 'Falha no teste: '}
                                        </strong>
                                        {aiTestResult.message}
                                    </span>
                                </div>
                            )}

                            <div className="mt-5 overflow-x-auto">
                                <table className="table table-zebra">
                                    <thead>
                                        <tr>
                                            <th>Configuração</th>
                                            <th>Modelo</th>
                                            <th>Status</th>
                                            <th>Último teste</th>
                                            <th className="text-right">
                                                Ações
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {aiProviders.map((provider) => (
                                            <tr key={provider.id}>
                                                <td>
                                                    <div className="font-semibold">
                                                        {provider.name}
                                                    </div>
                                                    <div className="text-xs text-base-content/55">
                                                        {aiProviderOptions.find(
                                                            ([value]) =>
                                                                value ===
                                                                provider.provider,
                                                        )?.[1] ??
                                                            provider.provider}
                                                    </div>
                                                </td>
                                                <td>
                                                    <code className="text-xs">
                                                        {provider.model}
                                                    </code>
                                                </td>
                                                <td>
                                                    <div className="flex flex-wrap gap-1">
                                                        <span
                                                            className={`badge badge-sm ${provider.enabled ? 'badge-success' : 'badge-ghost'}`}
                                                        >
                                                            {provider.enabled
                                                                ? 'Ativo'
                                                                : 'Inativo'}
                                                        </span>
                                                        {provider.is_default && (
                                                            <span className="badge badge-sm badge-primary">
                                                                Padrão
                                                            </span>
                                                        )}
                                                        <span
                                                            className={`badge badge-sm ${provider.has_api_key || provider.provider === 'ollama' ? 'badge-outline' : 'badge-error'}`}
                                                        >
                                                            {provider.provider ===
                                                            'ollama'
                                                                ? 'Local'
                                                                : provider.has_api_key
                                                                  ? 'Chave salva'
                                                                  : 'Sem chave'}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="text-sm">
                                                    {(aiTestResult?.providerId ===
                                                        provider.id &&
                                                        aiTestResult.testedAt) ||
                                                    provider.last_tested_at
                                                        ? new Date(
                                                              (aiTestResult?.providerId ===
                                                                  provider.id &&
                                                                  aiTestResult.testedAt) ||
                                                                  provider.last_tested_at!,
                                                          ).toLocaleString(
                                                              'pt-BR',
                                                          )
                                                        : 'Não testado'}
                                                    {provider.last_tested_at &&
                                                        aiTestResult?.providerId !==
                                                            provider.id && (
                                                            <div className="mt-1">
                                                                <span
                                                                    className={`badge badge-sm ${provider.last_test_succeeded ? 'badge-success' : 'badge-error'}`}
                                                                >
                                                                    {provider.last_test_succeeded
                                                                        ? '✓ Sucesso'
                                                                        : '✕ Falha'}
                                                                </span>
                                                            </div>
                                                        )}
                                                    {aiTestResult?.providerId ===
                                                        provider.id && (
                                                        <div
                                                            className={`mt-1 font-semibold ${aiTestResult.type === 'success' ? 'text-success' : 'text-error'}`}
                                                        >
                                                            {aiTestResult.type ===
                                                            'success'
                                                                ? '✓ Conexão aprovada'
                                                                : '✕ Conexão recusada'}
                                                        </div>
                                                    )}
                                                </td>
                                                <td>
                                                    <div className="flex justify-end gap-1">
                                                        {abilities.aiTest && (
                                                            <button
                                                                type="button"
                                                                className="btn btn-outline btn-xs"
                                                                disabled={
                                                                    processing
                                                                }
                                                                onClick={() =>
                                                                    testAiProvider(
                                                                        provider,
                                                                    )
                                                                }
                                                            >
                                                                Testar
                                                            </button>
                                                        )}
                                                        {abilities.aiUpdate && (
                                                            <>
                                                                <button
                                                                    type="button"
                                                                    className="btn btn-ghost btn-xs"
                                                                    onClick={() =>
                                                                        openAiProvider(
                                                                            provider,
                                                                        )
                                                                    }
                                                                >
                                                                    Editar
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    className="btn btn-ghost text-error btn-xs"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                    onClick={() =>
                                                                        removeAiProvider(
                                                                            provider,
                                                                        )
                                                                    }
                                                                >
                                                                    Excluir
                                                                </button>
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                        {aiProviders.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="py-10 text-center text-base-content/55"
                                                >
                                                    Nenhum provedor de IA
                                                    configurado.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                )}

                {tab === 'appearance' && (
                    <section className="card border border-base-300 bg-base-100 shadow-sm">
                        <div className="card-body">
                            <h2 className="card-title">Tema global</h2>
                            <p className="text-sm text-base-content/60">
                                Escolha o tema padrão para todos os usuários.
                            </p>
                            <div className="mt-5 grid gap-4 md:grid-cols-3">
                                {['light', 'dark', 'forest'].map((item) => (
                                    <button
                                        key={item}
                                        type="button"
                                        data-theme={item}
                                        onClick={() => setSelectedTheme(item)}
                                        className={`rounded-box border-2 bg-base-100 p-4 text-left transition ${selectedTheme === item ? 'border-primary ring-2 ring-primary/25' : 'border-base-300'}`}
                                    >
                                        <div className="mb-4 flex gap-2">
                                            <span className="size-7 rounded-full bg-primary" />
                                            <span className="size-7 rounded-full bg-secondary" />
                                            <span className="size-7 rounded-full bg-accent" />
                                            <span className="size-7 rounded-full bg-neutral" />
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="font-bold capitalize">
                                                {item}
                                            </span>
                                            {selectedTheme === item && (
                                                <span className="badge badge-primary">
                                                    Selecionado
                                                </span>
                                            )}
                                        </div>
                                    </button>
                                ))}
                            </div>
                            {abilities.appearanceUpdate && (
                                <div className="mt-5 card-actions justify-end">
                                    <button
                                        type="button"
                                        onClick={saveTheme}
                                        disabled={processing}
                                        className="btn btn-primary"
                                    >
                                        Aplicar tema
                                    </button>
                                </div>
                            )}
                        </div>
                    </section>
                )}
            </main>

            <dialog ref={companyDialog} className="modal">
                <div className="modal-box max-w-4xl">
                    <h3 className="text-xl font-bold">
                        {editingCompany ? 'Editar unidade' : 'Nova unidade'}
                    </h3>
                    <form
                        key={editingCompany?.id ?? 'new'}
                        ref={companyForm}
                        onSubmit={saveCompany}
                        className="mt-5 space-y-5"
                    >
                        <div className="grid gap-4 md:grid-cols-3">
                            <Field label="Tipo">
                                <select
                                    name="unit_type"
                                    className="select w-full"
                                    defaultValue={
                                        editingCompany?.unit_type ??
                                        emptyCompany.unit_type
                                    }
                                >
                                    <option value="headquarters">Matriz</option>
                                    <option value="branch">Filial</option>
                                </select>
                            </Field>
                            <Field label="Número da unidade">
                                <input
                                    name="unit_number"
                                    className="input w-full"
                                    required
                                    defaultValue={editingCompany?.unit_number}
                                />
                            </Field>
                            <Field label="Nome da unidade">
                                <input
                                    name="unit_name"
                                    className="input w-full"
                                    required
                                    defaultValue={editingCompany?.unit_name}
                                />
                            </Field>
                            <Field label="Matriz vinculada">
                                <select
                                    name="headquarters_id"
                                    className="select w-full"
                                    defaultValue={
                                        editingCompany?.headquarters_id ?? ''
                                    }
                                >
                                    <option value="">Nenhuma</option>
                                    {headquarters.map((company) => (
                                        <option
                                            key={company.id}
                                            value={company.id}
                                        >
                                            {company.unit_number} —{' '}
                                            {company.unit_name}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Razão social">
                                <input
                                    name="name"
                                    className="input w-full"
                                    required
                                    defaultValue={editingCompany?.name}
                                />
                            </Field>
                            <Field label="Nome fantasia">
                                <input
                                    name="trade_name"
                                    className="input w-full"
                                    defaultValue={
                                        editingCompany?.trade_name ?? ''
                                    }
                                />
                            </Field>
                            <Field label="CNPJ">
                                <div className="join w-full">
                                    <input
                                        name="cnpj"
                                        className="input join-item w-full"
                                        inputMode="numeric"
                                        maxLength={18}
                                        required
                                        defaultValue={editingCompany?.cnpj}
                                        onChange={(event) =>
                                            scheduleCnpjLookup(
                                                event.currentTarget.value,
                                            )
                                        }
                                    />
                                    <button
                                        type="button"
                                        className="btn join-item btn-outline"
                                        disabled={consultingCnpj}
                                        onClick={() => {
                                            const field =
                                                companyForm.current?.elements.namedItem(
                                                    'cnpj',
                                                );

                                            if (
                                                field instanceof
                                                HTMLInputElement
                                            ) {
                                                lookupCnpj(field.value);
                                            }
                                        }}
                                    >
                                        {consultingCnpj ? (
                                            <span className="loading loading-sm loading-spinner" />
                                        ) : (
                                            'Consultar'
                                        )}
                                    </button>
                                </div>
                            </Field>
                            <Field label="CEP">
                                <input
                                    name="postal_code"
                                    className="input w-full"
                                    required
                                    defaultValue={editingCompany?.postal_code}
                                />
                            </Field>
                            <Field label="Logradouro">
                                <input
                                    name="address"
                                    className="input w-full"
                                    required
                                    defaultValue={editingCompany?.address}
                                />
                            </Field>
                            <Field label="Número">
                                <input
                                    name="address_number"
                                    className="input w-full"
                                    required
                                    defaultValue={
                                        editingCompany?.address_number
                                    }
                                />
                            </Field>
                            <Field label="Complemento">
                                <input
                                    name="address_complement"
                                    className="input w-full"
                                    defaultValue={
                                        editingCompany?.address_complement ?? ''
                                    }
                                />
                            </Field>
                            <Field label="Bairro">
                                <input
                                    name="district"
                                    className="input w-full"
                                    required
                                    defaultValue={editingCompany?.district}
                                />
                            </Field>
                            <Field label="Cidade">
                                <input
                                    name="city"
                                    className="input w-full"
                                    required
                                    defaultValue={editingCompany?.city}
                                />
                            </Field>
                            <Field label="UF">
                                <input
                                    name="state"
                                    className="input w-full uppercase"
                                    maxLength={2}
                                    required
                                    defaultValue={editingCompany?.state}
                                />
                            </Field>
                            <Field label="Status">
                                <select
                                    name="active"
                                    className="select w-full"
                                    defaultValue={
                                        editingCompany?.active === false
                                            ? '0'
                                            : '1'
                                    }
                                >
                                    <option value="1">Ativa</option>
                                    <option value="0">Inativa</option>
                                </select>
                            </Field>
                        </div>
                        <div className="modal-action">
                            <button
                                type="button"
                                onClick={() => companyDialog.current?.close()}
                                className="btn btn-ghost"
                            >
                                Cancelar
                            </button>
                            <button
                                disabled={processing}
                                className="btn btn-primary"
                            >
                                {processing ? (
                                    <span className="loading loading-spinner" />
                                ) : null}
                                Salvar unidade
                            </button>
                        </div>
                    </form>
                </div>
                <form method="dialog" className="modal-backdrop">
                    <button>Fechar</button>
                </form>
            </dialog>

            <dialog ref={aiDialog} className="modal">
                <div className="modal-box max-w-3xl">
                    <h3 className="text-xl font-bold">
                        {editingAiProvider
                            ? 'Editar provedor de IA'
                            : 'Adicionar provedor de IA'}
                    </h3>
                    <form
                        key={editingAiProvider?.id ?? 'new-ai'}
                        onSubmit={saveAiProvider}
                        className="mt-5 space-y-5"
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field label="Nome da configuração">
                                <input
                                    name="name"
                                    className="input w-full"
                                    required
                                    placeholder="Ex.: IA para triagem"
                                    defaultValue={editingAiProvider?.name ?? ''}
                                />
                            </Field>
                            <Field label="Provedor">
                                <select
                                    name="provider"
                                    className="select w-full"
                                    value={selectedAiType}
                                    onChange={(event) =>
                                        setSelectedAiType(event.target.value)
                                    }
                                >
                                    {aiProviderOptions.map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Modelo">
                                <input
                                    name="model"
                                    className="input w-full"
                                    required
                                    placeholder={
                                        aiProviderOptions.find(
                                            ([value]) =>
                                                value === selectedAiType,
                                        )?.[2]
                                    }
                                    defaultValue={
                                        editingAiProvider?.model ??
                                        aiProviderOptions.find(
                                            ([value]) =>
                                                value === selectedAiType,
                                        )?.[2]
                                    }
                                />
                            </Field>
                            <Field
                                label={`Chave de API${editingAiProvider?.has_api_key ? ' — já configurada' : ''}`}
                            >
                                <input
                                    name="api_key"
                                    type="password"
                                    className="input w-full"
                                    required={
                                        selectedAiType !== 'ollama' &&
                                        !editingAiProvider?.has_api_key
                                    }
                                    placeholder={
                                        editingAiProvider?.has_api_key
                                            ? 'Deixe em branco para manter'
                                            : selectedAiType === 'ollama'
                                              ? 'Não necessária'
                                              : 'Cole a chave secreta'
                                    }
                                    autoComplete="new-password"
                                />
                            </Field>
                            <Field label="URL base (opcional)">
                                <input
                                    name="base_url"
                                    type="url"
                                    className="input w-full"
                                    placeholder={
                                        selectedAiType === 'ollama'
                                            ? 'http://host.docker.internal:11434'
                                            : 'Use o endereço padrão do provedor'
                                    }
                                    defaultValue={
                                        editingAiProvider?.base_url ?? ''
                                    }
                                />
                            </Field>
                            <Field label="Organização/projeto (opcional)">
                                <input
                                    name="organization"
                                    className="input w-full"
                                    defaultValue={
                                        editingAiProvider?.organization ?? ''
                                    }
                                />
                            </Field>
                            <Field label="Timeout (segundos)">
                                <input
                                    name="timeout"
                                    type="number"
                                    min={5}
                                    max={300}
                                    className="input w-full"
                                    required
                                    defaultValue={
                                        editingAiProvider?.timeout ?? 60
                                    }
                                />
                            </Field>
                            <Field label="Máximo de tokens de saída">
                                <input
                                    name="max_output_tokens"
                                    type="number"
                                    min={1}
                                    className="input w-full"
                                    required
                                    defaultValue={
                                        editingAiProvider?.max_output_tokens ??
                                        4096
                                    }
                                />
                            </Field>
                            <Field label="Temperatura (0 a 2)">
                                <input
                                    name="temperature"
                                    type="number"
                                    min={0}
                                    max={2}
                                    step={0.01}
                                    className="input w-full"
                                    required
                                    defaultValue={
                                        editingAiProvider?.temperature ?? 0.2
                                    }
                                />
                            </Field>
                            <Field label="Status">
                                <select
                                    name="enabled"
                                    className="select w-full"
                                    defaultValue={
                                        editingAiProvider?.enabled === false
                                            ? '0'
                                            : '1'
                                    }
                                >
                                    <option value="1">Ativo</option>
                                    <option value="0">Inativo</option>
                                </select>
                            </Field>
                            <label className="flex items-center gap-3 md:col-span-2">
                                <input
                                    type="checkbox"
                                    name="is_default"
                                    value="1"
                                    className="checkbox checkbox-primary"
                                    defaultChecked={
                                        editingAiProvider?.is_default ??
                                        aiProviders.length === 0
                                    }
                                />
                                <span>
                                    <strong>Usar como provedor padrão</strong>
                                    <span className="block text-xs text-base-content/55">
                                        Substitui o padrão atual.
                                    </span>
                                </span>
                            </label>
                        </div>
                        <div className="modal-action">
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => aiDialog.current?.close()}
                            >
                                Cancelar
                            </button>
                            <button
                                disabled={processing}
                                className="btn btn-primary"
                            >
                                {processing && (
                                    <span className="loading loading-spinner" />
                                )}
                                Salvar provedor
                            </button>
                        </div>
                    </form>
                </div>
                <form method="dialog" className="modal-backdrop">
                    <button>Fechar</button>
                </form>
            </dialog>

            {notice && (
                <div className="toast toast-end z-50">
                    <div
                        className={`alert ${notice.type === 'success' ? 'alert-success' : 'alert-error'}`}
                    >
                        <span>{notice.message}</span>
                    </div>
                </div>
            )}
        </>
    );
}

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <label className="fieldset">
            <span className="fieldset-legend">{label}</span>
            {children}
        </label>
    );
}

SystemSettings.layout = {
    breadcrumbs: [{ title: 'Configurações do sistema', href: index() }],
};
