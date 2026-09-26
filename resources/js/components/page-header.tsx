import type { ReactNode } from 'react';

type Props = {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
};

export function PageHeader({ title, description, actions }: Props) {
    return (
        <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div className="space-y-1">
                <h1 className="text-2xl font-semibold tracking-tight">
                    {title}
                </h1>
                {description && (
                    <div className="text-muted-foreground">{description}</div>
                )}
            </div>
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </header>
    );
}
