export type Organization = {
    id: string;
    parent_id: string | null;
    code: string;
    name: string;
    short_name: string | null;
    kind: string;
    kind_label: string;
    path: string;
    depth: number;
    is_active: boolean;
    is_root: boolean;
    valid_to: string | null;
};

export type OrganizationRow = Organization & { can_update: boolean };

export type Option = { value: string; label: string };

export type ParentOption = Option & { depth: number };
