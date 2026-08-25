export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    cpf?: string | null;
    birth_date?: string | null;
    phone?: string | null;
    gender?: string | null;
    postal_code?: string | null;
    address?: string | null;
    address_number?: string | null;
    address_complement?: string | null;
    district?: string | null;
    city?: string | null;
    state?: string | null;
    email_verified_at: string | null;
    /* @chisel-2fa */
    two_factor_enabled?: boolean;
    /* @end-chisel-2fa */
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    impersonation: {
        active: boolean;
    };
    abilities: {
        candidatePortal: boolean;
        systemSettingsView: boolean;
        usersView: boolean;
        hrView: boolean;
        virtualOfficeView: boolean;
        tracksTime: boolean;
        personnelView: boolean;
        timeApprovalsView: boolean;
        medicalCertificateSubmit: boolean;
    };
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

/* @chisel-2fa */
export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
/* @end-chisel-2fa */
