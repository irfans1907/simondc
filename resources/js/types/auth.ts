export type User = {
    id: number;
    name: string;
    email: string;
    role?: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Role = {
    id: number;
    name: string;
    guard_name: string;
    permissions: string[];
    users_count: number;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Permission = {
    id: number;
    name: string;
    guard_name: string;
    roles: string[];
    roles_count: number;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    permissions: string[];
    is_superadmin: boolean;
};

export type Menu = {
    id: number;
    name: string;
    group: string;
    parent_id: number | null;
    url: string;
    icon: string | null;
    order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type File = {
    id: number;
    type: 'folder' | 'file';
    name: string;
    path: string | null;
    parent_id: number | null;
    mime_type: string | null;
    size: number | null;
    created_at: string;
    updated_at: string;
    created_label: string;
    updated_label: string;
    owner: { id: number; name: string } | null;
    [key: string]: unknown;
};

export type FolderBreadcrumb = {
    id: number;
    name: string;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
