export type CrudRecord = Record<string, unknown> & {
    id?: string | number;
};

export interface CrudOperation {
    method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
    url: string;
}

export interface CrudOption {
    label: string;
    value: string | number | boolean;
}

export interface CrudField {
    name: string;
    label: string;
    type?: string;
    required?: boolean;
    required_on_create?: boolean;
    readonly_on_edit?: boolean;
    create_only?: boolean;
    edit_only?: boolean;
    default?: unknown;
    step?: string | number;
    placeholder?: string;
    help?: string;
    lookup?: string;
    depends_on?: string;
    multiple?: boolean;
    options?: CrudOption[];
}

export interface CrudColumn {
    key: string;
    label: string;
    type?: string;
    map?: Record<string, string>;
}

export interface CrudResource {
    title: string;
    description?: string;
    note?: string;
    group?: string;
    mode?: string;
    list?: CrudOperation;
    create?: CrudOperation;
    update?: CrudOperation;
    delete?: CrudOperation;
    columns?: CrudColumn[];
    fields?: CrudField[];
}

export interface ResourceLink {
    key: string;
    title: string;
    url: string;
    inertia: boolean;
}

export interface ResourcePageProps {
    resourceKey: string;
    resource: CrudResource;
    groupTitle: string;
    siblingResources: ResourceLink[];
    operationsUrl: string;
    lookupBase: string;
    csrfToken: string;
}
