import type { Option } from './organization';

export type ApplicationSummary = {
    id: string;
    code: string;
    name: string;
    description: string | null;
    owner_org_id: string;
    owner_name: string | null;
    status: 'draft' | 'active' | 'archived';
    status_label: string;
    is_system: boolean;
    entities_count?: number;
};

export type EntitySummary = {
    id: string;
    code: string;
    name: string;
    name_plural: string;
    description: string | null;
    default_visibility: string;
    default_visibility_label: string;
    is_shared: boolean;
    is_system: boolean;
    storage_type: string;
    title_template: string;
    has_draft: boolean;
    has_published: boolean;
    published_version?: number | null;
    draft_version?: number | null;
};

export type FieldConfig = Record<string, unknown>;

export type FieldSummary = {
    id: string | null;
    field_key: string;
    code: string;
    label: string;
    help_text: string | null;
    type: string;
    type_label: string;
    is_required: boolean;
    is_unique: boolean;
    is_indexed: boolean;
    is_searchable: boolean;
    classification: string;
    classification_label: string;
    is_personal: boolean;
    config: FieldConfig;
    position: number;
    summary: string;
};

export type FieldChange = {
    field_key: string;
    code: string;
    label: string;
    kind: 'added' | 'removed' | 'modified';
    category: 'safe' | 'warning' | 'migration' | 'blocked';
    category_label: string;
    messages: string[];
};

export type DraftReport = {
    errors: string[];
    warnings: string[];
    changes: FieldChange[];
    has_changes: boolean;
    requires_privacy_review: boolean;
    can_publish: boolean;
    blockers: string[];
};

export type FieldTypeOption = Option & {
    supports_index: boolean;
    supports_search: boolean;
    supports_default: boolean;
};

export type VersionSummary = {
    id: string;
    version: number;
    status: 'published' | 'superseded';
    change_summary: string | null;
    published_at: string | null;
};
