import { Form, Head, usePage } from '@inertiajs/react';
import type { InputHTMLAttributes } from 'react';
import { useRef, useState } from 'react';
import { update } from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import { edit } from '@/routes/profile';

const onlyDigits = (value: string, limit: number) =>
    value.replace(/\D/g, '').slice(0, limit);

const maskCpf = (value: string) =>
    onlyDigits(value, 11)
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');

const maskCep = (value: string) =>
    onlyDigits(value, 8).replace(/(\d{5})(\d)/, '$1-$2');

const maskPhone = (value: string) => {
    const digits = onlyDigits(value, 11);

    if (digits.length <= 10) {
        return digits
            .replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{4})(\d)/, '$1-$2');
    }

    return digits
        .replace(/(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d)/, '$1-$2');
};

export default function Profile() {
    const { auth } = usePage().props;
    const user = auth.user;
    const [preview, setPreview] = useState(user.avatar ?? '');
    const [consultingCep, setConsultingCep] = useState(false);
    const [cepMessage, setCepMessage] = useState<{
        type: 'success' | 'error';
        text: string;
    } | null>(null);
    const cepTimer = useRef<number | undefined>(undefined);

    const lookupCep = async (rawCep: string) => {
        const cep = rawCep.replace(/\D/g, '');

        if (cep.length !== 8 || consultingCep) {
            return;
        }

        setConsultingCep(true);
        setCepMessage(null);

        try {
            const response = await fetch(`/settings/profile/cep/${cep}`, {
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(
                    payload.message ?? 'Não foi possível consultar o CEP.',
                );
            }

            const form = document.getElementById(
                'profile-form',
            ) as HTMLFormElement | null;

            Object.entries(payload.address as Record<string, string>).forEach(
                ([name, value]) => {
                    const field = form?.elements.namedItem(name);

                    if (field instanceof HTMLInputElement) {
                        field.value = value;
                    }
                },
            );

            setCepMessage({
                type: 'success',
                text: 'Endereço preenchido automaticamente.',
            });
        } catch (error) {
            setCepMessage({
                type: 'error',
                text:
                    error instanceof Error
                        ? error.message
                        : 'Erro ao consultar o CEP.',
            });
        } finally {
            setConsultingCep(false);
        }
    };

    const scheduleCepLookup = (value: string) => {
        window.clearTimeout(cepTimer.current);

        if (value.replace(/\D/g, '').length === 8) {
            cepTimer.current = window.setTimeout(() => lookupCep(value), 500);
        }
    };

    return (
        <>
            <Head title="Meu perfil" />

            <div className="space-y-6">
                <div>
                    <div className="mb-3 badge badge-outline badge-primary">
                        Minha conta
                    </div>
                    <h1 className="text-3xl font-bold">Dados do perfil</h1>
                    <p className="mt-2 text-base-content/60">
                        Atualize seus dados pessoais, endereço e foto.
                    </p>
                </div>

                <Form
                    {...update.form()}
                    id="profile-form"
                    options={{ preserveScroll: true }}
                    encType="multipart/form-data"
                    className="space-y-6"
                >
                    {({ processing, errors, recentlySuccessful }) => (
                        <>
                            <section className="card border border-base-300 bg-base-100 shadow-sm">
                                <div className="card-body">
                                    <h2 className="card-title">
                                        Foto e identificação
                                    </h2>
                                    <div className="mt-3 flex flex-col gap-5 sm:flex-row sm:items-center">
                                        <div className="placeholder avatar">
                                            <div className="size-24 overflow-hidden rounded-full bg-base-300">
                                                {preview ? (
                                                    <img
                                                        src={preview}
                                                        alt={`Foto de ${user.name}`}
                                                        className="size-full object-cover"
                                                    />
                                                ) : (
                                                    <span className="text-2xl font-bold">
                                                        {user.name
                                                            .split(' ')
                                                            .slice(0, 2)
                                                            .map(
                                                                (part) =>
                                                                    part[0],
                                                            )
                                                            .join('')
                                                            .toUpperCase()}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex-1">
                                            <label className="fieldset">
                                                <span className="fieldset-legend">
                                                    Foto do perfil
                                                </span>
                                                <input
                                                    type="file"
                                                    name="avatar"
                                                    accept="image/jpeg,image/png,image/webp"
                                                    className="file-input w-full max-w-md"
                                                    onChange={(event) => {
                                                        const file =
                                                            event.currentTarget
                                                                .files?.[0];

                                                        if (file) {
                                                            setPreview(
                                                                URL.createObjectURL(
                                                                    file,
                                                                ),
                                                            );
                                                        }
                                                    }}
                                                />
                                            </label>
                                            <p className="mt-1 text-xs text-base-content/55">
                                                JPG, PNG ou WEBP; mínimo 36×36
                                                px e máximo 2 MB.
                                            </p>
                                            <InputError
                                                className="mt-2"
                                                message={errors.avatar}
                                            />
                                        </div>
                                    </div>

                                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                                        <ProfileField
                                            label="Nome completo"
                                            name="name"
                                            required
                                            defaultValue={user.name}
                                            error={errors.name}
                                        />
                                        <ProfileField
                                            label="E-mail"
                                            name="email"
                                            type="email"
                                            defaultValue={user.email}
                                            disabled
                                            hint="O e-mail é gerenciado pela autenticação."
                                        />
                                        <ProfileField
                                            label="CPF"
                                            name="cpf"
                                            inputMode="numeric"
                                            maxLength={14}
                                            defaultValue={maskCpf(
                                                user.cpf ?? '',
                                            )}
                                            onInput={(event) => {
                                                event.currentTarget.value =
                                                    maskCpf(
                                                        event.currentTarget
                                                            .value,
                                                    );
                                            }}
                                            error={errors.cpf}
                                            placeholder="000.000.000-00"
                                        />
                                        <ProfileField
                                            label="Data de nascimento"
                                            name="birth_date"
                                            type="date"
                                            defaultValue={user.birth_date ?? ''}
                                            error={errors.birth_date}
                                        />
                                        <ProfileField
                                            label="Telefone"
                                            name="phone"
                                            inputMode="tel"
                                            maxLength={15}
                                            defaultValue={maskPhone(
                                                user.phone ?? '',
                                            )}
                                            onInput={(event) => {
                                                event.currentTarget.value =
                                                    maskPhone(
                                                        event.currentTarget
                                                            .value,
                                                    );
                                            }}
                                            error={errors.phone}
                                            placeholder="(00) 00000-0000"
                                        />
                                        <label className="fieldset">
                                            <span className="fieldset-legend">
                                                Gênero
                                            </span>
                                            <select
                                                name="gender"
                                                className="select w-full"
                                                defaultValue={user.gender ?? ''}
                                            >
                                                <option value="">
                                                    Selecione
                                                </option>
                                                <option value="female">
                                                    Feminino
                                                </option>
                                                <option value="male">
                                                    Masculino
                                                </option>
                                                <option value="non_binary">
                                                    Não binário
                                                </option>
                                                <option value="not_informed">
                                                    Prefiro não informar
                                                </option>
                                            </select>
                                            <InputError
                                                message={errors.gender}
                                            />
                                        </label>
                                    </div>
                                </div>
                            </section>

                            <section className="card border border-base-300 bg-base-100 shadow-sm">
                                <div className="card-body">
                                    <h2 className="card-title">Endereço</h2>
                                    <div className="mt-3 grid gap-4 md:grid-cols-2">
                                        <label className="fieldset md:col-span-2 lg:col-span-1">
                                            <span className="fieldset-legend">
                                                CEP
                                            </span>
                                            <div className="join w-full">
                                                <input
                                                    name="postal_code"
                                                    inputMode="numeric"
                                                    maxLength={9}
                                                    className="input join-item w-full"
                                                    defaultValue={maskCep(
                                                        user.postal_code ?? '',
                                                    )}
                                                    onChange={(event) =>
                                                        scheduleCepLookup(
                                                            event.currentTarget
                                                                .value,
                                                        )
                                                    }
                                                    onInput={(event) => {
                                                        event.currentTarget.value =
                                                            maskCep(
                                                                event
                                                                    .currentTarget
                                                                    .value,
                                                            );
                                                    }}
                                                />
                                                <button
                                                    type="button"
                                                    className="btn join-item btn-outline"
                                                    disabled={consultingCep}
                                                    onClick={() => {
                                                        const form =
                                                            document.getElementById(
                                                                'profile-form',
                                                            ) as HTMLFormElement | null;
                                                        const field =
                                                            form?.elements.namedItem(
                                                                'postal_code',
                                                            );

                                                        if (
                                                            field instanceof
                                                            HTMLInputElement
                                                        ) {
                                                            lookupCep(
                                                                field.value,
                                                            );
                                                        }
                                                    }}
                                                >
                                                    {consultingCep ? (
                                                        <span className="loading loading-sm loading-spinner" />
                                                    ) : (
                                                        'Consultar'
                                                    )}
                                                </button>
                                            </div>
                                            {cepMessage && (
                                                <span
                                                    className={`text-xs ${cepMessage.type === 'success' ? 'text-success' : 'text-error'}`}
                                                >
                                                    {cepMessage.text}
                                                </span>
                                            )}
                                            <InputError
                                                message={errors.postal_code}
                                            />
                                        </label>
                                        <div>
                                            <ProfileField
                                                label="Logradouro"
                                                name="address"
                                                defaultValue={
                                                    user.address ?? ''
                                                }
                                                error={errors.address}
                                            />
                                        </div>
                                        <ProfileField
                                            label="Número"
                                            name="address_number"
                                            defaultValue={
                                                user.address_number ?? ''
                                            }
                                            error={errors.address_number}
                                        />
                                        <ProfileField
                                            label="Complemento"
                                            name="address_complement"
                                            defaultValue={
                                                user.address_complement ?? ''
                                            }
                                            error={errors.address_complement}
                                        />
                                        <ProfileField
                                            label="Bairro"
                                            name="district"
                                            defaultValue={user.district ?? ''}
                                            error={errors.district}
                                        />
                                        <ProfileField
                                            label="Cidade"
                                            name="city"
                                            defaultValue={user.city ?? ''}
                                            error={errors.city}
                                        />
                                        <ProfileField
                                            label="UF"
                                            name="state"
                                            maxLength={2}
                                            className="uppercase"
                                            defaultValue={user.state ?? ''}
                                            error={errors.state}
                                        />
                                    </div>
                                </div>
                            </section>

                            <div className="flex items-center justify-end gap-4">
                                {recentlySuccessful && (
                                    <span className="text-sm text-success">
                                        Perfil atualizado.
                                    </span>
                                )}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="btn btn-primary"
                                >
                                    {processing && (
                                        <span className="loading loading-sm loading-spinner" />
                                    )}
                                    Salvar alterações
                                </button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser />
        </>
    );
}

type ProfileFieldProps = InputHTMLAttributes<HTMLInputElement> & {
    label: string;
    name: string;
    error?: string;
    hint?: string;
};

function ProfileField({
    label,
    error,
    hint,
    className = '',
    ...props
}: ProfileFieldProps) {
    return (
        <label className="fieldset">
            <span className="fieldset-legend">{label}</span>
            <input className={`input w-full ${className}`} {...props} />
            {hint && <span className="label text-xs">{hint}</span>}
            <InputError message={error} />
        </label>
    );
}

Profile.layout = {
    breadcrumbs: [{ title: 'Meu perfil', href: edit() }],
};
