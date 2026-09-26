export type RuntimeEntity = {
    application_code: string;
    application_name: string;
    code: string;
    name: string;
    name_plural: string;
    version: number;
};

export type RuntimeOption = { value: string; label: string };

export type RuntimeField = {
    code: string;
    label: string;
    help_text: string | null;
    type: string;
    component: string;
    required: boolean;
    access: 'visible' | 'masked' | 'hidden';
    deferred: boolean;
    classification: string;
    classification_label: string;
    options: RuntimeOption[];
    config: {
        max_length?: number;
        min?: string | number;
        max?: string | number;
        scale?: number;
        mimes?: string[];
        max_files?: number;
        max_kb?: number;
        cardinality?: 'many_to_one' | 'many_to_many';
        max_selected?: number;
    };
    default: unknown;
    in_list: boolean;
    filterable: boolean;
};

export type RuntimeFile = {
    id: string;
    name: string;
    size: number;
    available: boolean;
    url: string;
};

export type RuntimeValues = Record<string, unknown>;
